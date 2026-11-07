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
namespace LDLib\Discord\Fetcher;

use LDLib\Cache\LDValkey;
use LDLib\Database\LDPDO;
use LDLib\Discord\ComponentType;
use LDLib\Discord\LabelChildComponent;
use LDLib\Discord\Objects\ModalSubmitInteractionData;

class CommandContext {
    public string $id;
    public array $data = [];

    public function __construct() { }

    public static function new(string $name) {
        $o = new self();
        $t = microtime(true);
        $x = bin2hex(random_bytes(4));
        $o->id = "{$name}*{$t}*{$x}";
        return $o;
    }

    public function save(LDValkey $valkey, int $ttl=3600*48) {
        return $valkey->set($this->id,json_encode($this->data),['EX' => $ttl]);
    }

    public function deleteSave(LDValkey $valkey) {
        return $valkey->del($this->id);
    }

    public function populateFromModalResult(ModalSubmitInteractionData $interactionData) {
        foreach ($interactionData->components as $c) {
            if ($c['type'] !== ComponentType::LABEL->value) continue;
            $comp = $c['component'];
            switch (LabelChildComponent::tryFrom($comp['type'])) {
                case LabelChildComponent::STRING_SELECT:
                    $vCount = count(['values']);
                    $val = null;
                    if ($vCount > 1) {
                        $val = [];
                        foreach ($comp['values'] as $v) $val[] = $v;
                    } else if ($vCount === 1) { $val = $comp['values'][0]; }
                    $this->data[$comp['custom_id']] = $val;
                    break;
                case LabelChildComponent::TEXT_INPUT:
                    $this->data[$comp['custom_id']] = $comp['value'];
                    break;
            }
        }
    }

    public static function fromId(LDValkey $valkey, string $id) {
        $o = new self();
        $o->id = $id;
        $v = $valkey->get($id);
        $o->data = $v == null ? [] : json_decode($v,true);
        return $o;
    }
}

class Fetcher {
    public static function getUserWithId(LDPDO $pdo, int $id) {
        return $pdo->query("SELECT * FROM users WHERE id=$id")->fetch();
    }

    public static function getUserWithDiscordId(LDPDO $pdo, string $discUserId) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE discord_id=?');
        $stmt->execute([$discUserId]);
        return $stmt->fetch();
    }

    public static function getServersWithMemberId(LDPDO $pdo, string $discUserId) {
        $stmt = $pdo->prepare('SELECT server_id FROM servers_members WHERE user_discord_id=?');
        $stmt->execute([$discUserId]);
        return $stmt->fetchAll();
    }

    public static function getServersWithUserId(LDPDO $pdo, int $userId) {
        $res = $pdo->query("SELECT discord_id FROM users WHERE id=$userId")->fetch();
        if ($res == false) return [];
        return self::getServersWithMemberId($pdo,$res['discord_id']);
    }

    public static function getCachedServerRoles(LDPDO $pdo, LDValkey $valkey, string $serverId) {
        $key = "serverRoles:$serverId";
        $res = $valkey->get($key);
        if ($res == null) {
            $stmt = $pdo->prepare('SELECT * FROM server_roles WHERE server_id=?');
            $stmt->execute([$serverId]);
            $res = $stmt->fetchAll();
            if (count($res) !== 0) $valkey->set($key,json_encode($res),['EX' => 86400]);
        } else $res = json_decode($res,true);
        return $res;
    }

    public static function forgetServerRoles(LDValkey $valkey, string $serverId) {
        return $valkey->del("serverRoles:$serverId");
    }
}
?>