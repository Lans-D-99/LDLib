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
namespace LDLib\Server;

use Swoole\Table;
use Swoole\Http\Request;
use Swoole\Http\Response;

class ServerContext {
    public static Table $workerDatas;
    public static string $tempPath;
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

    public static function applyContentEncoding(Request $request, Response $response, string $data):string {
        if (!is_string($request->header['accept-encoding']??null)) return $data;
        if (strlen($data) < 1000) return $data;

        $acceptEncoding = array_map(fn($v) => trim($v), explode(',',$request->header['accept-encoding']));

        if (in_array('zstd',$acceptEncoding)) {
            $response->header('Content-Encoding','zstd',false);
            return zstd_compress($data,3);
        } else if (in_array('br',$acceptEncoding)) {
            $process = proc_open("brotli -f -3 -c", array(0 => array('pipe','r'), 1 => array('pipe','w')), $pipes);
            if (!is_resource($process)) return $data;

            fwrite($pipes[0],$data);
            fclose($pipes[0]);
            $data2 = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            if (proc_close($process) !== 0) return $data;

            $response->header('Content-Encoding','br',false);
            return $data2;
        } else if (in_array('gzip',$acceptEncoding)) {
            $response->header('Content-Encoding','gzip',false);
            return gzencode($data,6,FORCE_GZIP);
        } else if (in_array('deflate',$acceptEncoding)) {
            $response->header('Content-Encoding','deflate',false);
            return gzencode($data,6,FORCE_DEFLATE);
        }
        return $data;
    }

    public static function applyContentDecoding(Request $request, string $data):string|false {
        $encoding = $request->header['content-encoding']??null;
        if ($encoding == null) return $data;

        if ($encoding == 'zstd') return zstd_uncompress($data);
        else if ($encoding == 'br') {
            $process = proc_open("brotli -d -c", array(0 => array('pipe','r'), 1 => array('pipe','w')), $pipes);
            if (!is_resource($process)) return false;

            fwrite($pipes[0],$data);
            fclose($pipes[0]);
            $data2 = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            return proc_close($process) === 0 ? $data2 : false;
        } else if ($encoding == 'gzip' || $encoding == 'deflate') return gzdecode($data);

        return false;
    }
}
ServerContext::init();
?>