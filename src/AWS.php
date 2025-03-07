<?php
/*****************************************************************************
 * This file is part of LDLib and subject to the Version 2.0 of the          *
 * Apache License, you may not use this file except in compliance            *
 * with the License. You may obtain a copy of the License at :               *
 *                                                                           *
 *                http://www.apache.org/licenses/LICENSE-2.0                 *
 *                                                                           *
 * Unless required by applicable law or agreed to in writing, software       *
 * distributed under the License is distributed on an "AS IS" BASIS,         *
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  *
 * See the License for the specific language governing permissions and       *
 * limitations under the License.                                            *
 *                                                                           *
 *                Author: Lans.D <lans.d.99@protonmail.com>                  *
 *                                                                           *
 *****************************************************************************/
namespace LDLib\AWS;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use LDLib\Database\LDPDO;
use LDLib\ErrorType;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\OperationResult;
use LDLib\SuccessType;
use LDLib\Utils\Utils;
use Swoole\Coroutine;

use function LDLib\Net\get_curl_handle;

class AWS {
    public static bool $initialized = false;

    public static function getS3Client():LDS3Client {
        return new LDS3Client();
    }
}

class LDS3Client {
    public function __construct(private ?string $accessKey=null, private ?string $secretKey=null, private ?string $region=null, private ?string $securityToken=null) {
        $this->accessKey ??= $_SERVER['LD_AWS_ACCESS_KEY'];
        $this->secretKey ??= $_SERVER['LD_AWS_SECRET_KEY'];
        $this->securityToken ??= $_SERVER['LD_AWS_SECURITY_TOKEN'] == null ? null : $_SERVER['LD_AWS_SECURITY_TOKEN'];
        $this->region ??= $_SERVER['LD_AWS_REGION'];
    }

    public function getObject(string $bucketName, string $key, ?string $range=null) {
        $date = new \DateTime('now');
        $host = "$bucketName.s3.amazonaws.com";
        $requestURI = '/'.rawurlencode($key);

        // Make headers
        $headers = [
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => $date->format('Ymd\THis\Z')
        ];
        if ($this->securityToken != null) $headers['x-amz-security-token'] = $this->securityToken;
        if ($range != null) $headers['range'] = $range;

        // prepRequest
        $prepRequest = static function($client) use(&$host, $requestURI, $date, &$headers) {
            unset($headers['authorization']);
            $headers['authorization'] = self::getAuthorization(
                $host, region:$client->region, accessKey:$client->accessKey, secretKey:$client->secretKey,
                requestURI:$requestURI, date:$date, headers:$headers
            );
            ksort($headers);
            $curlHeaders = [];
            foreach ($headers as $k => $v) $curlHeaders[] = "$k: $v";
    
            // Fetch resource
            $ch = get_curl_handle($host.$requestURI,$outHeaders);
            curl_setopt_array($ch,[
                CURLOPT_HTTPHEADER => $curlHeaders
            ]);
            
            return [$ch,&$outHeaders];
        };

        // Do request
        $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
        $v = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Redo the request if TemporaryRedirect error
        if ($statusCode === 307) {
            $xml = simplexml_load_string($v);
            $host = (string)$xml?->Endpoint;
            $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
            $v = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        if ($v === false) {
            trigger_error(curl_error($ch));
            Logger::log(LogLevel::ERROR, 'CURL - ListObjects', 'Unknown error.');
            return new OperationResult(ErrorType::UNKNOWN,'CURL ERROR');
        }

        return ['data' => $v, 'headers' => $outHeaders, 'statusCode' => $statusCode];
    }

    public function putObject(string $bucketName, string $key, array $file, LDPDO $pdo, string $tableName, ?array $metadata=null, ?callable $onRename=null):OperationResult {
        $date = new \DateTime('now');
        $host = "$bucketName.s3.amazonaws.com";
        $requestURI = '/'.rawurlencode($key);

        $fileData = file_get_contents($file['tmp_name']);
        $fileSize = $file['size'];
        $mimeType = Utils::getMimeType($file);
        $hashedPayload = hash('sha256',$fileData);
        $metadata ??= [];
        $metadata['filename'] ??= $file['name'];

        $deleteFromDatabase = function($key) use($pdo,$tableName) {
            try {
                $stmt = $pdo->prepare("DELETE FROM $tableName WHERE obj_key=?");
                $stmt->execute([$key]);
            } catch (\Throwable $t) {
                Logger::log(LogLevel::ERROR, 'Database', "Couldn't unregister '$key'.");
                Logger::logThrowable($t);
            }
        };

        // Rename key if similar name found?
        try {
            $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE obj_key=?");
            $stmt->execute([$key]);
            if ($stmt->fetch() !== false) {
                $key = isset($onRename) ? $onRename($key) : ((string)microtime(true)).'_'.$key;
                $requestURI = '/'.rawurlencode($key);
            }
        } catch (\Throwable $t) {
            Logger::log(LogLevel::FATAL, 'Database', "Couldn't verify key.");
            Logger::logThrowable($t);
            return new OperationResult(ErrorType::DATABASE_ERROR, "Couldn't verify key.");
        }

        // Register object in database
        try {
            $stmt = $pdo->prepare("INSERT INTO $tableName (obj_key,size,mime_type,status,metadata) VALUES (?,?,?,?,?)");
            $stmt->execute([$key,$fileSize,$mimeType,'Unverified',(isset($metadata) ? json_encode($metadata) : null)]);
        } catch (\Throwable $t) {
            Logger::log(LogLevel::ERROR, 'Database', "Couldn't register object '$key' in database.");
            Logger::logThrowable($t);
            return new OperationResult(ErrorType::DATABASE_ERROR, "Couldn't register object in database.");
        }

        // Make headers
        $headers = [
            'content-type' => $mimeType,
            'x-amz-content-sha256' => $hashedPayload,
            'x-amz-date' => $date->format('Ymd\THis\Z')
        ];
        if ($this->securityToken != null) $headers['x-amz-security-token'] = $this->securityToken;
        foreach ($metadata as $k => $v) $headers['x-amz-meta-'.strtolower($k)] = $v;

        // prepRequest
        $prepRequest = static function($client) use(&$host, $requestURI, $fileData, $date, &$headers, $hashedPayload) {
            unset($headers['authorization']);
            $headers['authorization'] = self::getAuthorization(
                $host, region:$client->region, accessKey:$client->accessKey, secretKey:$client->secretKey,
                requestURI:$requestURI, date:$date, headers:$headers, method:'PUT', hashedPayload:$hashedPayload
            );
            ksort($headers);
            $curlHeaders = [];
            foreach ($headers as $k => $v) $curlHeaders[] = "$k: $v";
    
            // Upload file
            $ch = get_curl_handle($host.$requestURI);
            curl_setopt_array($ch,[
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_POSTFIELDS => $fileData
            ]);
            
            return $ch;
        };

        // Do request
        $ch = $prepRequest($this);
        $v = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Redo the request if TemporaryRedirect error
        if ($statusCode === 307) {
            $xml = simplexml_load_string($v);
            $host = (string)$xml?->Endpoint;
            $ch = $prepRequest($this);
            $v = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        // Other request error handlings
        if ($v === false) {
            Logger::log(LogLevel::ERROR, 'CURL', "Couldn't put object with key '$key'.");
            $deleteFromDatabase($key);
            error_log(curl_error($ch));
            return new OperationResult(ErrorType::AWS_ERROR, "Couldn't put object.");
        } else if ($statusCode != 200) {
            Logger::log(LogLevel::ERROR, 'AWS - S3', "Couldn't put object with key '$key' (statusCode: $statusCode).");
            $deleteFromDatabase($key);
            error_log($v);
            return new OperationResult(ErrorType::AWS_ERROR, "Couldn't put object. (Bad AWS status code)");
        }


        // Validate object in database
        try {
            $stmt = $pdo->prepare("UPDATE $tableName SET status='Verified' WHERE obj_key=?");
            $stmt->execute([$key]);
        } catch (\Throwable $t) {
            Logger::log(LogLevel::ERROR, 'AWS - S3', "Couldn't validate object '$key' in database.");
            Logger::logThrowable($t);
            $deleteFromDatabase($key);
            return new OperationResult(ErrorType::DATABASE_ERROR, "Couldn't validate object in database.");
        }

        return new OperationResult(SuccessType::SUCCESS,null,[],[$key]);
    }

    public function listObjects(string $bucketName, int $maxKeys=1000, ?string $continuationToken=null):OperationResult {
        $date = new \DateTime('now');
        $host = "$bucketName.s3.amazonaws.com";
        $requestURI = '/';

        $queryString = '';;
        $queryStringArray = [];
        if ($continuationToken != null)  {
            $queryString .= "?continuation-token=".urlencode($continuationToken);
            $queryStringArray['continuation-token'] = $continuationToken;
        }
        $queryString .= $queryString != '' ? '&' : '?';
        $queryString .= "list-type=2&max-keys=$maxKeys";
        $queryStringArray['list-type'] = 2; $queryStringArray['max-keys'] = $maxKeys;

        // Make headers
        $headers = [
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => $date->format('Ymd\THis\Z')
        ];
        if ($this->securityToken != null) $headers['x-amz-security-token'] = $this->securityToken;

        // prepRequest
        $prepRequest = static function($client) use(&$host, $requestURI, $queryString, $queryStringArray, &$headers, $date) {
            unset($headers['authorization']);
            $headers['authorization'] = self::getAuthorization(
                $host, region:$client->region, accessKey:$client->accessKey, secretKey:$client->secretKey,
                requestURI:$requestURI, date:$date, headers:$headers, queryStringArray:$queryStringArray
            );
            ksort($headers);
            $curlHeaders = [];
            foreach ($headers as $k => $v) $curlHeaders[] = "$k: $v";

            $ch = get_curl_handle($host.$requestURI.$queryString,$outHeaders);
            curl_setopt_array($ch,[
                CURLOPT_HTTPHEADER => $curlHeaders
            ]);
            return [$ch,&$outHeaders];
        };
        
        // Do request
        $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
        $v = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Redo the request if TemporaryRedirect error
        if ($statusCode === 307) {
            $xml = simplexml_load_string($v);
            $host = (string)$xml?->Endpoint;
            $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
            $v = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        if ($v === false) {
            trigger_error(curl_error($ch));
            Logger::log(LogLevel::ERROR, 'CURL - ListObjects', 'Unknown error.');
            return new OperationResult(ErrorType::UNKNOWN,'CURL ERROR');
        }

        return new OperationResult(SuccessType::SUCCESS,null,[],['data' => $v, 'headers' => $outHeaders, 'statusCode' => $statusCode]);
    }

    public function headObject(string $bucketName, string $key) {
        $date = new \DateTime('now');
        $host = "$bucketName.s3.amazonaws.com";
        $requestURI = '/'.rawurlencode($key);

        // Make headers
        $headers = [
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => $date->format('Ymd\THis\Z')
        ];
        if ($this->securityToken != null) $headers['x-amz-security-token'] = $this->securityToken;

        // prepRequest
        $prepRequest = static function($client) use(&$host, $requestURI, &$headers, $date) {
            unset($headers['authorization']);
            $headers['authorization'] = self::getAuthorization(
                $host, region:$client->region, accessKey:$client->accessKey, secretKey:$client->secretKey,
                requestURI:$requestURI, date:$date, headers:$headers, method:'HEAD'
            );
            ksort($headers);
            $curlHeaders = [];
            foreach ($headers as $k => $v) $curlHeaders[] = "$k: $v";

            $ch = get_curl_handle($host.$requestURI,$outHeaders);
            curl_setopt_array($ch,[
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_CUSTOMREQUEST => 'HEAD',
                CURLOPT_NOBODY => true
            ]);
            return [$ch,&$outHeaders];
        };
        
        // Do request
        $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
        $v = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Redo the request if TemporaryRedirect error
        if ($statusCode === 307) {
            $host = "$bucketName.s3.{$this->region}.amazonaws.com";
            $res = $prepRequest($this); $ch = $res[0]; $outHeaders =& $res[1];
            $v = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        if ($v === false) {
            trigger_error(curl_error($ch));
            Logger::log(LogLevel::ERROR, 'CURL - HeadObject', 'Unknown error.');
            return new OperationResult(ErrorType::UNKNOWN,'CURL ERROR');
        }

        return new OperationResult(SuccessType::SUCCESS,null,[],['headers' => $outHeaders, 'statusCode' => $statusCode]);
    }

    public static function getAuthorization(
        string $host, ?string $region=null, string $method='GET', string $requestURI='/', string $service='s3', array $headers=[],
        ?\DateTimeInterface $date=null, string $hashedPayload='e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        ?string $accessKey=null, ?string $secretKey=null, array $queryStringArray=[]
    ) {
        $date ??= new \DateTimeImmutable('now');
        $accessKey ??= $_SERVER['LD_AWS_ACCESS_KEY'];
        $secretKey ??= $_SERVER['LD_AWS_SECRET_KEY'];
        $region ??= 'us-east-1';

        $smallDate = $date->format('Ymd');
        $longDate = $date->format('Ymd\THis\Z');
        $region = 'eu-west-3';
        $queryString = '';
        foreach ($queryStringArray as $k => $v) {
            if ($queryString != '') $queryString .= '&';
            $queryString .= rawurlencode($k).'='.rawurlencode($v);
        }

        $headers['host'] = $host;
        $headers['x-amz-content-sha256'] = $hashedPayload;
        $headers['x-amz-date'] = $longDate;
        ksort($headers,SORT_STRING);

        $canonicalRequestHeaders = '';
        $signedHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalRequestHeaders .= strtolower($k) . ':' . $v . "\n";
            $signedHeaders .= $signedHeaders == '' ? strtolower($k) : ';'.strtolower($k);
        }

        $canonicalRequest = "$method\n";
        $canonicalRequest .= "$requestURI\n";
        $canonicalRequest .= "$queryString\n";
        $canonicalRequest .= $canonicalRequestHeaders;
        $canonicalRequest .= "\n";
        $canonicalRequest .= "$signedHeaders\n";
        $canonicalRequest .= $hashedPayload;

        $stringToSign = "AWS4-HMAC-SHA256\n";
        $stringToSign .= $longDate."\n";
        $stringToSign .= $smallDate."/$region/$service/aws4_request\n";
        $stringToSign .= hash('sha256',$canonicalRequest);

        $signature = hash_hmac('sha256',$stringToSign,hash_hmac('sha256','aws4_request',hash_hmac('sha256',$service,hash_hmac('sha256',$region,hash_hmac('sha256',$smallDate,"AWS4".$secretKey,true),true),true),true));

        return "AWS4-HMAC-SHA256 Credential=$accessKey/$smallDate/$region/$service/aws4_request,SignedHeaders=$signedHeaders,Signature=$signature";
    }
}
?>