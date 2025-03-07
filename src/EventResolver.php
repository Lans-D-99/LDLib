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
namespace LDLib\Event;

use LDLib\Cache\LDValkey;
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
        $valkey = (new LDValkey())->valkey;
        $i = null;
        while ($i !== 0) {
            $keys = $valkey->scan($i,$keyPattern,200);
            $aToRemove = [];
            foreach ($keys as $key) {
                $keyParts = explode(':',$key);
                $conns = DataFetcher::getConnInfos();
                foreach ($conns as $conn) {
                    if ($keyParts[2] == $conn['fd'] && $keyParts[3] == $conn['connect_time']) {
                        $onMatchFound($valkey->get($key),$conn);
                        continue 2;
                    }
                }
                $aToRemove[] = $key;
            }
            $valkey->del($aToRemove);
        }
        $valkey->close();
    }
}
?>