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
namespace LDLib\DataFetcher;

use LDLib\Context\IWSContext;
use LDLib\Database\LDPDO;
use LDLib\Cache\LDValkey;

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

    public static function exec(LDPDO $pdo, LDValkey $valkey) {
        return self::$fetcher::{'exec'}($pdo,$valkey);
    }
}

interface IHTTPDataFetcher {
    public static function init();
    public static function init2();
    public static function exec(LDPDO $pdo, LDValkey $valkey);
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