<?php
namespace LDLib\Server;

$libDir = __DIR__.'/..';
require_once $libDir.'/Swoole/ServerContext.php';
require_once $libDir.'/EventBridge.php';
require_once $libDir.'/Logger.php';

use LDLib\DataFetcher\DataFetcher;
use LDLib\Event\EventBridge;
use LDLib\GraphQL\GraphQL;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\PostHog\PostHog;
use Swoole\Coroutine\Http\Client;
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Timer;

class HTTPServer {
    public static EventBridge $eventBridge;
    public static Server $server;
    public static string $rootPath;

    public static function init(string $rootPath, string $host, int $port, callable $resolver, $mode=SWOOLE_PROCESS, $sockType=SWOOLE_TCP|SWOOLE_SSL, ?callable $onWorkerStart=null, ?array $settings=null) {
        if (!DataFetcher::$isHTTPDataFetcher) throw new \ErrorException('DataFetcher not configured.');

        self::$rootPath = $rootPath;
        Logger::$logDir = self::$rootPath.'/.serv/logs';
        if (!is_dir(self::$rootPath.'/.serv/logs/http/swoole-logs')) mkdir(self::$rootPath.'/.serv/logs/http/swoole-logs/',777,true);
        file_put_contents(self::$rootPath.'/.serv/logs/http/swoole-logs/swoole-log.txt','',FILE_APPEND);

        $dotenv = \Dotenv\Dotenv::createMutable(self::$rootPath);
        $dotenv->load();

        DataFetcher::init();

        self::$server = new Server($host,$port,$mode,$sockType);
        $finalSettings = [
            'user' => 'root',
            'group' => 'www-data',
            // 'chroot' => '',

            'daemonize' => (bool)$_SERVER['LD_DAEMONIZE'],
            'enable_reuse_port' => false,
            'debug_mode' => true,
            'display_errors' => true,
            'reload_async' => true,
            'max_wait_time' => 10,

            'reactor_num' => (int)$_SERVER['LD_HTTP_REACTOR_NUM'] > 0 ? (int)$_SERVER['LD_HTTP_REACTOR_NUM'] : null,
            'worker_num' => (int)$_SERVER['LD_HTTP_WORKER_NUM'] > 0 ? (int)$_SERVER['LD_HTTP_WORKER_NUM'] : null,

            'trace_flags' => SWOOLE_TRACE_WORKER | SWOOLE_TRACE_PHP | SWOOLE_TRACE_SERVER,
            'log_file' => self::$rootPath.'/.serv/logs/http/swoole-logs/swoole-log.txt',
            'log_level' => SWOOLE_LOG_TRACE,
            'log_date_format' => '%Y-%m-%d %H:%M:%S',
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
            'pid_file' => self::$rootPath.'/.serv/server.pid',
            'stats_file' => self::$rootPath.'/.serv/logs/http/stats.log',

            'upload_tmp_dir' => self::$rootPath.'/tmp',

            'dns_server' => '1.1.1.1',

            'max_coroutine' => 3000,
            'hook_flags' => SWOOLE_HOOK_ALL,
            'enable_preemptive_scheduler' => false,

            'dispatch_mode' => SWOOLE_DISPATCH_ROUND,
            'enable_unsafe_event' => false,

            'ssl_cert_file' => $_SERVER['LD_SSL_CERT'],
            'ssl_key_file' => $_SERVER['LD_SSL_KEY'],
            'ssl_protocols' => SWOOLE_SSL_TLSv1_2 | SWOOLE_SSL_TLSv1_3,
            'ssl_verify_peer' => false,
            'ssl_allow_self_signed' => false,
            'ssl_compress' => false,
            'ssl_prefer_server_ciphers' => true,
            'ssl_verify_depth' => 100,

            'max_connection' => 300,
            'backlog' => 100,
            'max_request' => 50000,
            'discard_timeout_request' => true,

            'open_tcp_keepalive' => true,
            'tcp_keepidle' => 1800,
            'tcp_keepinterval' => 5,
            'tcp_keepcount' => 1,
            'tcp_fastopen' => false,
            'tcp_user_timeout' => 10000, // in ms

            'heartbeat_check_interval' => 600,
            'heartbeat_idle_time' => 1830,

            'http_compression' => false,
            'http_compression_level' => 4,

            'open_http2_protocol' => false, //! HTTP2 not working on safari with files larger than ~65kb ???????

            'open_cpu_affinity' => false,

            'buffer_input_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 55 * 1024 * 1024,
            'socket_buffer_size' => 2 * 1024 *1024,
            'package_max_length' => 55 * 1024 * 1024
        ];
        if ($settings != null) foreach ($settings as $k => $v)  $finalSettings[$k] = $v;
        self::$server->set($finalSettings);

        self::$server->on('start', function() {
            print_r("HTTP server started.".PHP_EOL);
            Logger::log(LogLevel::INFO, 'Server', "HTTP server started.");

            $oldMem = -1;
            $nMem = (int)$_SERVER['LD_DEBUG_TRACE_MEMUSAGE'];
            if ($nMem > 0) Timer::tick(1000*$nMem,function() use (&$oldMem) {
                $mem = memory_get_usage();
                $trueMem = memory_get_usage(true);
                if ($mem != $oldMem) { $oldMem = $mem; Logger::log(LogLevel::DEBUG, 'MEMORY', "$mem (true: $trueMem)"); }
            });

            $watchdogDetected = getenv('WATCHDOG_USEC') !== false;

            if ((int)($_SERVER['LD_OS_NOTIFY']) === 1) {
                exec('systemd-notify --ready');
                $nWatchdog = getenv('WATCHDOG_USEC');
                if ($watchdogDetected) {
                    $nWatchdog = ((int)$nWatchdog) * 0.000001;
                    $ping = $nWatchdog > 1 ? (int)($nWatchdog*0.9) : 1;
                    echo "Watchdog is set to : {$nWatchdog}s, will ping every $ping seconds.".PHP_EOL;
                    Timer::tick($ping*1000, function() {
                        exec('systemd-notify WATCHDOG=1');
                    });
                } else { print_r('Watchdog is not set.'.PHP_EOL); Logger::log(LogLevel::WARN, 'Watchdog', 'Watchdog is not set.'); }
            }

            $sAutoCleanLogs = (int)($_SERVER['LD_AUTOCLEAN_LOGS']??0);
            if ($sAutoCleanLogs > 0) Timer::tick(86400*1000,function() use ($sAutoCleanLogs) {
                Logger::cleanLogFiles($sAutoCleanLogs);
                Logger::cleanSwooleHTTPLogFiles($sAutoCleanLogs);
            });
            
            $tSSLWatch = (int)$_SERVER['LD_WATCH_SSL_FILES'];
            if ($tSSLWatch > 0 && (file_exists($_SERVER['LD_SSL_CERT']) || file_exists($_SERVER['LD_SSL_KEY']))) {
                echo "Listening for TLS file changes every {$tSSLWatch}s.".PHP_EOL;
                $lastMTime1 = stat($_SERVER['LD_SSL_CERT'])['mtime']??-1;
                $lastMTime2 = stat($_SERVER['LD_SSL_KEY'])['mtime']??-1;

                Timer::tick($tSSLWatch*1000,function() use(&$lastMTime1,&$lastMTime2,$watchdogDetected) {
                    clearstatcache(false,$_SERVER['LD_SSL_CERT']);
                    clearstatcache(false,$_SERVER['LD_SSL_KEY']);

                    $mTime1 = stat($_SERVER['LD_SSL_CERT'])['mtime']??$lastMTime1;
                    $mTime2 = stat($_SERVER['LD_SSL_KEY'])['mtime']??$lastMTime2;
                    switch (true) {
                        case $mTime1 !== $lastMTime1:
                            $lastMTime1 = $mTime1;
                            echo "TLS file change detected (LD_SSL_CERT)".PHP_EOL;
                            if ($watchdogDetected) { echo 'Restarting server.'.PHP_EOL; `systemctl restart php-http-server.service`; }
                            break;
                        case $mTime2 !== $lastMTime2:
                            $lastMTime2 = $mTime2;
                            echo "TLS file change detected (LD_SSL_KEY)".PHP_EOL;
                            if ($watchdogDetected) { echo 'Restarting server.'.PHP_EOL; `systemctl restart php-http-server.service`; }
                            break;
                    }
                });
            }
        });

        self::$server->on('workerstart', function (Server $server, int $workerId) use($dotenv,$onWorkerStart) {
            echo "Worker started : $workerId\n";
            Logger::log(LogLevel::INFO, 'Worker - HTTP', "Worker started : $workerId (pid:{$server->getWorkerPid()})");
            ServerContext::workerSet($workerId,'nRequests',0);

            Timer::tick(5000,function() use ($workerId) {
                ServerContext::workerSet($workerId,'mem_usage',memory_get_usage());
                ServerContext::workerSet($workerId,'true_mem_usage',memory_get_usage(true));
            });
            $oldMem = -1;
            $nMem = (int)$_SERVER['LD_DEBUG_TRACE_MEMUSAGE'];
            if ($nMem > 0) Timer::tick(1000*$nMem,function() use ($workerId,$server,&$oldMem) {
                $mem = memory_get_usage();
                $trueMem = memory_get_usage(true);
                if ($mem != $oldMem) { $oldMem = $mem; Logger::log(LogLevel::DEBUG, 'MEMORY', "Worker $workerId (pid:{$server->getWorkerPid()}): "."$mem (true: $trueMem)"); }
            });

            $dotenv->load();
            $libDir = __DIR__.'/..';
            require_once self::$rootPath.'/vendor/autoload.php';
            require_once $libDir.'/Utils/Utils.php';
            require_once $libDir.'/Utils/ArrayTools.php';
            require_once $libDir.'/Swoole/SwoolePromise.php';
            require_once $libDir.'/Swoole/SwoolePromiseAdapter.php';
            require_once $libDir.'/GraphQL/Executor.php';
            require_once $libDir.'/GraphQL/Rules/Limiter.php';
            require_once $libDir.'/GraphQL/Rules/QueryComplexity.php';
            require_once $libDir.'/GraphQL/Rules/MutationLimiter.php';
            require_once $libDir.'/GraphQL/GraphQL.php';
            require_once $libDir.'/GraphQL/QueryValidationContext.php';
            require_once $libDir.'/GraphQL/DocumentValidator.php';
            require_once $libDir.'/AWS.php';
            require_once $libDir.'/Classes.php';
            require_once $libDir.'/Context.php';
            require_once $libDir.'/Database.php';
            require_once $libDir.'/FFMPEG.php';
            require_once $libDir.'/Magick.php';
            require_once $libDir.'/Network.php';
            require_once $libDir.'/OAuth.php';
            require_once $libDir.'/PostHog.php';
            require_once $libDir.'/Redis.php';
            require_once $libDir.'/Security.php';
            require_once $libDir.'/User.php';
            require_once $libDir.'/GraphQL.php';
            if (isset($onWorkerStart)) $onWorkerStart();
            if ($workerId == 0) DataFetcher::init2();
            GraphQL::buildSchema();
        });

        $workerExitAlreadyTriggered = false;
        self::$server->on('workerexit',function(Server $server, int $workerId) use(&$workerExitAlreadyTriggered) { 
            if ($workerExitAlreadyTriggered) return;
            $workerExitAlreadyTriggered = true;
            echo "Worker exiting : $workerId".PHP_EOL;
            Logger::log(LogLevel::INFO, 'Worker', "Worker exiting : $workerId");
            
            go(fn() => PostHog::$main?->shutdown());
            Timer::clearAll();
        });

        self::$server->on('request', function(Request $request, Response $response) use($resolver) {
            $resolver($request,$response);
        });

        self::$server->on('beforereload', function() use($dotenv) {
            Logger::log(LogLevel::INFO, 'Server', "HTTP server will reload.");
            $dotenv->load();
        });

        $serverShutdownAlreadyTriggered = false;
        self::$server->on('beforeshutdown',function() use(&$serverShutdownAlreadyTriggered) {
            if ($serverShutdownAlreadyTriggered) return;
            $serverShutdownAlreadyTriggered = true;
            
            echo "HTTP server stopping.".PHP_EOL;
            Logger::log(LogLevel::INFO, 'Server', "HTTP server stopping.");
            Timer::clearAll();
        });

        self::$server->on('shutdown',function() {
            echo "HTTP server stopped.".PHP_EOL;
            Logger::log(LogLevel::INFO, 'Server', "HTTP server stopped.");
        });
    }

    public static function initEventBridge(string $host, int $port, bool $ssl=true, bool $doShoutings=false) {
        self::$eventBridge = new EventBridge(new Client($host,$port,$ssl));
        if ($doShoutings) Timer::tick(5000,fn() => self::$eventBridge->sendEvent('shout',60));
    }
}
?>