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
namespace LDLib\Database;

use LDLib\Context\Context;
use LDLib\Logger\Logger;
use LDLib\PageInfo;
use LDLib\PaginationVals;
use LDLib\Server\WorkerContext;
use PDOException;

enum MariaDBError:int {
    case ER_DUP_ENTRY = 1062;
}

class LDPDO {
    public string $instanceId;
    public ?\PDO $pdo;
    public array $locks = [];
    public bool $skipTransactionCommands = false;

    public function __construct(public ?Context $context=null) {
        $this->instanceId = bin2hex(random_bytes(8));
        $this->pdo = PDO::getConnection();
    }

    public function query(string $query, ?int $fetchMode = null, ?int $cost=null):\PDOStatement|false {
        if ($this->skipTransactionCommands && self::isTransactionCommand($query)) return false;
        if ($this->context != null) $this->context->dbcost += $cost ?? 1;
        return $this->pdo->query($query,$fetchMode);
    }

    public function prepare(string $query, array $options=[], ?int $cost=null):\PDOStatement|false {
        if ($this->skipTransactionCommands && self::isTransactionCommand($query)) return false;
        if ($this->context != null) $this->context->dbcost += $cost ?? 1;
        return $this->pdo->prepare($query,$options);
    }

    function getLock(string $name, int $timeout):bool {
        $b = $this->query("SELECT GET_LOCK('$name', $timeout)")->fetch(\PDO::FETCH_NUM)[0] === 1;
        if ($b) $this->locks[] = $name;
        return $b;
    }

    function releaseLock(string $name):bool {
        $b = $this->query("SELECT RELEASE_LOCK('$name')")->fetch(\PDO::FETCH_NUM)[0] === 1;
        if ($b) foreach ($this->locks as $k => $v) if ($v == $name) unset($this->locks[$k]);
        return $b;
    }

    public function toPool(bool $rollback=false) {
        if ($rollback) $this->query('ROLLBACK');
        $this->skipTransactionCommands = false;
        WorkerContext::$pdoConnectionPool->put($this);
    }

    public function close() {
        $this->pdo = null;
    }

    public function releaseAllLocks() {
        foreach ($this->locks as $lock) $this->query("SELECT RELEASE_LOCK('$lock')");
    }

    public function assureConnectionIsAlive() {
        try {
            $this->query('SELECT 1');
        } catch (PDOException $e) {
            $this->pdo = PDO::getConnection();
        }
    }

    public static function isTransactionCommand(string $s):bool {
        return in_array(mb_trim($s), ['START TRANSACTION','COMMIT','ROLLBACK']) || str_starts_with($s,'SAVEPOINT') || str_starts_with($s,'SET TRANSACTION');
    }
}

class PDO {
    public static function getConnection():\PDO|null {
        try {
            $dbName = (bool)$_SERVER['LD_TEST'] ? $_SERVER['LD_TEST_DB_NAME'] : $_SERVER['LD_DB_NAME'];
            $conn = new \PDO("mysql:host={$_SERVER['LD_DB_HOST']};dbname={$dbName}", $_SERVER['LD_DB_USER'], $_SERVER['LD_DB_PWD']);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            return $conn;
        } catch(\PDOException $e) {
            echo "Connection to database failed.";
            error_log("Connection to database failed: " . $e->getMessage());
            return null;
        }
    }
}

class DatabaseUtils {
    public static function pagRequest(LDPDO|\PDO $conn, string $dbName, string $whereCond, PaginationVals $pag, string|ComplexVariable $variable, // <- how should I name this one?...
        callable $makeCursor, callable $decodeCursor, callable $storeOne, callable $storeAll, string $select='*', ?array $executeVals = null) {
        if ($whereCond == "") $whereCond = "1=1";
        $first = $pag->first;
        $last = $pag->last;
        $after = $pag->after;
        $before = $pag->before;
        $isComplex = $variable instanceof ComplexVariable;

        $dbLoc = $dbName;
        if (preg_match('/AS (.*)$/i',$dbName, $m) > 0) $dbName = $m[1];

        $executeVals_filteredForWhere = null;
        if ($executeVals != null) {
            $executeVals_filteredForWhere = [];
            foreach ($executeVals as $k => $v) if (str_contains($whereCond,$k)) $executeVals_filteredForWhere[$k] = $v;
            if (count($executeVals_filteredForWhere) == 0) $executeVals_filteredForWhere = null;
        }

        // Make and exec sql
        $sql = "SELECT $select FROM $dbLoc";
        $n = 0;
        $vCurs = null;
        $pageCount = null;
        $currPage = null;
        $getLastPage = $pag->lastPageSpecialBehavior && $last != null && ($before == null && $after == null);

        if ($getLastPage) { // if requesting last page: do this
            if ($executeVals_filteredForWhere != null) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM $dbLoc WHERE $whereCond");
                $stmt->execute($executeVals_filteredForWhere);
                $itemsCount = $stmt->fetch(\PDO::FETCH_NUM)[0];
            } else $itemsCount = $conn->query("SELECT COUNT(*) FROM $dbLoc WHERE $whereCond")->fetch(\PDO::FETCH_NUM)[0];

            $n = $itemsPerPage = $first != null ? $first : $last;
            $mod = $itemsCount % $n;
            if ($mod != 0) $n = $mod;

            $i = $n + 1;
            if ($executeVals != null) {
                $stmt = $conn->prepare("SELECT $select FROM $dbLoc WHERE $whereCond ORDER BY ".($isComplex ? ($variable->orderBy_before)(null,4) : $variable." DESC")." LIMIT $i");
                $stmt->execute($executeVals);
            } else $stmt = $conn->query("SELECT $select FROM $dbLoc WHERE $whereCond ORDER BY ".($isComplex ? ($variable->orderBy_before)(null,4) : $variable." DESC")." LIMIT $i");

            if ($pag->requestPageCount) $pageCount = $itemsCount / $itemsPerPage;

            $before=null;
            $whereCondAfterCurs = "AND ($whereCond)";
        } else { // otherwise do as normal
            if ($after != null) {
                $vCurs = $decodeCursor($after);
                if (is_string($vCurs)) $vCurs = "'$vCurs'";
                $sql .= $isComplex ? " WHERE ".($variable->afterFunc)($vCurs,1) : " WHERE $variable>$vCurs";
            }
            if ($before != null) {
                $vCurs = $decodeCursor($before);
                if (is_string($vCurs)) $vCurs = "'$vCurs'";
                $sql .= $after != null ? " AND " : " WHERE ";
                $sql .= $isComplex ? ($variable->beforeFunc)($vCurs,2) : "$variable<$vCurs";
            }

            $whereCondAfterCurs = ($after == null && $before == null) ? "WHERE $whereCond" : "AND ($whereCond)";
            $sql .= " $whereCondAfterCurs";
            if ($pag->skipPages > 0) $whereCondAfterCurs = " AND ($whereCond)"; //??? Don't remember what it was used for

            if ($first != null && $first > 0) {
                $n = $first;
                $n2 = ($pag->skipPages > 0 ? ($first*($pag->skipPages+1)) : $n) + 1; // This last +1 is to check if there's still data left after I got what I want
                $sql .= $isComplex ? " ORDER BY ".($variable->orderBy_after)()." LIMIT $n2" : " ORDER BY $variable LIMIT $n2";
            } else if ($last != null && $last > 0) {
                $n = $last;
                $n2 = ($pag->skipPages > 0 ? ($last*($pag->skipPages+1)) : $n ) + 1;
                $sql .= $isComplex ? " ORDER BY ".($variable->orderBy_before)()." LIMIT $n2" : " ORDER BY $variable DESC LIMIT $n2";
            }

            if ($executeVals != null) {
                $stmt = $conn->prepare($sql);
                $stmt->execute($executeVals);
            } else $stmt = $conn->query($sql,\PDO::FETCH_ASSOC);
        }

        // Store results
        $result = [];
        $nResults = 0;
        $aRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $hadMoreResults = false;
        for ($i=0; $i<count($aRows); $i++) {
            if ($i < $n*$pag->skipPages) continue;

            if ($nResults == $n) { $hadMoreResults = true; break; }

            $row = $aRows[$i];

            $cursor = $makeCursor($row);
            $v = ['data' => $row, 'metadata' => ['fromDb' => $dbName]];
            $storeOne($v,$cursor);

            $refRow =& $v;
            if (count($result) === 0) $startCursor = $cursor;
            $endCursor = $cursor;
            $result[] = ['edge' => $refRow, 'cursor' => $cursor];
            $nResults++;
        }
        if ($last != null && $nResults > 0) {
            $result = array_reverse($result);
            $startCursor = $result[0]['cursor'];
            $endCursor = $result[$nResults-1]['cursor'];
        }

        /* Set some vals according to the result
        (if it used skipPages, cuz it alters the normal behavior of the function,
        I'm making it look like a normal operation so that the following operations don't have to adapt) */
        if ($pag->skipPages > 0 && $nResults>0) {
            $after = $makeCursor($result[0]['edge']['data']);
            if (!$getLastPage) $before = $makeCursor($result[count($result)-1]['edge']['data']);
        }

        // Set hasPreviousPage and hasNextPage
        if ($nResults > 0) {
            if ($after != null) {
                $vCurs = $decodeCursor($after);
                if (is_string($vCurs)) $vCurs = "'$vCurs'";
                $where1 = ($isComplex ? ($variable->countBeforeFirstItem)($vCurs) : "$variable<=$vCurs") . " $whereCondAfterCurs";
            }
            if ($before != null) {
                $vCurs = $decodeCursor($before);
                if (is_string($vCurs)) $vCurs = "'$vCurs'";
                $where2 = ($isComplex ? ($variable->countAfterLastItem)($vCurs) : "$variable>=$vCurs") . " $whereCondAfterCurs";
            }
            if ($after == null && $before == null) $where1 = $where2 = $whereCond;

            $hasPreviousPage = false;
            $hasNextPage = false;
            if ($last != null && $hadMoreResults) $hasPreviousPage = true;
            else if ($after != null) {
                if ($executeVals_filteredForWhere != null) {
                    $stmt = $conn->prepare("SELECT 1 FROM $dbLoc WHERE $where1 LIMIT 1");
                    $stmt->execute($executeVals_filteredForWhere);
                    $hasPreviousPage = $stmt->fetch() !== false;
                } else $hasPreviousPage = $conn->query("SELECT 1 FROM $dbLoc WHERE $where1 LIMIT 1")->fetch() !== false;
            }
            if ($first != null && $hadMoreResults) $hasNextPage = true;
            else if ($before != null) {
                if ($executeVals_filteredForWhere != null) {
                    $stmt = $conn->prepare("SELECT 1 FROM $dbLoc WHERE $where2 LIMIT 1");
                    $stmt->execute($executeVals_filteredForWhere);
                    $hasNextPage = $stmt->fetch() !== false;
                } else $hasNextPage = $conn->query("SELECT 1 FROM $dbLoc WHERE $where2 LIMIT 1")->fetch() !== false;
            }
        }

        if ($pag->requestPageCount == true) {
            // Set pageCount & itemsCount
            if (!$getLastPage) {
                if ($executeVals_filteredForWhere != null) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $dbLoc WHERE $whereCond");
                    $stmt->execute($executeVals_filteredForWhere);
                    $itemsCount = $stmt->fetch(\PDO::FETCH_NUM)[0];
                } else {
                    $itemsCount = $conn->query("SELECT COUNT(*) FROM $dbLoc WHERE $whereCond")->fetch(\PDO::FETCH_NUM)[0];
                }
                $pageCount = $itemsCount / $n;
            }
            $pageCount = (int)((fmod($pageCount,1) > 0) ? $pageCount+1 : $pageCount);
            if ($pageCount < 1) $pageCount = 1;

            // Set currPage
            if ($getLastPage) $currPage = $pageCount;
            else if ($nResults == 0) $currPage = 1;
            else if ($after == null && $before == null) $currPage = $first != null ? 1 : $pageCount;
            else {
                try {
                    $data = $result[0]['edge']['data'];
                    $vCurs2 = $decodeCursor($makeCursor($data));
                    if (is_string($vCurs2)) $vCurs2 = "'$vCurs2'";

                    $s = $isComplex ? "($whereCond) AND ".($variable->countBeforeFirstItem)($vCurs2) : "($whereCond) AND $variable<=$vCurs2";

                    if ($executeVals_filteredForWhere != null) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM $dbLoc WHERE $s");
                        $stmt->execute($executeVals_filteredForWhere);
                        $nItemsBefore = $stmt->fetch(\PDO::FETCH_NUM)[0];
                    } else $nItemsBefore = $conn->query("SELECT COUNT(*) FROM $dbLoc WHERE $s")->fetch(\PDO::FETCH_NUM)[0];

                    $currPage = ceil($nItemsBefore / $n);
                } catch (\Exception $e) { Logger::logThrowable($e); }
            }
        }

        $storeAll([
            'data' => $result,
            'metadata' => [
                'fromDb' => $dbName,
                'pageInfo' => new PageInfo($startCursor??null,$endCursor??null,$hasPreviousPage??false,$hasNextPage??false,$pageCount,$currPage,$itemsCount??null)
            ]
        ]);
    }
}

class ComplexVariable {
    public function __construct(
        public \Closure $afterFunc,
        public \Closure $beforeFunc,
        public \Closure $orderBy_after,
        public \Closure $orderBy_before,
        public \Closure $countBeforeFirstItem,
        public \Closure $countAfterLastItem
    ) { }
}
?>