<?php
namespace LDLib\DataFetcher;

use LDLib\Context\IWSContext;
use LDLib\Database\LDPDO;
use LDLib\Cache\LDRedis;

class DataFetcher {
    public static string $fetcher;

    public static bool $isHTTPDataFetcher = false;
    public static bool $isWSDataFetcher = false;

    public static function init() {
        return self::$fetcher::{'init'}();
    }

    public static function init2() {
        return self::$fetcher::{'init2'}();
    }

    public static function wsInit() {
        return self::$fetcher::{'wsInit'}();
    }

    public static function bindCache(string $className) {
        $c = new \ReflectionClass($className);
        self::$isHTTPDataFetcher = $c->implementsInterface(IHTTPDataFetcher::class);
        self::$isWSDataFetcher = $c->implementsInterface(IWSDataFetcher::class);
        self::$fetcher = $className;
    }

    public static function storeConnInfo(\Swoole\WebSocket\Server $server, \Swoole\HTTP\Request $request) {
        return self::$fetcher::{'storeConnInfo'}($server,$request);
    }

    public static function removeConnInfo(int $fd) {
        return self::$fetcher::{'removeConnInfo'}($fd);
    }

    public static function getConnInfos() {
        return self::$fetcher::{'getConnInfos'}();
    }

    public static function storeSubscription(IWSContext $context, string $json) {
        return self::$fetcher::{'storeSubscription'}($context,$json);
    }

    public static function removeSubscription(IWSContext $context, string $subName, mixed $subData) {
        return self::$fetcher::{'removeSubscription'}($context,$subName,$subData);
    }

    public static function exec(LDPDO $pdo, LDRedis $redis) {
        return self::$fetcher::{'exec'}($pdo,$redis);
    }
}

interface IHTTPDataFetcher {
    public static function init();
    public static function init2();
    public static function exec(LDPDO $pdo, LDRedis $redis);
}

interface IWSDataFetcher extends IHTTPDataFetcher {
    public static function wsInit();
    public static function storeConnInfo(\Swoole\WebSocket\Server $server, \Swoole\HTTP\Request $request);
    public static function removeConnInfo(int $fd);
    public static function getConnInfos();
    public static function storeSubscription(IWSContext $context, string $json);
    public static function removeSubscription(IWSContext $context, string $subName, mixed $subData);
}
?>