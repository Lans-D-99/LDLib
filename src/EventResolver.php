<?php
namespace LDLib\Event;

use LDLib\Cache\LDRedis;
use LDLib\DataFetcher\DataFetcher;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;

class EventResolver {
    public static ?\Closure $eventResolver = null;

    public static function init(\Closure $eventResolver) {
        self::$eventResolver = $eventResolver;
    }

    public static function resolveEvent(string $eventName, mixed $eventData=null):bool {
        Logger::log(LogLevel::TRACE,"Event Resolver", "Resolving event : $eventName");

        $res = self::$eventResolver?->__invoke($eventName,$eventData);
        if (is_bool($res)) return $res;

        Logger::log(LogLevel::ERROR,"Event Resolver", "Mismanaged event : $eventName");
        return false;
    }

    public static function connIteration(string $keyPattern, callable $onMatchFound) {
        $redis = (new LDRedis())->redis;
        $i = null;
        while ($i !== 0) {
            $keys = $redis->scan($i,$keyPattern,200);
            $aToRemove = [];
            foreach ($keys as $key) {
                $keyParts = explode(':',$key);
                $conns = DataFetcher::getConnInfos();
                foreach ($conns as $conn) {
                    if ($keyParts[2] == $conn['fd'] && $keyParts[3] == $conn['connect_time']) {
                        $onMatchFound($redis->get($key),$conn);
                        continue 2;
                    }
                }
                $aToRemove[] = $key;
            }
            $redis->del($aToRemove);
        }
        $redis->close();
    }
}
?>