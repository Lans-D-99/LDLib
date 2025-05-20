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
        self::$pdoConnectionPool = new ConnectionPool(fn() => new LDPDO(),self::$pdoPoolCapacities,fn(LDPDO $pdo, ?Context $context=null) => $pdo->context = $context, 'PDOPool');
        self::$valkeyConnectionPool = new ConnectionPool(fn() => new LDValkey(),self::$valkeyPoolCapacities,fn(LDValkey $valkey, ?Context $context=null) => $valkey->context = $context, 'ValkeyPool');
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