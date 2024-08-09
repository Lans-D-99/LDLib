<?php
namespace LDLib\Cache;

use LDLib\Context\IWSContext;
use LDLib\Database\LDPDO;

class LocalCache {
    public static string $cache;

    public static bool $isHTTPCache = false;
    public static bool $isWSCache = false;

    public static function init() {
        return self::$cache::{'init'}();
    }

    public static function init2() {
        return self::$cache::{'init2'}();
    }

    public static function wsInit() {
        return self::$cache::{'wsInit'}();
    }

    public static function bindCache(string $className) {
        $c = new \ReflectionClass($className);
        self::$isHTTPCache = $c->implementsInterface(IHTTPCache::class);
        self::$isWSCache = $c->implementsInterface(IWSCache::class);
        self::$cache = $className;
    }

    public static function storeConnInfo(\Swoole\WebSocket\Server $server, \Swoole\HTTP\Request $request) {
        return self::$cache::{'storeConnInfo'}($server,$request);
    }

    public static function removeConnInfo(int $fd) {
        return self::$cache::{'removeConnInfo'}($fd);
    }

    public static function getConnInfos() {
        return self::$cache::{'getConnInfos'}();
    }

    public static function storeSubscription(IWSContext $context, string $json) {
        return self::$cache::{'storeSubscription'}($context,$json);
    }

    public static function removeSubscription(IWSContext $context, string $subName, mixed $subData) {
        return self::$cache::{'removeSubscription'}($context,$subName,$subData);
    }

    public static function exec(LDPDO $pdo, LDRedis $redis) {
        return self::$cache::{'exec'}($pdo,$redis);
    }
}

interface IHTTPCache {
    public static function init();
    public static function init2();
    public static function exec(LDPDO $pdo, LDRedis $redis);
}

interface IWSCache extends IHTTPCache {
    public static function wsInit();
    public static function storeConnInfo(\Swoole\WebSocket\Server $server, \Swoole\HTTP\Request $request);
    public static function removeConnInfo(int $fd);
    public static function getConnInfos();
    public static function storeSubscription(IWSContext $context, string $json);
    public static function removeSubscription(IWSContext $context, string $subName, mixed $subData);
}
?>