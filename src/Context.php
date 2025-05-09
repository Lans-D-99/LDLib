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
namespace LDLib\Context;

use LDLib\Cache\LDValkey;
use LDLib\User\User;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WSServer;
use LDLib\Database\LDPDO;
use LDLib\Server\WorkerContext;
use LDLib\User\IIdentifiableUser;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;

abstract class Context {
    public ?IIdentifiableUser $authenticatedUser = null;
    public ?User $asUser = null;
    public array $logs = [];

    public int $dbcost = 0;
    public int $nValkeyGet = 0;
    public int $nValkeySet = 0;

    public array $gqlPathTimes = [];

    public function __construct(public Server|WSServer $server) { }

    public function addLog(string $name, string $msg) {
        $this->logs[] = "$name: $msg";
    }

    public function getLDPDO():LDPDO|false {
        return WorkerContext::$pdoConnectionPool->get($this);
    }

    public function getLDValkey():LDValkey|false {
        return WorkerContext::$valkeyConnectionPool->get($this);
    }

    public function logQueryPathTime(array $path, ?float $time=null) {        
        $aSub =& $this->gqlPathTimes;
        foreach ($path as $key) {
            if (!array_key_exists($key,$aSub)) $aSub[$key] = [];
            $aSub =& $aSub[$key];
        }
        $aSub['__time__'] = $time === null ? (microtime(true)*1000) : $time;
    }

    public function getAuthenticatedUser():?IIdentifiableUser {
        return $this->authenticatedUser;
    }

    public function getGraphQLPathTimes():array {
        return $this->gqlPathTimes;
    }
}

interface IContext {
    public function getLDPDO():LDPDO|false;
    public function getLDValkey():LDValkey|false;
    public function getAuthenticatedUser():?IIdentifiableUser;
    public function getGraphQLPathTimes():array;
    public function logQueryPathTime(array $a, ?float $time=null);
}

interface IHTTPContext extends IContext {
    public function __construct(Server $server, Request $request, ?Response $response = null);
    public function deleteUploadedFiles();
    public function addServerTimingData(string $s);
    public function getRealRemoteAddress();
}

interface IWSContext extends IContext {
    public function __construct(WSServer $server, Frame $frame);
    public function getConnInfo();
    public function getSubscriptionRequest():?SubscriptionRequest;
    public function getLDValkey():LDValkey|false;
}

interface IOAuthContext extends IContext {

}

class SubscriptionRequest {
    public function __construct(public string $name, public mixed $data=null) { }
}
?>