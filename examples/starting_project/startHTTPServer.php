<?php
$apiDir = __DIR__.'/api';
require_once __DIR__.'/vendor/autoload.php';
require_once $apiDir.'/Cache.php';
\LDLib\Cache\LocalCache::bindCache('\\BaseWebsite\\Cache\\LocalCache');

use BaseWebsite\Context\HTTPContext;
use BaseWebsite\Context\OAuthContext;
use LDLib\GraphQL\GraphQL;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Security\Security;
use LDLib\Server\HTTPServer;
use LDLib\Server\ServerContext;
use LDLib\Utils\Utils;
use Swoole\Http\Request;
use Swoole\Http\Response;

function badRequest(Response $response, string $msg='Bad request.') {
    $response->header('Content-Type', 'text/plain');
    $response->status(405);
    $response->end($msg);
}

function forbidden(Response $response, string $msg='Unauthorized access.') {
    $response->header('Content-Type', 'text/plain');
    $response->status(403);
    $response->end($msg);
}

function fileNotFound(Response $response, string $msg='File not found.') {
    $response->status(404);
    $response->header('Content-Type', 'text/plain');
    $response->end($msg);
}

function defaultAccessControlOrigin(Request $request, Response $response) {
    switch ($request->header['origin']??'') {
        case $_SERVER['LD_LINK_OAUTH']: $response->header('Access-Control-Allow-Origin', $_SERVER['LD_LINK_OAUTH']); break;
        case $_SERVER['LD_LINK_WWW']: $response->header('Access-Control-Allow-Origin', $_SERVER['LD_LINK_WWW']); break;
        default: $response->header('Access-Control-Allow-Origin', "{$_SERVER['LD_LINK_ROOT']}"); break;
    }
}

$resolver = function(Request $request, Response $response) {
    $workerId = HTTPServer::$server->getWorkerId();
    ServerContext::workerInc($workerId,'nRequests',1);

    $requestHost = $request->header['host']??'';
    $remoteAddr = Utils::getRealRemoteAddress($request);
    if (!isset($requestHost)) { badRequest($response); return; }

    $requestMethod = $request->server['request_method']??'';
    $requestURI = urldecode($request->server['request_uri'])??'/';

    $subdomain = '';
    if (preg_match('/^(?:(?:0.0.0.0)|(?:(?:([\w-]+)\.){0,3}[\w-]+\.\w+))$/',$requestHost,$m,PREG_UNMATCHED_AS_NULL) > 0) {
        $subdomain = $m[1];
        switch ($subdomain) {
            case 'www': $response->redirect('https://'.$_SERVER['LD_LINK_DOMAIN'].$requestURI); return;
            case 'mta-sts':
                if ($requestURI === '/.well-known/mta-sts.txt' && $request->server['server_port'] === 443) {
                    $filePath = HTTPServer::$rootPath.$requestURI;
                    if (file_exists($filePath)) { $response->header('Content-Type','text/plain'); $response->sendfile($filePath); }
                    else fileNotFound($response);
                    return;
                }
        }
    }
    if (isset($request->header['x-subdomain'])) $subdomain = $request->header['x-subdomain'];
    if (!in_array($subdomain,['www','res','api','oauth','mta-sts'])) $subdomain = 'www';

    $response->header('Server', 'Swoole');

    // Handle letsEncrypt challenge
    if (str_starts_with($requestURI, '/.well-known/acme-challenge/')) {
        $filePath = HTTPServer::$rootPath.$requestURI;
        if (str_contains($filePath,'/../')) { badRequest($response); return; }

        if (file_exists($filePath)) $response->sendfile($filePath);
        else fileNotFound($response);
        return;
    }

    // Force HTTPS
    if ($request->server['server_port'] == 80) {
        $response->redirect(
            'https://'.(isset($m[1]) ? $m[1].'.' : '').$_SERVER['LD_LINK_DOMAIN'].$requestURI,
            ($requestMethod === 'GET' || $requestMethod === 'HEAD') ? 301 : 308
        );
        return;
    }
    $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

    // Secret Access Key logic
    if (in_array($subdomain,['www','api','oauth']) && ($requestMethod == 'GET' || $requestMethod == 'POST')) {
        if (isset($_SERVER['LD_SECRET_ACCESS_KEY'])) {
            $secKey = $_SERVER['LD_SECRET_ACCESS_KEY'];

            if ($secKey !== ($request->cookie['secretAccessKey']??'')) {
                if ($secKey === ($request->get['secretAccessKey']??''))
                    $response->setCookie('secretAccessKey',$secKey,time()+3600*24*7,'/',$_SERVER['LD_LINK_DOMAIN'],true,true,'Lax');
                else { forbidden($response,'Unauthorized access. (The link you were given might have become invalid.)'); return; }
            }
        }
    }

    // Security: check if ip is banned
    if (Security::isIPBanned($remoteAddr)) { forbidden($response,'Your IP Address is banned.'); return; }

    // Security: check limits for unauthentified users
    if (!isset($request->cookie['sid'])) {
        if (Security::isQueryComplexityLimitReached($remoteAddr)) { forbidden($response,'API limit reached.'); return; }
        else if (Security::isRequestsLimitReached($remoteAddr)) { forbidden($response,'Requests limit reached.'); return; }
    }

    // Create context object
    try {
        $context = null;
        if (($request->header['authorization']??'') != null) $context = new OAuthContext(HTTPServer::$server,$request,$response);
        if ($context?->asUser == null) {
            $context = new HTTPContext(HTTPServer::$server,$request,$response);
            // Security check for authentified users
            $user = $context->getAuthenticatedUser();
            if ($user?->hasRole('Administrator') === false && is_int($user?->id)) {
                if (Security::isQueryComplexityLimitReached_Users($user->id)) { forbidden($response,'API limit reached.'); return; }
                else if (Security::isRequestsLimitReached_Users($user->id)) { forbidden($response,'Requests limit reached.'); return; }
            }
        }
        if ($context == null) new \LogicException('Context ???');
    } catch (\Throwable $t) {
        Logger::log(LogLevel::FATAL, "Context", "Context couldn't be initialized.");
        Logger::logThrowable($t);
        $response->header('Content-Type', 'application/json');
        $response->status(503);
        $response->end('{"error":"server not ready"}');
        return;
    }

    // Process file extension
    $fileExtension = '';
    if ($requestURI != '/') {
        $requestURI = preg_replace('/\/+$/','',$requestURI);
        if (preg_match('/\.(\w+)$/',$requestURI,$m) === 0) { $requestURI .= '.php'; $fileExtension = 'php'; }
        else $fileExtension = $m[1];
    }

    // Security: Increment request counter
    go(function() use($context,$user,$remoteAddr) {
        $pdo = $context->getLDPDO();
        if ($user !== null) {
            $pdo->pdo->query("INSERT INTO sec_users_total_requests (user_id,count) VALUES ($user->id,1) ON DUPLICATE KEY UPDATE count=count+1");
        } else {
            $pdo->pdo->query("INSERT INTO sec_total_requests (remote_address,count) VALUES ('{$remoteAddr}',1) ON DUPLICATE KEY UPDATE count=count+1");
        }
        $pdo->close();
    });

    // CSP reporting
    // if ($requestURI == '/csp-reports.php' && $requestMethod == 'POST') {
    //     if ($request->header['content-type'] == 'application/csp-report') {
    //         $res = json_decode($request->getContent(),true);
    //         if ($res != null) error_log(print_r($res,true));
    //         $response->end('OK');
    //     } else $response->end('NOT OK');
    // }

    if ($subdomain === 'api' && $requestURI === '/graphql.php') {
        $response->header('Access-Control-Allow-Credentials','true');
        $response->header('Access-Control-Allow-Headers','Content-Type, Cache-Control');
        defaultAccessControlOrigin($request,$response);

        if ($requestMethod !== 'POST') {
            $response->status(200);
            $response->end('...');
            return;
        }

        GraphQL::processQuery($context);
    } else if ($requestMethod == 'GET' && ($subdomain == 'www' || $subdomain == 'res' || $subdomain == 'oauth')) {
        $path = $_SERVER['LD_SUBDOMAINS_PATH']??null;
        if ($path === null) { $response->header('Content-Type', 'text/html'); $response->end('Internal error.'); return; }

        if (preg_match('/^(.*(\.js|\.css|\/pages\/.*\.php))\.h_(\w+)$/',$requestURI,$m) > 0) {
            $requestURI = $m[1];
            $response->header('Cache-Control','max-age=31536000');
        }

        try {
            if ($subdomain === 'www') {
                $response->header('Access-Control-Allow-Credentials','true');
                defaultAccessControlOrigin($request,$response);
                switch ($requestURI) {
                    case '/':
                    case '/index.php': $response->end(\BaseWebsite\Pages\WWW\Index::getPage($request,$response,$requestLoadPage??'')); break;
                    case '/manifest.webmanifest': $response->end(\BaseWebsite\Pages\WWW\Manifest::getPage($request,$response)); break;
                    case '/style.css': $response->end(\BaseWebsite\Pages\WWW\Style::getPage($request,$response)); break;
                    case '/styleReset.css': $response->end(\BaseWebsite\Pages\WWW\StyleReset::getPage($request,$response)); break;
                    case '/scripts/components.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Components::getPage($request,$response)); break;
                    case '/scripts/init.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Init::getPage($request,$response)); break;
                    case '/scripts/load.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Load::getPage($request,$response)); break;
                    case '/scripts/quick.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Quick::getPage($request,$response)); break;
                    case '/scripts/router.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Router::getPage($request,$response)); break;
                    case '/scripts/storage.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Storage::getPage($request,$response)); break;
                    case '/scripts/events.js': $response->end(\BaseWebsite\Pages\WWW\Scripts\Events::getPage($request,$response)); break;
                    case '/scripts/external/gsap.min.3.12.5.js':
                        $filePath = HTTPServer::$rootPath."/$path/$subdomain{$requestURI}";
                        if (file_exists($filePath)) { $response->header('Cache-Control','max-age=31536000'); $response->sendfile($filePath); }
                        else fileNotFound($response);
                        break;
                    default: fileNotFound($response); break;
                }
            } else if ($subdomain === 'res') {
                $response->header('Access-Control-Allow-Credentials','true');
                defaultAccessControlOrigin($request,$response);

                if (preg_match('/^\/file\/([^\/]*)$/', $requestURI, $m) > 0) {
                    $response->end(\BaseWebsite\Pages\Res\Gen\Page::get($request,$response,$m[1]));
                    return;
                }

                $filePath = HTTPServer::$rootPath."/$path"."/$subdomain".$requestURI;
                if (str_contains($filePath,'/../')) { badRequest($response); return; }
                if (!file_exists($filePath)) fileNotFound($response);

                
                $contentType = match ($fileExtension) {
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    default => null
                };
                if ($contentType != null) $response->header('Content-Type',$contentType);
                $response->end(@file_get_contents($filePath));
            } else fileNotFound($response);
        } catch (\Throwable $t) {
            $response->status(500);
            $response->header('Content-Type', 'text/plain');
            $response->end("Couldn't load resource.");
            Logger::logThrowable($t);
            return;
        }
    } else {
        badRequest($response);
        return;
    }
};

$onWorkerStart = function() {
    require_once __DIR__.'/api/GraphQL.php';
    require_once __DIR__.'/api/Schema.php';
    require_once __DIR__.'/lib/Auth.php';
    require_once __DIR__.'/lib/Context.php';
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
    \BaseWebsite\GraphQL::init();
};
HTTPServer::init(__DIR__,'0.0.0.0',443,$resolver,onWorkerStart:$onWorkerStart);
HTTPServer::initEventBridge('0.0.0.0',1443,true);
if (HTTPServer::$server->listen('0.0.0.0',80,SWOOLE_TCP) !== false) print_r('Listening on port 80.'.PHP_EOL);
HTTPServer::$server->start();
?>