<?php
namespace BaseWebsite\Pages\WWW;

use LDLib\Utils\Utils;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Index {
    public static function getPage(Request $request, Response $response, string $requestLoadPage='') {
        $root = Utils::getRootLink();
        $res = Utils::getRootLink('res');
        $rootRegex = str_replace('/','\/',$root);
        $cspReportingEnabled = false;

        $scriptsToLoad = [
            \BaseWebsite\Pages\WWW\Scripts\Quick::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Storage::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Router::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Load::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Init::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Components::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Scripts\Events::getVersionedURI(),
            '/scripts/external/gsap.min.3.12.5.js'
        ];
        $stylesToLoad = [
            \BaseWebsite\Pages\WWW\StyleReset::getVersionedURI(),
            \BaseWebsite\Pages\WWW\Style::getVersionedURI()
        ];

        $sScripts = '';
        foreach ($scriptsToLoad as $s) $sScripts .= "<script src=\"$root{$s}\"></script>";
        $sStyles = '';
        foreach ($stylesToLoad as $s) $sStyles .= "<link rel=\"stylesheet\" href=\"$root{$s}\" type=\"text/css\" />";

        $csp = "default-src https: {$_SERVER['LD_LINK_WEBSOCKET']}; style-src 'unsafe-inline' {$_SERVER['LD_LINK_DOMAIN']}; script-src 'unsafe-inline' {$_SERVER['LD_LINK_DOMAIN']}; img-src {$_SERVER['LD_LINK_RES']}; media-src {$_SERVER['LD_LINK_RES']}; form-action 'none'; frame-ancestors 'none'";
        if ($cspReportingEnabled) {
            $response->header('Report-To',preg_replace('/\r?\n/',' ',<<<JSON
            {
                "group": "csp-endpoint",
                "max_age": 30,
                "endpoints": [
                    { "url": "https://{$_SERVER['LD_LINK_DOMAIN']}/csp-reports" },
                ]
            }
            JSON));
            $csp .= "; report-uri https://{$_SERVER['LD_LINK_DOMAIN']}/csp-reports; report-to csp-endpoint";
        }
        $response->header('Content-Security-Policy',$csp);
        $response->header('X-Frame-Options','DENY');
        $response->header('X-XSS-Protection','0');
        $response->header('X-Content-Type-Options','nosniff');

        $response->header('Content-Type', 'text/html');
        return <<<HTML
        <!DOCTYPE html>

        <html>
            <head>
                <meta charset="UTF-8">
                <meta id="meta_viewport" name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

                $sStyles

                <link rel="manifest" href="$root/manifest.webmanifest"/>
                <link rel="icon" href="$res/icons/globe.ico"/>
                <title>INDEX.PHP</title>

                $sScripts
            </head>

            <body>
                <div class="main">
                    <p>INDEX.PHP</p>
                </div>
            </body>
        </html>
        HTML;
    }
}