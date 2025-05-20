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
namespace LDLib\Logger;

use LDLib\Server\WorkerContext;

enum LogLevel:Int {
    case FATAL = 5;
    case ERROR = 4;
    case DEBUG = 3;
    case WARN = 2;
    case INFO = 1;
    case TRACE = 0;
}

class Logger {
    public static string $name = '';
    public static string $logDir = __DIR__.'/../.serv/logs';
    public static LogLevel $minLevel = LogLevel::INFO;

    public static function log(LogLevel $level, string $sub, string $msg) {
        if ($level->value < self::$minLevel->value) return;
        $sNow = (new \DateTime('now'))->format('Y-m-d H:i:s.v');
        $name = self::$name;
        try { $sWorker = "Worker n°".WorkerContext::$server->getWorkerId().'('.WorkerContext::$server->getWorkerPid().')'; } catch (\Error $e) { $sWorker = 'No worker'; }
        file_put_contents(self::prepFile(),"$sNow | $name | $sWorker | {$level->name} | $sub | $msg".PHP_EOL,FILE_APPEND);
    }

    public static function logThrowable(\Throwable $t) {
        error_log($t->getMessage());
        error_log(print_r($t,true));
    }

    public static function cleanLogFiles(?int $secondsToKeep=86400*7, bool $test=false):array {
        $now = new \DateTimeImmutable('now');
        $cutoff = $now->modify("-{$secondsToKeep} seconds");
        $dir = self::$logDir;

        $aDeleted = [];
        $yFolders = scandir($dir);
        foreach ($yFolders as $y) if (preg_match('/^\d{4}$/',$y) > 0 && is_dir("$dir/{$y}")) {
            $mFolders = scandir("$dir/{$y}");
            foreach ($mFolders as $m) if (preg_match('/^\d\d$/',$m) > 0 && is_dir("$dir/{$y}/{$m}")) {
                $dFolders = scandir("$dir/{$y}/{$m}");
                foreach ($dFolders as $d) if (preg_match('/^(\d\d)\.txt$/',$d,$dMatch) > 0 && !is_dir("$dir/{$y}/{$m}/{$d}")) {
                    $filePath = "$dir/{$y}/{$m}/{$d}";
                    $d2 = $dMatch[1];
                    $date = new \DateTimeImmutable("{$y}-{$m}-{$d2}T23:59:59Z");
                    if (($secondsToKeep < 0 || $date < $cutoff) && is_writable($filePath)) {
                        $aDeleted[] = "{$y}/{$m}/{$d2}";
                        if (!$test) { @unlink($filePath); Logger::log(LogLevel::INFO,'LOGGING',"Deleted '$filePath'."); }
                    }
                }
                if (count(scandir("$dir/{$y}/{$m}")) == 2 && is_writable("$dir/{$y}/{$m}") && !$test) rmdir("$dir/{$y}/{$m}");
            }
            if (count(scandir("$dir/{$y}")) == 2 && is_writable("$dir/{$y}") && !$test) rmdir("$dir/{$y}");
        }

        return $aDeleted;
    }

    public static function cleanSwooleHTTPLogFiles(?int $secondsToKeep=86400*7, bool $test=false):array {
        $now = new \DateTimeImmutable('now');
        $cutoff = $now->modify("-{$secondsToKeep} seconds");
        $dir = self::$logDir.'/http/swoole-logs';

        $aDeleted = [];
        $files = scandir($dir);
        foreach ($files as $file) if (preg_match('/^swoole\-log\.txt\.(\d{8})$/',$file,$m) > 0 && !is_dir("$dir/$file") && is_writable("$dir/$file")) {
            $date = new \DateTimeImmutable("{$m[1]}T235959Z");
            if ($secondsToKeep < 0 || $date < $cutoff) {
                $aDeleted[] = $file;
                $filePath = "$dir/$file";
                if (!$test) { @unlink($filePath); Logger::log(LogLevel::INFO,'LOGGING',"Deleted '$filePath'."); }
            }
        }

        return $aDeleted;
    }

    public static function cleanSwooleWSLogFiles(?int $secondsToKeep=86400*7, bool $test=false):array {
        $now = new \DateTimeImmutable('now');
        $cutoff = $now->modify("-{$secondsToKeep} seconds");
        $dir = self::$logDir.'/ws/swoole-logs';

        $aDeleted = [];
        $files = scandir($dir);
        foreach ($files as $file) if (preg_match('/^swoole\-log\.txt\.(\d{8})$/',$file,$m) > 0 && !is_dir("$dir/$file") && is_writable("$dir/$file")) {
            $date = new \DateTimeImmutable("{$m[1]}T235959Z");
            if ($secondsToKeep < 0 || $date < $cutoff) {
                $aDeleted[] = $file;
                $filePath = "$dir/$file";
                if (!$test) { @unlink($filePath); Logger::log(LogLevel::INFO,'LOGGING',"Deleted '$filePath'."); }
            }
        }

        return $aDeleted;
    }

    public static function cleanPHPLogFiles(bool $test=false):array {
        $filePath = ini_get('error_log');
        $aDeleted = [];
        if (file_exists($filePath)) {
            $index = strrpos($filePath,'/');
            $aDeleted[] = $index != false ? mb_substr($filePath,$index+1) : $filePath;
            if (!$test) { @unlink($filePath); Logger::log(LogLevel::INFO,'LOGGING',"Deleted '$filePath'."); }
        }
        return $aDeleted;
    }

    private static function prepFile():string {
        $now = new \DateTime('now');
        $dir = self::$logDir."/{$now->format('Y')}/{$now->format('m')}";
        $fullPath = $dir."/{$now->format('d')}.txt";
        if (!is_dir($dir)) mkdir($dir,0777,true);
        return $fullPath;
    }
}
?>