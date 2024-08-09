<?php
namespace BaseWebsite\Auth;

use LDLib\{ErrorType,TypedException,OperationResult,SuccessType};
use BaseWebsite\User\RegisteredUser;
use BaseWebsite\Cache\LocalCache;
use LDLib\Database\LDPDO;
use LDLib\User\User;
use BaseWebsite\Context\HTTPContext;

class Auth {
    public static function registerUser(HTTPContext $context, string $username, string $password):OperationResult {
        $pdo = $context->getLDPDO();
        $redis = $context->getLDRedis();

        $resValidation = RegisteredUser::validateUserInfos($pdo,$username,$password)->resultType;
        if ($resValidation instanceof ErrorType) return $resValidation;

        $stmt = $pdo->prepare("INSERT INTO users (name,password,registration_date) VALUES (?,?,?) RETURNING *");
        $stmt->execute([$username,Auth::cryptPassword($password),(new \DateTime('now'))->format('Y-m-d H:i:s')]);
        if ($stmt->rowCount() != 1) return new OperationResult(ErrorType::DATABASE_ERROR);

        $userRow = $stmt->fetch();
        LocalCache::storeUser($redis, $userRow, new \DateTime('now'));
        $user = RegisteredUser::initFromRow(LocalCache::getUser($redis,$userRow['id']));
        return new OperationResult(SuccessType::SUCCESS, 'Successfully registered.', [$user->id], [$user]);
    }

    public static function loginUser(HTTPContext $context, string $name, string $pwd, bool $rememberMe):OperationResult {
        $conn = $context->getLDPDO();
        $now = new \DateTime('now');
        $sNow = $now->format('Y-m-d H:i:s');
        $appId = $context->request->header['user-agent'];
        $remoteAddress = $context->server->getClientInfo($context->request->fd)['remote_ip'];

        $registerAttempt = function(?int $userId, bool $successful, ?string $errType) use($conn,$appId,$sNow,$remoteAddress) {
            $stmt = $conn->prepare("INSERT INTO sec_connection_attempts (user_id,app_id,remote_address,date,successful,error_type) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$userId,$appId,$remoteAddress,$sNow,(int)$successful,$errType]);
        };

        if (isset($context->request->header['sid'])) return new OperationResult(ErrorType::CONTEXT_INVALID, 'A user is already authenticated.');

        if ($conn->query("SELECT COUNT(*) FROM sec_connection_attempts WHERE DATE(date)=DATE('$sNow') AND successful=0")->fetch(\PDO::FETCH_NUM)[0] >= $_SERVER['LD_SEC_MAX_CONNECTION_ATTEMPTS'])
            return new OperationResult(ErrorType::PROHIBITED, 'Too many failed connection attempts for today.');

        // Check name+pwd
        if (preg_match(User::$usernameRegex,$name) == 0) return new OperationResult(ErrorType::INVALID_DATA, "The username contains invalid characters.");
        $stmt = $conn->prepare("SELECT * FROM users WHERE name=? AND password=? LIMIT 1");
        $stmt->execute([$name,Auth::cryptPassword($pwd)]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($userRow == false) { $registerAttempt(null,false,ErrorType::NOT_FOUND->name); return new OperationResult(ErrorType::NOT_FOUND, 'User not found. Verify name and password.'); }

        // Check if banned
        $banRow = $conn->query("SELECT * FROM sec_users_bans WHERE user_id={$userRow['id']} AND start_date<='$sNow' AND end_date>'$sNow' LIMIT 1")->fetch();
        if ($banRow !== false) return new OperationResult(ErrorType::PROHIBITED, 'User is banned until '.$banRow['end_date'].' (UTC+0).');

        // Generate session id and register connection
        $sid = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO connections (user_id,session_id,app_id,created_at,last_activity_at) VALUES(?,?,?,?,?);");
        if ($stmt->execute([$userRow['id'],$sid,$appId,$sNow,$sNow]) === false) return new OperationResult(ErrorType::DATABASE_ERROR);

        $registerAttempt($userRow['id'],true,null);

        // All good, create cookie
        $time = $rememberMe ? time()+(60*60*24*120) : 0;
        $context->response->cookie('sid',$sid,$time,'/',$_SERVER['LD_LINK_DOMAIN'],true,true,'Lax','High');

        $redis = $context->getLDRedis();
        LocalCache::storeUser($redis,$userRow,$now);
        $user = RegisteredUser::initFromRow(LocalCache::getUser($redis,$userRow['id']));
        return new OperationResult(SuccessType::SUCCESS, 'User successfully logged in.', [$user->id], [$user]);
    }

    public static function logoutUser(HTTPContext $context):OperationResult {
        $context->deleteSidCookie();
        $stmt = $context->getLDPDO()->prepare("DELETE FROM connections WHERE session_id=?");
        $stmt->execute([$context->request->cookie['sid']]);
        return new OperationResult(SuccessType::SUCCESS, 'User successfully logged out.');
    }

    public static function changePassword(LDPDO $pdo, int $userId, string $oldPassword, string $newPassword):OperationResult {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=? AND password=? LIMIT 1");
        $stmt->execute([$userId,Auth::cryptPassword($oldPassword)]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($userRow == false) return new OperationResult(ErrorType::INVALID_DATA, 'Old password is invalid or user not found.');

        $resValidation = RegisteredUser::validateUserInfos($pdo,null,$newPassword);
        if ($resValidation->resultType instanceof ErrorType) return $resValidation;
        
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=? LIMIT 1");
        $stmt->execute([Auth::cryptPassword($newPassword),$userId]);
        return new OperationResult(SuccessType::SUCCESS, 'Password successfully changed.');
    }

    public static function logoutUserFromEverything(LDPDO $pdo, int $userId):OperationResult {
        $c = $pdo->query("DELETE FROM connections WHERE user_id=$userId")->rowCount();
        return new OperationResult(SuccessType::SUCCESS, "Terminated $c sessions.");
    }

    public static function cryptPassword(string $pwd, ?array &$fullString=null):string {
        $sCrypt = $_SERVER['LD_CRYPT_PASSWORD'];
        $res = preg_match('/^(.{28})(.{32})$/',crypt($pwd, $sCrypt),$m);
        $fullString = $pwd;
        if ($res === false || $res === 0) throw new TypedException("Password encryption failure.", ErrorType::INVALID_DATA);
        return $m[2];
    }
}
?>