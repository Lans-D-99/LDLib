<?php
namespace LDLib\OAuth;

use Ds\Set;
use LDLib\Cache\LDRedis;
use LDLib\Database\LDPDO;
use LDLib\ErrorType;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\OperationResult;
use LDLib\SuccessType;
use ReflectionEnum;

enum OAuthScopes:string { // scopes are readonly by default
    case USER_BASIC = 'user:basic';
}

class OAuth {
    public static string $urlRegex = '/^https:\/\/\S*?\.\S*?(?:[\s)\[\]{},;"\':<]|\.\s|$)/i';
    public static ?ReflectionEnum $scopesEnum = null;
    
    public static bool $initialized = false;

    public static function init() {
        if (self::$initialized) return;
        self::$scopesEnum = new ReflectionEnum(OAuthScopes::class);
        self::$initialized = true;
    }

    public static function registerClient(LDPDO $pdo, int $userId, array $redirectURIs, string $clientName, ?string $website=null, ?string $description=null, ?string $logo=null):OperationResult {
        if (count($redirectURIs) < 1) return new OperationResult(ErrorType::INVALID_DATA,'Specify at least one redirect URI.');

        $clientId = $userId.'_'.bin2hex(random_bytes(10));
        for ($i=0; $i<50; $i++) {
            $redo = $pdo->query("SELECT 1 FROM oauth_clients WHERE user_id=$userId AND client_id='$clientId'")->fetch() !== false;
            if (!$redo) break;
            if ($i == 49) {
                Logger::log(LogLevel::ERROR, 'OAuth', 'Failed to generate unique client ID.');
                return new OperationResult(ErrorType::UNKNOWN,'Client ID generation failed.');
            }
            $clientId = $userId.'_'.bin2hex(random_bytes(10));
        }
        $clientSecret = bin2hex(random_bytes(50));

        $pdo->query('START TRANSACTION');

        $stmt = $pdo->prepare('INSERT INTO oauth_clients(user_id,client_id,client_name,client_type,client_secret,website,description,logo) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$userId,$clientId,$clientName,'confidential',$clientSecret,$website,$description,$logo]);
        if ($stmt->rowCount() == 0) return new OperationResult(ErrorType::UNKNOWN,'1');

        $stmt = $pdo->prepare('INSERT INTO oauth_clients_redirect_uris(user_id,client_id,number,redirect_uri) VALUES (?,?,?,?)');
        $i = 1;
        foreach ($redirectURIs as $redirectURI) {
            if (preg_match(self::$urlRegex,$redirectURI) > 0) $stmt->execute([$userId,$clientId,$i++,$redirectURI]);
            else { $pdo->query('ROLLBACK'); return new OperationResult(ErrorType::INVALID_DATA,"URL number $i is invalid."); }
        }

        $pdo->query('COMMIT');
        return new OperationResult(SuccessType::SUCCESS,"Successfully registered client. (Client ID: \"$clientId\")");
    }

    public static function requestAuthorization(LDPDO $pdo, string $clientId, string $responseType, string $redirectURI, array $scope, string $state):OperationResult {
        if ($responseType != 'code') return new OperationResult(ErrorType::INVALID_DATA,'Unsupported responseType parameter.');

        // Check if url is valid
        if (preg_match(self::$urlRegex,$redirectURI) == null) return new OperationResult(ErrorType::INVALID_DATA,'Invalid redirect_uri, not a valid link.');

        // Check scope
        $finalScope = new Set();
        foreach ($scope as $s) { $val = self::checkIfEnumBackedValue($s); if ($val !== null) $finalScope->add($val); }
        if (count($finalScope) < 1) return new OperationResult(ErrorType::INVALID_DATA, 'No valid scope provided.');
        $finalScope = $finalScope->toArray();
        $finalScope = self::trimScopeValues($finalScope);

        $finalScope = implode(' ',$finalScope);

        // Check if client registered
        $stmt = $pdo->prepare('SELECT * FROM oauth_clients WHERE client_id=?');
        $stmt->execute([$clientId]);
        $clientRow = $stmt->fetch();
        if ($clientRow === false) return new OperationResult(ErrorType::NOT_FOUND,'Client not found, verify client_id.');

        // Check if url registered
        $stmt = $pdo->prepare('SELECT * FROM oauth_clients_redirect_uris WHERE client_id=? AND redirect_uri=?');
        $stmt->execute([$clientId,preg_replace('/\?.*$/','',$redirectURI)]);
        if ($stmt->fetch() === false) return new OperationResult(ErrorType::NOT_FOUND,'Unregistered redirect_uri.');

        // Finish
        $authAttemptId = self::makeAuthorizationId($clientId);
        $redis = new LDRedis();
        $key = "oauth:clientAuthorization:$responseType:$authAttemptId";
        if (!$redis->hMSet($key,[
            'client_id' => $clientRow['client_id'],
            'client_name' => $clientRow['client_name'],
            'client_type' => $clientRow['client_type'],
            'website' => $clientRow['website'],
            'description' => $clientRow['description'],
            'logo' => $clientRow['logo'],
            'redirect_uri' => $redirectURI,
            'scope' => $finalScope,
            'state' => $state
        ])) return new OperationResult(ErrorType::UNKNOWN);
        $redis->expire($key,600);

        $fullId = $responseType.'_'.$authAttemptId;
        return new OperationResult(SuccessType::SUCCESS,"Client can be authorized.",[],['clientRow' => $clientRow, 'scope' => $finalScope, 'authId' => $fullId]);
    }

    public static function processAuthorizationRequest_code(LDPDO $pdo, LDRedis $redis, int $userId, string $authId, bool $allowed):OperationResult {
        $authRequest = self::getAuthorizationRequest($redis, $authId);
        if ($authRequest == null) return new OperationResult(ErrorType::NOT_FOUND,'Authorization request not found.');
        self::deleteAuthorizationRequest($redis,$authId);

        $redirectURI = $authRequest['redirect_uri'];
        $firstChar = strpos($redirectURI,'?') === false ? '?' : '&';
        if (!$allowed) {
            $stmt = $pdo->prepare("DELETE FROM oauth_access_tokens WHERE user_id=$userId AND client_id=?");
            $stmt->execute([$authRequest['client_id']]);

            $redirectURI .= $firstChar.'error=access_denied';
            if ($authRequest['state'] != null) $redirectURI .= '&state='.$authRequest['state'];
            return new OperationResult(SuccessType::SUCCESS,'Authorization denied.',[],['url' => $redirectURI, 'authRequest' => $authRequest]);
        } else {
            $redirectURI = $authRequest['redirect_uri'];
            $code = self::makeNewCode($authRequest['client_id']);
            if ($code == null) {
                $redirectURI .= $firstChar.'error=server_error';
                if ($authRequest['state'] != null) $redirectURI .= '&state='.$authRequest['state'];
                return new OperationResult(ErrorType::UNKNOWN,'Authorization failed.',[],['url' => $redirectURI, 'authRequest' => $authRequest]);
            }
            $redirectURI .= $firstChar.'code='.urlencode($code);
            $key = "oauth:authorizationCode:$code";
            $redis->hMSet($key,[
                'code' => $code,
                'client_id' => $authRequest['client_id'],
                'user_id' => $userId,
                'redirect_uri' => $authRequest['redirect_uri'],
                'scope' => $authRequest['scope'],
                'uses' => 0
            ]);
            $redis->expire($key,300);
            if ($authRequest['state'] != null) $redirectURI .= '&state='.$authRequest['state'];
            return new OperationResult(SuccessType::SUCCESS,'Authorization granted.',[],['url' => $redirectURI, 'authRequest' => $authRequest]);
        }
    }

    public static function processRefreshToken(LDPDO $pdo, string $clientId, string $clientSecret, string $refreshToken, ?string $scope) {
        $stmt = $pdo->prepare('SELECT * FROM oauth_clients WHERE client_id=? AND client_secret=? LIMIT 1');
        $stmt->execute([$clientId,$clientSecret]);
        $clientRow = $stmt->fetch();
        if ($clientRow === false) return new OperationResult(ErrorType::NOT_FOUND,'invalid_client: Client not found.',[],['body' => json_encode(['error' => 'invalid_client'])]);

        $stmt = $pdo->prepare('SELECT * FROM oauth_access_tokens WHERE client_id=? AND refresh_token=? LIMIT 1');
        $stmt->execute([$clientId,$refreshToken]);
        $tokenRow = $stmt->fetch();
        if ($tokenRow === false) return new OperationResult(ErrorType::INVALID_DATA,'invalid_grant: Invalid refresh token.',[],['body' => json_encode(['error' => 'invalid_grant'])]);

        if ($scope == null) $finalScope = $tokenRow['granted_scope'];
        else {
            $finalScope = new Set();
            $grantedScope = explode(' ',$tokenRow['granted_scope']);
            $newScope = explode(' ',$scope);
            foreach ($newScope as $s) { $val = self::checkIfEnumBackedValue($s); if ($val !== null && in_array($s,$grantedScope)) $finalScope->add($val); }
            if (count($finalScope) < 1) return new OperationResult(ErrorType::INVALID_DATA,'invalid_scope: No valid scope provided.',[],['body' => json_encode(['error' => 'invalid_scope'])]);
            $finalScope = $finalScope->toArray();

            $finalScope = self::trimScopeValues($finalScope);
            $finalScope = implode(' ',$finalScope);
        }

        // Make new tokens
        $aToken = self::makeNewTokens($pdo);
        if ($aToken->resultType instanceof ErrorType) return new OperationResult(ErrorType::UNKNOWN,'server_error: Something went wrong.',[],['body' => json_encode(['error' => 'server_error'])]);
        $accessToken = $aToken->data['access_token'];
        $refreshToken = $aToken->data['refresh_token'];
        $tokenType = $aToken->data['token_type'];
        $expiresIn = $aToken->data['expires_in'];
        $expirationDate = $aToken->data['expirationDate'];

        $stmt = $pdo->prepare('UPDATE oauth_access_tokens SET refresh_token=?,access_token=?,expiration_date=?,token_type=?,scope=? WHERE client_id=? AND user_id=? LIMIT 1');
        $stmt->execute([$refreshToken,$accessToken,$expirationDate->format('Y-m-d H:i:s'),$tokenType,$finalScope,$clientId,$tokenRow['user_id']]);
        
        $body = json_encode([
            'access_token' => $accessToken,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => $finalScope
        ]);
        return new OperationResult(SuccessType::SUCCESS,null,[],['body' => $body]);
    }

    public static function requestAccessToken(LDPDO $pdo, LDRedis $redis, string $grantType, string $clientId, string $clientSecret, string $code, string $redirectURI):OperationResult {
        if ($grantType != 'authorization_code') return new OperationResult(ErrorType::INVALID_DATA,'unsupported_grant_type',[],['body' => json_encode(['error' => 'unsupported_grant_type'])]);

        // Check if client registered
        $stmt = $pdo->prepare('SELECT * FROM oauth_clients WHERE client_id=? AND client_secret=?');
        $stmt->execute([$clientId,$clientSecret]);
        $clientRow = $stmt->fetch();
        if ($clientRow === false) return new OperationResult(ErrorType::NOT_FOUND,'invalid_client: Client not found.',[],['body' => json_encode(['error' => 'invalid_client'])]);

        $key = "oauth:authorizationCode:$code";

        // Check if code is for the client
        $codeData = $redis->redis->hGetAll($key);
        if ($codeData == false) return new OperationResult(ErrorType::NOT_FOUND,'invalid_grant: Authorization code not found.',[],['body' => json_encode(['error' => 'invalid_grant'])]);
        if ($codeData['client_id'] !== $clientId) return new OperationResult(ErrorType::INVALID_DATA,'invalid_grant: Authorization code not found.',[],['body' => json_encode(['error' => 'invalid_grant'])]);

        // Check code usage
        $res = $redis->redis->hIncrBy($key,'uses',1);
        if ($res == false) return new OperationResult(ErrorType::NOT_FOUND,'invalid_grant: Authorization code not found.',[],['body' => json_encode(['error' => 'invalid_grant'])]);
        else if ($res != 1) {
            $stmt = $pdo->prepare('DELETE FROM oauth_access_tokens WHERE client_id=? AND associated_code=?');
            $stmt->execute([$clientId,$code]);
            return new OperationResult(ErrorType::LIMIT_REACHED,'invalid_grant: Authorization code has already been used.',[],['body' => json_encode(['error' => 'invalid_grant'])]);
        }

        // Check redirectURI
        if ($redirectURI !== $codeData['redirect_uri']) return new OperationResult(ErrorType::INVALID_DATA,'invalid_grant: Invalid redirect_uri.',[],['body' => json_encode(['error' => 'invalid_grant'])]);

        // All good
        $aToken = self::makeNewTokens($pdo);
        if ($aToken->resultType instanceof ErrorType) return new OperationResult(ErrorType::UNKNOWN,'server_error: Something went wrong.',[],['body' => json_encode(['error' => 'server_error'])]);
        $accessToken = $aToken->data['access_token'];
        $refreshToken = $aToken->data['refresh_token'];
        $tokenType = $aToken->data['token_type'];
        $expiresIn = $aToken->data['expires_in'];
        $expirationDate = $aToken->data['expirationDate'];

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO oauth_access_tokens(client_id,user_id,granted_scope,scope,token_type,refresh_token,access_token,expiration_date,associated_code) VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE granted_scope=VALUES(granted_scope),scope=VALUES(scope),token_type=VALUES(token_type),refresh_token=VALUES(refresh_token),access_token=VALUES(access_token),expiration_date=VALUES(expiration_date),associated_code=VALUES(associated_code)
            SQL
        );
        $stmt->execute([$clientId,$codeData['user_id'],$codeData['scope'],$codeData['scope'],$tokenType,$refreshToken,$accessToken,$expirationDate->format('Y-m-d H:i:s'),$code]);

        $body = json_encode([
            'access_token' => $accessToken,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => $codeData['scope']
        ]);
        return new OperationResult(SuccessType::SUCCESS,null,[],['body' => $body]);
    }

    public static function getAuthorizationRequest(LDRedis $redis, string $authId) {
        $authId = preg_replace('/^code_/','',$authId);
        $key = "oauth:clientAuthorization:code:$authId";
        $authRequest = $redis->redis->hGetAll($key);
        if ($authRequest == false || count($authRequest) == 0) return null;
        return $authRequest;
    }

    public static function deleteAuthorizationRequest(LDRedis $redis, string $authId) {
        $authId = preg_replace('/^code_/','',$authId);
        return $redis->redis->del("oauth:clientAuthorization:code:$authId");
    }

    public static function makeAuthorizationId(string $clientId, ?string $responseType=null) {
        $authAttemptId = $clientId.'_'.microtime(true).'_'.bin2hex(random_bytes(10));
        if ($responseType != null) $authAttemptId = $responseType.'_'.$authAttemptId;
        return $authAttemptId;
    }

    public static function makeNewCode(string $clientId) {
        return $clientId.'_'.microtime(true).'_'.bin2hex(random_bytes(25));
    }

    public static function makeNewTokens(LDPDO $pdo) {
        $accessToken = bin2hex(random_bytes(50));
        $refreshToken = bin2hex(random_bytes(50));
        for ($i=0; $i<50; $i++) {
            $redo = $pdo->query("SELECT 1 FROM oauth_access_tokens WHERE access_token='$accessToken' OR refresh_token='$refreshToken'")->fetch() !== false;
            if (!$redo) break;
            if ($i == 49) {
                Logger::log(LogLevel::ERROR, 'OAuth', 'Failed to generate unique access_token and refresh_token.');
                return new OperationResult(ErrorType::UNKNOWN,'server_error: Something went wrong.',[],['body' => json_encode(['error' => 'server_error'])]);
            }
            $accessToken = bin2hex(random_bytes(50));
            $refreshToken = bin2hex(random_bytes(50));
        }
        $tokenType = 'Bearer';
        $expiresIn = 86400; // A day
        $expirationDate = new \DateTime();
        $expirationDate->setTimestamp(time()+$expiresIn);

        return new OperationResult(SuccessType::SUCCESS,null,[],[
            'access_token' => $accessToken,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'expirationDate' => $expirationDate
        ]);
    }

    public static function trimScopeValues(array $scope) {
        if (in_array('user',$scope)) foreach ($scope as $k => $v) if (preg_match('/^user:/',$v) > 0) unset($scope[$k]);
        return $scope;
    }

    private static function checkIfEnumBackedValue(string $sCase) {
        try {
            foreach (self::$scopesEnum->getCases() as $case)  if ($case->getValue()->value == $sCase) return $sCase;
        } catch (\Exception $e) {
            return null;
        }
    }
}
OAuth::init();
?>