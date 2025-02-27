<?php
namespace LDLib\Server;

use LDLib\Cache\LDValkey;
use LDLib\ConnectionPool;
use LDLib\Context\Context;
use LDLib\Database\LDPDO;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;

class WorkerContext {
    public static ConnectionPool $pdoConnectionPool;
    public static ConnectionPool $valkeyConnectionPool;
    public static array $pdoPoolCapacities = [10,30,50];
    public static array $valkeyPoolCapacities = [10,30,50];
    public static bool $initialized = false;

    public static function init() {
        //! should move stuff in HTTPServer here
        if (WorkerContext::$initialized) return;
        self::$pdoPoolCapacities = explode(',',$_SERVER['LD_DB_POOL_SIZE']??'10,30,50');
        self::$valkeyPoolCapacities = explode(',',$_SERVER['LD_VALKEY_POOL_SIZE']??'10,30,50');
        self::$pdoConnectionPool = new ConnectionPool(fn() => new LDPDO(),self::$pdoPoolCapacities,fn(LDPDO $pdo, ?Context $context=null) => $pdo->context = $context);
        self::$valkeyConnectionPool = new ConnectionPool(fn() => new LDValkey(),self::$valkeyPoolCapacities,fn(LDValkey $valkey, ?Context $context=null) => $valkey->context = $context);
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
    //     while ($error != true && self::$valkeyConnectionPool->isAtCapacity()) {
    //         if ($n > 100) { $error = true; break; }
    //         usleep(100000);
    //         $n++;
    //     }
    //     if ($error) {
    //         $nPDOExpect = self::$pdoConnectionPool->getCapacity();
    //         $nValkeyExpect = self::$valkeyConnectionPool->getCapacity();
    //         $nPDO = self::$pdoConnectionPool->pool->length();
    //         $nValkey = self::$valkeyConnectionPool->pool->length();
    //         $msg = "Pool size is wrong. (expected: PDO=$nPDOExpect and Valkey=$nValkeyExpect but got PDO=$nPDO and Valkey=$nValkey)";
    //         Logger::log(LogLevel::ERROR, 'ConnectionPool', $msg);
    //         self::$pdoConnectionPool->fill();
    //         self::$valkeyConnectionPool->fill();
    //         if ($_SERVER['LD_TEST'] === '1') throw new \Exception($msg);
    //     }
    // }
}
?>