<?php
namespace LDLib\Server;

use LDLib\Cache\LDRedis;
use LDLib\ConnectionPool;
use LDLib\Context\Context;
use LDLib\Database\LDPDO;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;

class WorkerContext {
    public static ConnectionPool $pdoConnectionPool;
    public static ConnectionPool $redisConnectionPool;
    public static array $pdoPoolCapacities = [10,30,50];
    public static array $redisPoolCapacities = [10,30,50];
    public static bool $initialized = false;

    public static function init() {
        //! should move stuff in HTTPServer here
        if (WorkerContext::$initialized) return;
        self::$pdoPoolCapacities = explode(',',$_SERVER['LD_DB_POOL_SIZE']??'10,30,50');
        self::$redisPoolCapacities = explode(',',$_SERVER['LD_REDIS_POOL_SIZE']??'10,30,50');
        self::$pdoConnectionPool = new ConnectionPool(fn() => new LDPDO(),self::$pdoPoolCapacities,fn(LDPDO $pdo, ?Context $context=null) => $pdo->context = $context);
        self::$redisConnectionPool = new ConnectionPool(fn() => new LDRedis(),self::$redisPoolCapacities,fn(LDRedis $redis, ?Context $context=null) => $redis->context = $context);
        self::$initialized = true;
    }

    // public static function waitForFilledPoolSize() {
    //     $error = false;
    //     $n = 0;
    //     while (!self::$pdoConnectionPool->isAtCapacity()) {
    //         if ($n > 100) { $error = true; break; }
    //         usleep(100000);
    //         $n++;
    //     }
    //     $n = 0;
    //     while ($error != true && self::$redisConnectionPool->isAtCapacity()) {
    //         if ($n > 100) { $error = true; break; }
    //         usleep(100000);
    //         $n++;
    //     }
    //     if ($error) {
    //         $nPDOExpect = self::$pdoConnectionPool->getCapacity();
    //         $nRedisExpect = self::$redisConnectionPool->getCapacity();
    //         $nPDO = self::$pdoConnectionPool->pool->length();
    //         $nRedis = self::$redisConnectionPool->pool->length();
    //         $msg = "Pool size is wrong. (expected: PDO=$nPDOExpect and Redis=$nRedisExpect but got PDO=$nPDO and Redis=$nRedis)";
    //         Logger::log(LogLevel::ERROR, 'ConnectionPool', $msg);
    //         self::$pdoConnectionPool->fill();
    //         self::$redisConnectionPool->fill();
    //         if ($_SERVER['LD_TEST'] === '1') throw new \Exception($msg);
    //     }
    // }
}
?>