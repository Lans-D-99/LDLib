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
namespace LDLib\Security;

use LDLib\Cache\LDValkey;
use LDLib\Database\LDPDO;
use LDLib\OperationResult;
use LDLib\SuccessType;

class Security {
    public static function isRequestsLimitReached(string $remoteAddress, ?LDPDO $pdo=null, ?LDValkey $valkey=null):bool {
        $autoCloseValkey = $valkey === null;
        $autoClosePDO = $pdo === null;

        $valkey ??= new LDValkey();
        $key = "security:requestslimit:anonymous:$remoteAddress";
        $val = $valkey->get($key);
        $limitReached = false;

        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_total_requests WHERE remote_address=? AND count>?');
            $stmt->execute([$remoteAddress,(int)$_SERVER['LD_SEC_BASE_REQUESTS_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $valkey->set($key,$limitReached ? '1' : '0',['EX' => 5]);
        }

        if ($autoCloseValkey) $valkey->valkey->close();
        return $limitReached;
    }

    public static function isRequestsLimitReached_Users(int $userId, ?LDPDO $pdo=null, ?LDValkey $valkey=null):bool {
        $autoCloseValkey = $valkey === null;
        $autoClosePDO = $pdo === null;

        $valkey ??= new LDValkey();
        $key = "security:requestslimit:users:$userId";
        $val = $valkey->get($key);
        $limitReached = false;

        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_users_total_requests WHERE user_id=? AND count>?');
            $stmt->execute([$userId,(int)$_SERVER['LD_SEC_USERS_REQUESTS_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $valkey->set($key,$limitReached ? '1' : '0',['EX' => 300]);
        }

        if ($autoCloseValkey) $valkey->valkey->close();
        return $limitReached;
    }

    public static function isQueryComplexityLimitReached(string $remoteAddress, ?LDPDO $pdo=null, ?LDValkey $valkey=null):bool {
        $autoCloseValkey = $valkey === null;
        $autoClosePDO = $pdo === null;

        $valkey ??= new LDValkey();
        $key = "security:apilimit:anonymous:$remoteAddress";
        $val = $valkey->get($key);
        $limitReached = false;

        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_query_complexity_usage WHERE remote_address=? AND complexity_used>?');
            $stmt->execute([$remoteAddress,(int)$_SERVER['LD_SEC_BASE_QUERY_COMP_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $valkey->set($key,$limitReached ? '1' : '0',['EX' => 5]);
        }

        if ($autoCloseValkey) $valkey->valkey->close();
        return $limitReached;
    }

    public static function isQueryComplexityLimitReached_Users(int $userId, ?LDPDO $pdo=null, ?LDValkey $valkey=null):bool {
        $autoCloseValkey = $valkey === null;
        $autoClosePDO = $pdo === null;

        $valkey ??= new LDValkey();
        $key = "security:apilimit:users:$userId";
        $val = $valkey->get($key);
        $limitReached = false;

        if ($val === '1') $limitReached = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_users_query_complexity_usage WHERE user_id=? AND complexity_used>?');
            $stmt->execute([$userId,(int)$_SERVER['LD_SEC_USERS_QUERY_COMP_LIMIT']]);
            $limitReached = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $valkey->set($key,$limitReached ? '1' : '0',['EX' => 60]);
        }

        if ($autoCloseValkey) $valkey->valkey->close();
        return $limitReached;
    }

    public static function isIPBanned(string $remoteAddress, ?LDPDO $pdo=null, ?LDValkey $valkey=null):bool {
        $autoCloseValkey = $valkey === null;
        $autoClosePDO = $pdo === null;

        $valkey ??= new LDValkey();
        $key = "security:ip_bans:$remoteAddress";
        $val = $valkey->get($key);
        $banned = false;

        if ($val === '1') $banned = true;
        else if ($val === null) {
            $pdo ??= new LDPDO();
            $stmt = $pdo->prepare('SELECT * FROM sec_ip_bans WHERE remote_address=? LIMIT 1');
            $stmt->execute([$remoteAddress]);
            $banned = $stmt->fetch() !== false;
            if ($autoClosePDO) $pdo = null;
            $valkey->set($key,$banned ? '1' : '0',['EX' => $banned ? 86400*7 : 3600*4]);
        }

        if ($autoCloseValkey) $valkey->valkey->close();
        return $banned;
    }

    public static function uncacheIPBan(string $remoteAddress, ?LDValkey $valkey=null) {
        $autoCloseValkey = $valkey === null;
        $valkey ??= new LDValkey();
        $res = $valkey->del("security:ip_bans:$remoteAddress");
        if ($autoCloseValkey) $valkey->valkey->close();
        return $res;
    }

    public static function reportSusIP(string $remoteAddress, int $points, string $reason, ?LDPDO $pdo=null):OperationResult {
        $autoClosePDO = $pdo === null;
        $pdo ??= new LDPDO();

        $stmt = $pdo->prepare('INSERT INTO sec_sus_ip(remote_address,date,points,reason) VALUES (?,?,?,?)');
        $stmt->execute([$remoteAddress,(new \DateTime('now'))->format('Y-m-d H:i:s'),$points,$reason]);
        if ($autoClosePDO) $pdo->close();
        return new OperationResult(SuccessType::SUCCESS);
    }
}
?>