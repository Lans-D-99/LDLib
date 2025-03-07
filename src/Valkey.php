<?php
/*****************************************************************************
 * This file is part of LDLib and subject to the Version 2.0 of the          *
 * Apache License, you may not use this file except in compliance            *
 * with the License. You may obtain a copy of the License at :               *
 *                                                                           *
 *                http://www.apache.org/licenses/LICENSE-2.0                 *
 *                                                                           *
 * Unless required by applicable law or agreed to in writing, software       *
 * distributed under the License is distributed on an "AS IS" BASIS,         *
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  *
 * See the License for the specific language governing permissions and       *
 * limitations under the License.                                            *
 *                                                                           *
 *                Author: Lans.D <lans.d.99@protonmail.com>                  *
 *                                                                           *
 *****************************************************************************/
namespace LDLib\Cache;

use LDLib\Context\Context;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Server\WorkerContext;

class LDValkey {
    public ?\Redis $valkey = null;

    public int $getCount = 0;
    public int $setCount = 0;

    public function __construct(public ?Context $context=null) {
        $this->init();
    }

    public function get(string $key):mixed {
        $this->getCount++;
        if ($this->context != null) $this->context->nValkeyGet++;

        $v = $this->valkey->get($key);
        return $v === false ? null : $v;
    }

    public function set(string $key, string $value, mixed $options = null):bool|\Redis {
        $this->setCount++;
        if ($this->context != null) $this->context->nValkeySet++;

        return $this->valkey->set($key, $value, $options);
    }

    public function keys(string $pattern):array|false|\Redis {
        return $this->valkey->keys($pattern);
    }

    public function del(string|array $key):int|false|\Redis {
        return $this->valkey->del($key);
    }

    public function delM(string $pattern):int|\Redis {
        $keys = $this->keys($pattern);
        if (!is_array($keys)) return 0;
        return $this->del($keys);
    }

    public function hGet(string $key, string $hashKey) {
        $this->getCount++;
        if ($this->context != null) $this->context->nValkeyGet++;

        return $this->valkey->hGet($key, $hashKey);
    }

    public function hMGet(string $key, array $hashKeys) {
        $this->getCount += count($hashKeys);
        if ($this->context != null) $this->context->nValkeyGet += count($hashKeys);

        return $this->valkey->hMGet($key, $hashKeys);
    }

    public function hMSet(string $key, array $hashKeys) {
        $this->setCount += count($hashKeys);
        if ($this->context != null) $this->context->nValkeySet += count($hashKeys);

        return $this->valkey->hMSet($key, $hashKeys);
    }

    public function exists($key, ...$keys) {
        return $this->valkey->exists($key, ...$keys);
    }

    public function expire($key, $ttl) {
        return $this->valkey->expire($key,$ttl);
    }

    public function ensureConnectionIsAlive() {
        try {
            $this->valkey->ping();
        } catch (\Exception $e) {
            Logger::logThrowable($e);
            $this->init();
        }
    }

    public function toPool() {
        WorkerContext::$valkeyConnectionPool->put($this);
    }

    public function init() {
        $this->valkey = new \Redis();
        try {
            if ((bool)$_SERVER['LD_VALKEY_VERIFY_PEER_NAME'] === false)
                $res = $this->valkey->connect($_SERVER['LD_VALKEY_HOST'],$_SERVER['LD_VALKEY_HOST_PORT'],$_SERVER['LD_VALKEY_TIMEOUT'],null,0,0,['stream' => ['verify_peer_name' => false]]);
            else $res = $this->valkey->connect($_SERVER['LD_VALKEY_HOST'],$_SERVER['LD_VALKEY_HOST_PORT'],$_SERVER['LD_VALKEY_TIMEOUT']);
            if ($res == false) {
                $this->valkey = null;
                Logger::log(LogLevel::FATAL, 'Valkey','Valkey connection failure.');
            }
        } catch (\RedisException $e) {
            $this->valkey = null;
            Logger::log(LogLevel::FATAL, 'Valkey','Valkey connection failure: '.$e->getMessage());
        }
    }
}
?>