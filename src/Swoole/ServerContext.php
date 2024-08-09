<?php
namespace LDLib\Server;

use Swoole\Table;

class ServerContext {
    public static Table $workerDatas;
    // public static \Swoole\Http\Server|\Swoole\WebSocket\Server $server;

    public static function init() {
        self::$workerDatas = new Table(64);
        self::$workerDatas->column('workerId',Table::TYPE_INT);
        self::$workerDatas->column('nRequests',Table::TYPE_INT);
        self::$workerDatas->column('mem_usage',Table::TYPE_INT);
        self::$workerDatas->column('true_mem_usage',Table::TYPE_INT);
        self::$workerDatas->create();
    }

    public static function workerSet(int $workerId, string $key, mixed $data) {
        self::$workerDatas->set($workerId,[$key => $data]);
    }

    public static function workerInc(int $workerId, string $key, int|float $n=1) {
        return self::$workerDatas->incr($workerId,$key,$n);
    }

    public static function workerGet(int $workerId, ?string $key=null) {
        return self::$workerDatas->get($workerId,$key);
    }
}
ServerContext::init();
?>