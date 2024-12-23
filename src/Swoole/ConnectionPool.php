<?php
namespace LDLib;

use LDLib\Context\Context;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use Swoole\Coroutine\Channel;

class ConnectionPool {
    public Channel $pool;
    public int $nConnectionsTracked = 0;

    private int $capacityLevel = -1;
    private int $busyGoingToLevel = -1;

    public function __construct(public \Closure $constructor, protected array $capacities, public ?\Closure $afterFetch=null) {
        if (count($capacities) <= 0) throw new \Exception('count($capacities) <= 0.');
        foreach ($capacities as $cap) if (!is_int((int)$cap)) throw new \Exception('Invalid capacities given.');
        $capacities = array_map(fn($v) => (int)$v, $capacities);
        sort($capacities,SORT_NUMERIC);
        $this->pool = new Channel(max($capacities));
        $this->fillForLevel($this->capacityLevel);
    }

    public function fill(int $n=-1) {
        if ($n < 0) $n = $this->capacities[$this->capacityLevel];
        $chosenLevel = -1;
        foreach ($this->capacities as $cap) { $chosenLevel++; if ($cap >= $n) break; }
        $this->fillForLevel($chosenLevel);
    }

    public function get(?Context $context=null, float $timeout=1) {
        $conn = $this->pool->pop($timeout);
        if ($conn === false) {
            // Logger::log(LogLevel::WARN, 'ConnectionPool', 'Pool is empty but need connection.');
            go(fn() => $this->fillForLevel($this->capacityLevel+1));
            $conn = $this->pool->pop(5);
            if ($conn === false) {
                return false;
            }
        }
        if ($this->afterFetch !== null) ($this->afterFetch)($conn,$context);
        $this->nConnectionsTracked++;
        return $conn;
    }

    public function put(mixed $connection, float $timeout=3) {
        if ($this->nConnectionsTracked <= 0) {
            Logger::log(LogLevel::ERROR,'ConnectionPool',"Failed giving back a connection to pool, nConnectionsTracked={$this->nConnectionsTracked}, poolLength={$this->pool->length()}.");
            return false;
        }
        $this->nConnectionsTracked--;
        $res = $this->pool->push($connection,$timeout);
        if (!$res) {
            // Logger::log(LogLevel::WARN,'ConnectionPool',"Failed giving back a connection to pool, nConnectionsTracked={$this->nConnectionsTracked}, poolLength={$this->pool->length()}. (errcode:{$this->pool->errCode} (errCode might be wrong))");
        }
        return $res;
    }

    public function getCapacity() { return $this->capacities[$this->capacityLevel]; }
    public function isAtCapacity() { return $this->pool->length() === $this->capacities[$this->capacityLevel]; }
    public function getMaxLevel() { return count($this->capacities)-1; }
    public function getMaxCapacity() { return $this->capacities[count($this->capacities)-1]; }

    private function fillForLevel(int $level, float $timeout=0.5) {
        $maxLevel = $this->getMaxLevel();
        if ($level > $maxLevel) $level = $maxLevel;
        else if ($level < 0) $level = 0;

        if ($this->busyGoingToLevel >= 0) return;
        $this->busyGoingToLevel = $level;

        $capacity = $this->capacities[$level];
        Logger::log(LogLevel::INFO,'ConnectionPool',"Filling to level $level (toCapacity=$capacity, currentLength={$this->pool->length()}).");

        $nFailures = 0;
        while ($this->pool->length() < $capacity) {
            if (!$this->addNewConnection($timeout)) {
                if (++$nFailures == 10) {
                    Logger::log(LogLevel::ERROR, 'ConnectionPool', "Failed filling to level $level (toCapacity=$capacity, currentLength={$this->pool->length()}).");
                    $this->busyGoingToLevel = -1;
                    return;
                }
            }
        }
        $this->capacityLevel = $level;
        $this->busyGoingToLevel = -1;
    }

    private function addNewConnection(float $timeout=1) {
        $maxCapacity = $this->getMaxCapacity();
        if ($this->pool->length() < $maxCapacity) {
            $conn = ($this->constructor)();
            $res = $this->pool->push($conn,$timeout);
            if (!$res) {
                Logger::log(LogLevel::ERROR,'ConnectionPool',"Failed adding a connection to pool. (errcode:{$this->pool->errCode} (errCode might be wrong))");
                return false;
            }
            return true;
        }
        return false;
    }
}

?>