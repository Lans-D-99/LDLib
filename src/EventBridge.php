<?php
namespace LDLib\Event;

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Timer;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;

use function Co\go;

class EventBridge {
    public int $reconnectTimerID = -1;
    public int $nConnectionRetry = 5;

    public WaitGroup $sendEventWG;
    public WaitGroup $connectWG;

    private int $lastConnectionFailLogged = 0;

    public function __construct(public Client $client, ?array $settings=null) {
        $this->sendEventWG = new WaitGroup();
        $this->connectWG = new WaitGroup();

        $client->set([
            'lowercase_header' => true,
            'socket_buffer_size' => 3 * 1024 * 1024,
            'open_tcp_nodelay' => true,
            // 'ssl_host_name' => 'xxx.com',
            // 'ssl_verify_peer' => true,
            // 'ssl_allow_self_signed' => true,

            'method' => 'GET',
            'reconnect' => 3,
            'timeout' => 3,
            'keep_alive' => true,
            'websocket_compression' => false,
            'http_compression' => false,
            'body_decompression' => true
        ]);
        if ($settings != null) $client->set($settings);
    }

    public function connect(bool $async=true) {
        if ($this->client->connected) { Logger::log(LogLevel::INFO, 'EventBridge', "EventBridge already connected."); return; }
        if ($this->connectWG->count() > 0) return;

        $wg = new WaitGroup(1);
        go(function() use($wg) {
            $this->connectWG->add();
            if (!$this->client->upgrade('/graphql.php?pass='.$_SERVER['LD_WEBSOCKET_PRIVATE_KEY'])) {
                if ($this->reconnectTimerID <= 0) {
                    if (time() - $this->lastConnectionFailLogged > 60) {
                        $this->lastConnectionFailLogged = time();
                        Logger::log(LogLevel::ERROR, 'EventBridge', "!!! Couldn't connect to websocket server. Retries every {$this->nConnectionRetry}s.");
                    }
                    $this->reconnectTimerID = (int)Timer::after($this->nConnectionRetry*1000,function() {
                        $this->reconnectTimerID = -1;
                        $this->connect();
                    });
                }
            }

            $this->connectWG->done();
            $wg->done();
        });
        if (!$async) $wg->wait();
    }

    public function sendEvent(string $eventName, mixed $data=null, int $ttl=3600*7) {
        Logger::log(LogLevel::TRACE, 'Event', 'Sent event : '.$eventName);
        $t = time();
        if (!$this->client->connected) $this->connect(false);
        if (!$this->client->connected) { $this->saveEvent($eventName,$data,new \DateTimeImmutable(date('Y-m-d H:i:s', $t+$ttl))); return; }
        if ($this->sendEventWG->count() > 0 && !$this->sendEventWG->wait(3)) { $this->saveEvent($eventName,$data,new \DateTimeImmutable(date('Y-m-d H:i:s', $t+$ttl))); return; }

        $this->sendEventWG->add();
        @$this->client->push(json_encode(["event" => $eventName, "data" => $data, "ttl" => $ttl]));
        $this->sendEventWG->done();
    }

    private function saveEvent(string $eventName, mixed $data, \DateTimeInterface $expirationDate) {
        Logger::log(LogLevel::TRACE, 'EventBridge', 'Saving an event in DB.');
        try {
            $conn = $this->getDBConn();
            $stmt = $conn->prepare('INSERT INTO events_to_send (name,data,date,expiration_date) VALUES (?,?,?,?)');
            $stmt->execute([$eventName,json_encode($data),(new \DateTime('now'))->format('Y-m-d H:i:s'),$expirationDate->format('Y-m-d H:i:s')]);
            $conn = null;
        } catch (\Throwable $e) {
            Logger::log(LogLevel::ERROR, 'EventBridge', "Couldn't save event in DB : $eventName");
            Logger::logThrowable($e);
        }
    }

    private function getDBConn():\PDO {
        $dbName = (bool)$_SERVER['LD_TEST'] ? $_SERVER['LD_TEST_DB_NAME'] : $_SERVER['LD_DB_NAME'];
        $conn = new \PDO("mysql:host={$_SERVER['LD_DB_HOST']};dbname={$dbName}", $_SERVER['LD_DB_USER'], $_SERVER['LD_DB_PWD']);
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $conn;
    }
}
?>