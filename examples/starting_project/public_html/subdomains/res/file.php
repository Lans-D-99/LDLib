<?php
namespace BaseWebsite\Pages\Res\Gen;

use Swoole\Http\Request;
use Swoole\Http\Response;
use LDLib\AWS\AWS;
use LDLib\Cache\LDRedis;

class Page {
    public static function get(Request $request, Response $response, string $fileToFetch) {
        $response->header('Accept-Ranges','bytes');
        if (preg_match('/^[^\/?]*/',urldecode($fileToFetch),$m) == 0) {
            $response->setStatusCode(404);
            return 'File not found.';
        }

        $objectCategory = 'normal';
        if (($request->get['type']??null) == 'avatar') $objectCategory = 'avatar';
        $s3Key = $m[0];
        $awsBucket = $objectCategory == 'avatar' ? $_SERVER['LD_AWS_BUCKET_AVATARS'] : $_SERVER['LD_AWS_BUCKET_GENERAL'];
        $redisKey = $objectCategory == 'avatar' ? "s3:avatars:$s3Key" : "s3:general:$s3Key";

        $redis = new LDRedis();

        // Return cached result if exists
        $vCache = $redis->redis->hGetAll($redisKey);
        if ($vCache != null)  {
            if (isset($vCache['errorCode'])) {
                $response->setStatusCode($vCache['errorCode']);
                return $vCache['errorMsg'];
            } else {
                return self::serveData($request,$response,$vCache['data'],$vCache['mimetype']);
            }
        }

        // Fetch from S3
        $s3 = AWS::getS3Client();
        $res = $s3->getObject($awsBucket,$s3Key);
        if ($res instanceof \LDLib\OperationResult) return 'res/file error';

        // S3 failure handling
        if ($res['statusCode'] != 200) {
            $errMsg = '';
            $errCode = 500;
            $statusCode = $res['statusCode'];
            switch ($statusCode) {
                case 404: $errCode = $statusCode; $errMsg = 'File not found.'; break;
                case 403: $errCode = $statusCode; $errMsg = 'Access denied.'; break;
                default: $errMsg = $_SERVER['LD_DEBUG'] ? "Status Code: $statusCode" : 'Unknown error.'; break;
            }

            // Cache failure
            $redis->hMSet($redisKey,['errorCode' => $errCode, 'errorMsg' => $errMsg]);
            $redis->expire($redisKey,15);

            $response->setStatusCode($errCode);
            return $errMsg;
        }

        $contentType = preg_match('/^(?:image\/|video\/|application\/pdf$|text\/(?:plain|css)$)/',$res['headers']['content-type']) > 0 ? $res['headers']['content-type'] : 'application/octet-stream';
        $data = $res['data'];

        // Cache result
        if (($res['headers']['content-length']??0) <= 25_000_000) {
            $redis->hMSet($redisKey,['mimetype' => $contentType, 'data' => $data]);
            $redis->expire($redisKey,3600);
        }

        return self::serveData($request,$response,$data,$contentType);
    }

    private static function serveData(Request $request, Response $response, string $data, string $contentType) {
        if (($request->header['range']??null) != null && str_starts_with($request->header['range'], 'bytes=')) {
            $sRanges = str_replace(['bytes=',' '],'',$request->header['range']);
            $aRanges = explode(',',$sRanges);
            $isMultipart = count($aRanges) > 1;
            $boundary = bin2hex(random_bytes(8));
            $dataLength = strlen($data);

            if ($isMultipart) $response->header('Content-Type', "multipart/byteranges; boundary=$boundary");
            else $response->header('Content-Type', $contentType);
            $response->setStatusCode(206); // 206: Partial Content

            $s = '';
            $i = 0;
            foreach ($aRanges as $sRange) {
                if (preg_match('/^(\d+)?\-(\d+)?$/',$sRange,$m,PREG_UNMATCHED_AS_NULL) == 0) continue;


                if (($m[2]??0) > $dataLength) { // abort //? Need better?
                    $response->setStatusCode(200);
                    $response->header('Content-Type', $contentType);
                    $response->header('Content-Length', $dataLength);
                    return $data;
                }

                $v = substr($data, $m[1]??0, ($m[2] == null ? null : ($m[2]-$m[1]) + 1));

                if ($isMultipart) {
                    if ($i++ != 0) $s .= "\n";
                    $s .= <<< EOF
                    --$boundary
                    Content-Type: $contentType
                    Content-Range: bytes {$sRange}/{$dataLength}

                    $v
                    EOF;
                } else {
                    preg_match('/^(\d+)?\-(\d+)?$/',$sRange,$m,PREG_UNMATCHED_AS_NULL);
                    $v1 = $m[1]??0;
                    $v2 = $m[2]??($dataLength-1);
                    $response->header('Content-Range', "bytes {$v1}-{$v2}/{$dataLength}");
                    $s .= $v;
                }
            }
            if ($isMultipart) $s .= "\n--$boundary--";

            $response->header('Content-Length', strlen($s));
            return $s;
        } else {
            $response->header('Content-Type', $contentType);
            $response->header('Content-Length', strlen($data));
            return $data;
        }
    }
}
?>