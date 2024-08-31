<?php
namespace LDLib\PostHog;

use LDLib\ErrorType;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\OperationResult;
use LDLib\SuccessType;
use Swoole\Timer;

class PostHog {
    public static ?PostHog $main = null;
    public static int $curlMaxRedirections = 10;

    public array $batch = [];
    public int $batchSendCutoff;
    public int $maxBatchSize;
    public int $forceSendBatch; // After X seconds of not sending batch, send batch

    public bool $compress = true;
    public int $curlWaitTimeout;
    public bool $dontChangeURL;
    
    public int $tsLastBatchSent;
    public int $forceSendBatchTimerId;

    public static function initMain() {
        self::$main = new PostHog();
    }

    public function __construct(public string $host='', public string $projectKey='', public string $personalAPIKey='') {
        if ($this->host == null) $this->host = $_SERVER['LD_POSTHOG_HOST'];
        if ($this->projectKey == null) $this->projectKey = $_SERVER['LD_POSTHOG_PROJECT_KEY'];
        if ($this->personalAPIKey == null) $this->personalAPIKey = $_SERVER['LD_POSTHOG_PERSONAL_API_KEY'];
        $this->curlWaitTimeout = (int)($_SERVER['LD_POSTHOG_CURL_TIMEOUT']??10000);
        $this->batchSendCutoff = (int)($_SERVER['LD_POSTHOG_BATCH_SEND_CUTOFF']??100);
        $this->maxBatchSize = (int)($_SERVER['LD_POSTHOG_MAX_BATCH_SIZE']??500);
        $this->dontChangeURL = (bool)($_SERVER['LD_POSTHOG_DONT_CHANGE_URL']??false);
        $this->forceSendBatch = (int)($_SERVER['LD_POSTHOG_FORCE_SEND_BATCH']??600);
        $this->tsLastBatchSent = time();

        $this->forceSendBatchTimerId = Timer::tick((int)($_SERVER['LD_POSTHOG_FORCE_SEND_BATCH_CHECK']??60),function() {
            if (time() - $this->tsLastBatchSent > $this->forceSendBatch) $this->sendBatch();
        });
    }

    public function shutdown(bool $sendEvents = true) {
        Timer::clear($this->forceSendBatchTimerId);
        if ($sendEvents) while (count($this->batch) > 0) $this->sendBatch();
    }

    public function enqueue(array $a, bool $addTimestampIfNotSet=true) {
        if (count($this->batch) >= $this->maxBatchSize) {
            Logger::log(LogLevel::ERROR, 'PostHog', "Max batch size reached. ({$this->maxBatchSize})");
            return;
        }
        if ($addTimestampIfNotSet && !isset($a['timestamp'])) $a['timestamp'] = (new \DateTimeImmutable('now'))->format('c');
        if (!isset($a['properties'])) $a['properties'] = [];
        $a['properties']['library'] = 'LDLib';
        $a['properties']['ldlib_version'] = LDLIB_VERSION_WITH_SUFFIX;

        $this->batch[] = $a;

        $count = count($this->batch);
        if ($count >= $this->batchSendCutoff) $this->sendBatch(array_splice($this->batch, 0, min($this->maxBatchSize,$count)));
    }

    private function sendBatch(?array $batch=null):OperationResult {
        if ($batch == null) $batch = array_splice($this->batch, 0, min($this->maxBatchSize,count($this->batch)));
        $this->tsLastBatchSent = time();
        $count = count($batch);
        if ($count < 1) return new OperationResult(SuccessType::SUCCESS,'Nothing to send.');
        
        $ch = curl_init($this->dontChangeURL ? $this->host : "{$this->host}/batch");

        $payload = json_encode([
            'api_key' => $this->projectKey,
            'batch' => $batch
        ]);
        if ($this->compress) $payload = gzencode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $headers = ['Content-Type: application/json'];
        if ($this->compress) $headers[] = 'Content-Encoding: gzip';
        curl_setopt_array($ch,[
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->curlWaitTimeout,
            CURLOPT_CONNECTTIMEOUT_MS => $this->curlWaitTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => PostHog::$curlMaxRedirections
        ]);

        Logger::log(LogLevel::INFO, 'PostHog - SendBatch', "Sending $count events, batched.");
        $v = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($v === false) {
            trigger_error(curl_error($ch));
            Logger::log(LogLevel::ERROR, 'PostHog - SendBatch', 'Unknown error.');
            return new OperationResult(ErrorType::UNKNOWN,'CURL ERROR');
        } else if ($statusCode !== 200) {
            Logger::log(LogLevel::ERROR, 'PostHog - SendBatch', "Non-200 reponse: $statusCode");
            error_log(substr($v,0,400));
            return new OperationResult(ErrorType::UNKNOWN,null,[],['data' => $v, 'statusCode' => $statusCode]);
        }

        return new OperationResult(SuccessType::SUCCESS,null,[],['data' => $v, 'statusCode' => $statusCode]);
    }
}
?>