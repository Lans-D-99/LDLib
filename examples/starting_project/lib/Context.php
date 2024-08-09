<?php
namespace BaseWebsite\Context;

use Swoole\Http\{Server, Request, Response};
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WSServer;
use LDLib\Context\Context;
use LDLib\Context\IHTTPContext;
use LDLib\Context\IOAuthContext;
use LDLib\Context\IWSContext;
use LDLib\Context\SubscriptionRequest;
use LDLib\Security\Security;
use LDLib\Utils\Utils;
use BaseWebsite\Cache\LocalCache;
use BaseWebsite\User\RegisteredUser;

class HTTPContext extends Context implements IHTTPContext {
    public string $sServerTiming = '';

    public function __construct(Server $server, public Request $request, public ?Response $response = null) {
        parent::__construct($server);

        $sNow = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $this->a = [
            'authenticatedUser' => null
        ];

        if (isset($request->cookie['sid'])) {
            $conn = $this->getLDPDO();
            $redis = $this->getLDRedis();
            $sid = $request->cookie['sid'];
            $remoteAddr = Utils::getRealRemoteAddress($request);

            $stmt = $conn->prepare('SELECT * FROM connections WHERE session_id=? LIMIT 1');
            $stmt->execute([$sid]);
            $row = $stmt->fetch();
            if ($row !== false) {
                LocalCache::prepUser($redis,$row['user_id']);
                LocalCache::exec($conn,$redis);
                $rowUser = LocalCache::getUser($redis,$row['user_id'],$this);
                $stmt = $conn->prepare("UPDATE connections SET last_activity_at=? WHERE session_id=?");
                $stmt->execute([$sNow,$sid]);
                $this->a['authenticatedUser'] = RegisteredUser::initFromRow($rowUser);
            } else {
                $stmt = $conn->prepare('INSERT IGNORE INTO sec_wrong_sids (remote_address,date,session_id) VALUES(?,?,?)');
                $stmt->execute([$remoteAddr,$sNow,$request->cookie['sid']]);
                Security::uncacheIPBan($remoteAddr,$redis);
                $this->deleteSidCookie();
            }
        }

        $this->closeConnections();
    }

    public function deleteSidCookie():bool {
        return $this->response->cookie('sid','',time()-1000000,'/',$_SERVER['LD_LINK_DOMAIN']);
    }

    public function deleteUploadedFiles() {
        if (isset($this->request->files)) foreach ($this->request->files as $f) { @unlink($f['tmp_name']); }
    }

    public function addServerTimingData(string $s) {
        if ($this->sServerTiming != null) $this->sServerTiming .= ', ';
        $this->sServerTiming .= $s;
    }
}

class WSContext extends Context implements IWSContext {
    public array $connInfo;
    public ?SubscriptionRequest $subRequest = null;

    public function __construct(WSServer $server, public Frame $frame) {
        parent::__construct($server);
        $this->connInfo = LocalCache::getConnInfo($frame->fd);

        $sNow = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $this->a = [
            'authenticatedUser' => null
        ];
        $sid = $this?->connInfo['sid']??null;

        if (isset($sid)) {
            $conn = $this->getLDPDO();
            $redis = $this->getLDRedis();

            $stmt = $conn->prepare('SELECT * FROM connections WHERE session_id=? LIMIT 1');
            $stmt->execute([$sid]);
            $row = $stmt->fetch();
            if ($row !== false) {
                LocalCache::prepUser($redis,$row['user_id']);
                LocalCache::exec($conn,$redis);
                $rowUser = LocalCache::getUser($redis,$row['user_id']);
                $stmt = $conn->prepare("UPDATE connections SET last_activity_at=? WHERE session_id=?");
                $stmt->execute([$sNow,$sid]);
                $this->a['authenticatedUser'] = RegisteredUser::initFromRow($rowUser);
            }
        }

        $this->closeConnections();
    }

    public function isEventTriggerer():bool {
        return $this->connInfo['is_event_triggerer'] === 1;
    }

    public function getConnInfo() { return $this->connInfo; }
    public function getSubscriptionRequest():?SubscriptionRequest { return $this->subRequest; }
}

class OAuthContext extends HTTPContext implements IOAuthContext {
    public array $scope = [];

    public function __construct(Server $server, public Request $request, public ?Response $response = null) {
        $now = new \DateTime('now');
        $this->a = [
            'authenticatedUser' => null
        ];

        $authHeader = $request->header['authorization']??'';
        if (preg_match('/^Bearer (.+)$/',$authHeader,$m) == 0) return;
        $code = $m[1];

        $conn = $this->getLDPDO();
        $stmt = $conn->prepare('SELECT * FROM oauth_access_tokens WHERE access_token=? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if ($row == false) return;

        if (new \DateTime($row['expiration_date']) <= $now) return;
        $this->scope = explode(' ',$row['scope']);

        $redis = $this->getLDRedis();
        LocalCache::prepUser($redis,$row['user_id']);
        LocalCache::exec($conn,$redis);
        $rowUser = LocalCache::getUser($redis,$row['user_id'],$this);
        $this->asUser = RegisteredUser::initFromRow($rowUser);

        $this->closeConnections();
    }
}
?>