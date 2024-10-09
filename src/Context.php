<?php
namespace LDLib\Context;

use LDLib\Cache\LDRedis;
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
    public int $nRedisGet = 0;
    public int $nRedisSet = 0;

    public array $gqlPathTimes = [];

    public function __construct(public Server|WSServer $server) { }

    public function addLog(string $name, string $msg) {
        $this->logs[] = "$name: $msg";
    }

    public function getLDPDO():LDPDO|false {
        return WorkerContext::$pdoConnectionPool->get($this);
    }

    public function getLDRedis():LDRedis|false {
        return WorkerContext::$redisConnectionPool->get($this);
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
    public function getLDRedis():LDRedis|false;
    public function getAuthenticatedUser():?IIdentifiableUser;
    public function getGraphQLPathTimes():array;
    public function logQueryPathTime(array $a, ?float $time=null);
}

interface IHTTPContext extends IContext {
    public function __construct(Server $server, Request $request, ?Response $response = null);
    public function deleteUploadedFiles();
    public function addServerTimingData(string $s);
}

interface IWSContext extends IContext {
    public function __construct(WSServer $server, Frame $frame);
    public function getConnInfo();
    public function getSubscriptionRequest():?SubscriptionRequest;
    public function getLDRedis():LDRedis|false;
}

interface IOAuthContext extends IContext {

}

class SubscriptionRequest {
    public function __construct(public string $name, public mixed $data=null) { }
}
?>