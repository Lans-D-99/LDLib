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
namespace LDLib\Client;

use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Timer;

$libDir = __DIR__.'/..';
require_once $libDir.'/Logger.php';

class WSClient {
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

    public function connect(string $path, bool $async=true) {
        if ($this->client->connected) { Logger::log(LogLevel::INFO, 'WSClient', "WSClient already connected to path $path."); return; }
        if ($this->connectWG->count() > 0) return;

        $wg = new WaitGroup(1);
        $res = null;
        go(function() use($wg,$path,&$res) {
            $this->connectWG->add();
            $res = $this->client->upgrade($path);
            if (!$res) {
                if ($this->reconnectTimerID <= 0) {
                    if (time() - $this->lastConnectionFailLogged > 60) {
                        $this->lastConnectionFailLogged = time();
                        Logger::log(LogLevel::ERROR, 'WSClient', "!!! Couldn't connect to path. Retries every {$this->nConnectionRetry}s. (path:$path)");
                    }
                    $this->reconnectTimerID = (int)Timer::after($this->nConnectionRetry*1000,function() use($path) {
                        $this->reconnectTimerID = -1;
                        $this->connect($path);
                    });
                }
            }

            $this->connectWG->done();
            $wg->done();
        });
        if (!$async) { $wg->wait(); return $res; }
    }
}
?>