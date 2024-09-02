<?php
namespace LDLib\Cache;

use LDLib\Context\Context;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;

class LDRedis {
    public ?\Redis $redis = null;

    public int $getCount = 0;
    public int $setCount = 0;

    public function __construct(public ?Context $context=null) {
        $this->init();
    }

    public function get(string $key):mixed {
        $this->getCount++;
        if ($this->context != null) $this->context->nRedisGet++;

        $v = $this->redis->get($key);
        return $v === false ? null : $v;
    }

    public function set(string $key, string $value, mixed $options = null):bool|\Redis {
        $this->setCount++;
        if ($this->context != null) $this->context->nRedisSet++;

        return $this->redis->set($key, $value, $options);
    }

    public function keys(string $pattern):array|false|\Redis {
        return $this->redis->keys($pattern);
    }

    public function del(string|array $key):int|false|\Redis {
        return $this->redis->del($key);
    }

    public function delM(string $pattern):int|\Redis {
        $keys = $this->keys($pattern);
        if (!is_array($keys)) return 0;
        return $this->del($keys);
    }

    public function hGet(string $key, string $hashKey) {
        $this->getCount++;
        if ($this->context != null) $this->context->nRedisGet++;

        return $this->redis->hGet($key, $hashKey);
    }

    public function hMGet(string $key, array $hashKeys) {
        $this->getCount += count($hashKeys);
        if ($this->context != null) $this->context->nRedisGet += count($hashKeys);

        return $this->redis->hMGet($key, $hashKeys);
    }

    public function hMSet(string $key, array $hashKeys) {
        $this->setCount += count($hashKeys);
        if ($this->context != null) $this->context->nRedisSet += count($hashKeys);

        return $this->redis->hMSet($key, $hashKeys);
    }

    public function exists($key, ...$keys) {
        return $this->redis->exists($key, ...$keys);
    }

    public function expire($key, $ttl) {
        return $this->redis->expire($key,$ttl);
    }

    public function ensureConnectionIsAlive() {
        try {
            $this->redis->ping();
        } catch (\Exception $e) {
            Logger::logThrowable($e);
            $this->init();
        }
    }

    public function init() {
        $this->redis = new \Redis();
        try {
            if ((bool)$_SERVER['LD_REDIS_VERIFY_PEER_NAME'] === false)
                $res = $this->redis->connect($_SERVER['LD_REDIS_HOST'],$_SERVER['LD_REDIS_HOST_PORT'],$_SERVER['LD_REDIS_TIMEOUT'],null,0,0,['stream' => ['verify_peer_name' => false]]);
            else $res = $this->redis->connect($_SERVER['LD_REDIS_HOST'],$_SERVER['LD_REDIS_HOST_PORT'],$_SERVER['LD_REDIS_TIMEOUT']);
            if ($res == false) {
                $this->redis = null;
                Logger::log(LogLevel::FATAL, 'Redis','Redis connection failure.');
            }
        } catch (\RedisException $e) {
            $this->redis = null;
            Logger::log(LogLevel::FATAL, 'Redis','Redis connection failure: '.$e->getMessage());
        }
    }
}
?>