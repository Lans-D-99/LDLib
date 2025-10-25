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

use Ds\Map;
use GraphQL\Type\Definition\ResolveInfo;
use LDLib\Cache\LDValkey;
use LDLib\User\User;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WSServer;
use LDLib\Database\LDPDO;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Server\WorkerContext;
use LDLib\User\IIdentifiableUser;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;


interface IPDOImplContext {
    public function getLDPDO():LDPDO|false;
}

interface IValkeyImplContext {
    public function getLDValkey():LDValkey|false;
}

interface IUserImplContext {
    public function getAuthenticatedUser():?IIdentifiableUser;
}

interface IGraphQLImplContext extends IPDOImplContext,IValkeyImplContext,IUserImplContext {
    public function getGraphQLPathTimes():array;
    public function logQueryPathTime(array $a, ?float $time=null);
    public function checkConnectionsLeak();
}

interface IHTTPContext {
    public function __construct(Server $server, Request $request, ?Response $response = null);
    public function deleteUploadedFiles();
    public function addServerTimingData(string $s);
    public function getRealRemoteAddress();
}

interface IWSContext extends IValkeyImplContext {
    public function __construct(WSServer $server, Frame $frame);
    public function getConnInfo();
    public function getSubscriptionRequest():?SubscriptionRequest;
}

interface IOAuthContext { }

abstract class Context implements IPDOImplContext, IValkeyImplContext, IUserImplContext, IGraphQLImplContext {
    public ?IIdentifiableUser $authenticatedUser = null;
    public ?User $asUser = null;
    public array $logs = [];

    public int $dbcost = 0;
    public int $nValkeyGet = 0;
    public int $nValkeySet = 0;

    public array $gqlPathTimes = [];

    public Map $pdoConnsTraces;
    public Map $valkeyConnsTraces;

    public function __construct(public Server|WSServer $server) {
        $this->pdoConnsTraces = new Map();
        $this->valkeyConnsTraces = new Map();
    }

    public function addLog(string $name, string $msg) {
        $this->logs[] = "$name: $msg";
    }

    public function getLDPDO($dontTrack=false):LDPDO|false {
        $conn = WorkerContext::$pdoConnectionPool->get($this);
        if ($_SERVER['LD_TRACK_CONNECTIONS'] === '1' && $conn !== false && !$dontTrack) {
            $a = $this->pdoConnsTraces->get($conn->instanceId,'');
            if ($a !== '') throw new \Exception('pdoConnsTraces PDO ??????');
            $a = [ 'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4) ];
            $this->pdoConnsTraces->put($conn->instanceId,$a);
        }
        return $conn;
    }

    public function getLDValkey($dontTrack=false):LDValkey|false {
        $conn = WorkerContext::$valkeyConnectionPool->get($this);
        if ($_SERVER['LD_TRACK_CONNECTIONS'] === '1' && $conn !== false && !$dontTrack) {
            $a = $this->valkeyConnsTraces->get($conn->instanceId,'');
            if ($a !== '') throw new \Exception('valkeyConnsTraces PDO ??????');
            $a = [ 'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4) ];
            $this->valkeyConnsTraces->put($conn->instanceId,$a);
        }
        return $conn;
    }

    public function checkConnectionsLeak() {
        $nPDOLeaks = $this->pdoConnsTraces->count();
        $nValkeyLeaks = $this->valkeyConnsTraces->count();
        if ($nPDOLeaks > 0) { error_log('LEAK - '.print_r($this->pdoConnsTraces,true)); Logger::log(LogLevel::ERROR,'Context - Connections','PDO LEAKS: '.$nPDOLeaks); }
        if ($nValkeyLeaks > 0) { error_log('LEAK - '.print_r($this->valkeyConnsTraces,true)); Logger::log(LogLevel::ERROR,'Context - Connections','Valkey LEAKS: '.$nValkeyLeaks); }
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

class SubscriptionRequest {
    public array $metadata = [];

    public function __construct(public string $name, public mixed $data=null, public ?string $alias=null) { }

    public static function fromResolveInfo(ResolveInfo $ri, mixed $data=null) {
        return new self($ri->fieldName,$data,$ri->path[count($ri->path)-1]);
    }
}
?>