<?php
$apiDir = __DIR__.'/api';
require_once __DIR__.'/vendor/autoload.php';
require_once $apiDir.'/Cache.php';
\LDLib\Cache\LocalCache::bindCache('\\BaseWebsite\\Cache\\LocalCache');

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use LDLib\Event\EventResolver;
use LDLib\GraphQL\GraphQL;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Server\WSServer;
use BaseWebsite\Context\WSContext;

$resolver = function (Server $wsServer, Frame $frame) {
    try {
        $context = new WSContext($wsServer, $frame);
    } catch (\Throwable $t) {
        @$wsServer->push($frame->fd,json_encode(['error' => 'Invalid connection.']));
        $wsServer->disconnect($frame->fd,SWOOLE_WEBSOCKET_CLOSE_SERVER_ERROR);
        Logger::log(LogLevel::ERROR, 'Websocket - Message', 'Invalid connection.');
        Logger::logThrowable($t);
        return;
    }

    if ($context->isEventTriggerer()) {
        $json = json_decode($frame->data,true);
        $b = false;
        if (is_array($json) && isset($json['event'])) $b = EventResolver::resolveEvent($json['event'],$json['data']);
        @$wsServer->push($frame->fd,json_encode(['event_resolution' => $b ? 'succeeded' : 'failed']));
        return;
    }

    GraphQL::processQuery($context);
};

$onWorkerStart = function() {
    require_once __DIR__.'/api/GraphQL.php';
    require_once __DIR__.'/api/Schema.php';
    require_once __DIR__.'/lib/Auth.php';
    require_once __DIR__.'/lib/Context.php';
    require_once __DIR__.'/lib/EventResolver.php';
    require_once __DIR__.'/lib/User.php';
    require_once __DIR__.'/public_html/subdomains/res/file.php';
    require_once __DIR__.'/public_html/subdomains/www/index.php';
    require_once __DIR__.'/public_html/subdomains/www/manifest.php-webmanifest';
    require_once __DIR__.'/public_html/subdomains/www/style.php-css';
    require_once __DIR__.'/public_html/subdomains/www/styleReset.php-css';
    require_once __DIR__.'/public_html/subdomains/www/scripts/components.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/init.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/load.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/quick.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/router.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/storage.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/components.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/events.php-js';
    \BaseWebsite\GraphQL::init(true);
};
WSServer::init(__DIR__,'0.0.0.0',1443,$resolver,onWorkerStart:$onWorkerStart);
WSServer::$server->start();
?>