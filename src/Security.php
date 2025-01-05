<?php
namespace LDLib\Security;

use LDLib\Cache\LDRedis;
use LDLib\Database\LDPDO;
use LDLib\OperationResult;
use LDLib\SuccessType;

class Security {
    public static function isRequestsLimitReached(string $remoteAddress, ?LDPDO $pdo=null, ?LDRedis $redis=null):bool {
        $autoCloseRedis = $redis === null;
        $autoClosePDO = $pdo === null;

        $redis ??= new LDRedis();
        $key = "security:requestslimit:anonymous:$remoteAddress";
        $val = $redis->get($key);
        $limitReached = false;
    
        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_total_requests WHERE remote_address=? AND count>?');
            $stmt->execute([$remoteAddress,(int)$_SERVER['LD_SEC_BASE_REQUESTS_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $redis->set($key,$limitReached ? '1' : '0',['EX' => 5]);
        }

        if ($autoCloseRedis) $redis->redis->close();
        return $limitReached;
    }

    public static function isRequestsLimitReached_Users(int $userId, ?LDPDO $pdo=null, ?LDRedis $redis=null):bool {
        $autoCloseRedis = $redis === null;
        $autoClosePDO = $pdo === null;

        $redis ??= new LDRedis();
        $key = "security:requestslimit:users:$userId";
        $val = $redis->get($key);
        $limitReached = false;
    
        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_users_total_requests WHERE user_id=? AND count>?');
            $stmt->execute([$userId,(int)$_SERVER['LD_SEC_USERS_REQUESTS_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $redis->set($key,$limitReached ? '1' : '0',['EX' => 300]);
        }

        if ($autoCloseRedis) $redis->redis->close();
        return $limitReached;
    }

    public static function isQueryComplexityLimitReached(string $remoteAddress, ?LDPDO $pdo=null, ?LDRedis $redis=null):bool {
        $autoCloseRedis = $redis === null;
        $autoClosePDO = $pdo === null;

        $redis ??= new LDRedis();
        $key = "security:apilimit:anonymous:$remoteAddress";
        $val = $redis->get($key);
        $limitReached = false;
    
        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_query_complexity_usage WHERE remote_address=? AND complexity_used>?');
            $stmt->execute([$remoteAddress,(int)$_SERVER['LD_SEC_BASE_QUERY_COMP_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $redis->set($key,$limitReached ? '1' : '0',['EX' => 5]);
        }

        if ($autoCloseRedis) $redis->redis->close();
        return $limitReached;
    }

    public static function isQueryComplexityLimitReached_Users(int $userId, ?LDPDO $pdo=null, ?LDRedis $redis=null):bool {
        $autoCloseRedis = $redis === null;
        $autoClosePDO = $pdo === null;

        $redis ??= new LDRedis();
        $key = "security:apilimit:users:$userId";
        $val = $redis->get($key);
        $limitReached = false;
    
        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_users_query_complexity_usage WHERE user_id=? AND complexity_used>?');
            $stmt->execute([$userId,(int)$_SERVER['LD_SEC_USERS_QUERY_COMP_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $redis->set($key,$limitReached ? '1' : '0',['EX' => 60]);
        }

        if ($autoCloseRedis) $redis->redis->close();
        return $limitReached;
    }

    public static function isIPBanned(string $remoteAddress, ?LDPDO $pdo=null, ?LDRedis $redis=null):bool {
        $autoCloseRedis = $redis === null;
        $autoClosePDO = $pdo === null;

        $redis ??= new LDRedis();
        $key = "security:ip_bans:$remoteAddress";
        $val = $redis->get($key);
        $banned = false;
    
        if ($val === '1') $banned = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_ip_bans WHERE remote_address=? LIMIT 1');
            $stmt->execute([$remoteAddress]);
            $banned = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $redis->set($key,$banned ? '1' : '0',['EX' => $banned ? 86400*7 : 3600*4]);
        }

        if ($autoCloseRedis) $redis->redis->close();
        return $banned;
    }

    public static function uncacheIPBan(string $remoteAddress, ?LDRedis $redis=null) {
        $autoCloseRedis = $redis === null;
        $redis ??= new LDRedis();
        $res = $redis->del("security:ip_bans:$remoteAddress");
        if ($autoCloseRedis) $redis->redis->close();
        return $res;
    }

    public static function reportSusIP(string $remoteAddress, int $points, string $reason, ?LDPDO $pdo=null):OperationResult {
        $autoClosePDO = $pdo === null;
        $pdo ??= new LDPDO();

        $stmt = $pdo->prepare('INSERT INTO sec_sus_ip(remote_address,date,points,reason) VALUES (?,?,?,?)');
        $stmt->execute([$remoteAddress,(new \DateTime('now'))->format('Y-m-d H:i:s'),$points,$reason]);
        if ($autoClosePDO) $pdo->close();
        return new OperationResult(SuccessType::SUCCESS);
    }
}
?>