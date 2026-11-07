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
namespace LDLib\Discord\Auth;

use Ds\Set;
use LDLib\Cache\LDValkey;
use LDLib\Database\LDPDO;
use LDLib\Discord\Discord;
use LDLib\Discord\Fetcher\Fetcher;
use LDLib\User\User;

class RegisteredDiscordUser extends User {
    public ?array $c_discordRoles = null;

    public function __construct(
        int $id, public string $discId, Set $titles, string $username, public \DateTimeImmutable $registrationDate,
        public array $settings, public ?string $dmChannelId = null, public array $extraData=[]
    ) {
        parent::__construct($id,$titles,$username);
    }

    public function hasDiscordRoleWithDBName(LDPDO $pdo, LDValkey $valkey, string $guildId, string $roleName):bool {
        $serverRoles = Fetcher::getCachedServerRoles($pdo,$valkey,$guildId);
        foreach ($serverRoles as $r) if ($r['name'] === $roleName) {
            if (array_find($this->c_discordRoles[$guildId]??[],fn($v) => $v === $r['id']) !== null) return true;
        }
        return false;
    }

    public function hasDiscordRoleWithId(string $guildId, string $roleId, ?Discord $client=null):bool {
        if (!isset($this->c_discordRoles[$guildId]) && $client != null) $this->loadDiscordRoles($client,$guildId);

        return array_find($this->c_discordRoles[$guildId]??[],fn($v) => $v === $roleId) !== null;
    }

    public function loadDiscordRoles(Discord $client, string $guildId) {
        $v = $client->api_getGuildMember($guildId,$this->discId);
        if ($v['httpCode'] === 200) {
            $this->c_discordRoles[$guildId] = json_decode($v['res'],true)['roles'];
            return true;
        }
        return false;
    }

    public function sendDM(Discord $disc, array $data) {
        $disc->api_createMessage($this->dmChannelId, $data);
    }

    public static function initFromRow(array $row) {
        $data = array_key_exists('data',$row) && array_key_exists('metadata',$row) ? $row['data'] : $row;
        return new self(
            $data['id'],$data['discord_id'],new Set(explode(',',$data['roles'])),$data['name'],new \DateTimeImmutable($data['registration_date']),
            json_decode($data['settings'],true),$data['dm_channel_id'],json_decode($data['extra_data'],true)
        );
    }

    public static function initFromDiscordId(LDPDO $pdo, string $discordId):self|false {
        $rowUser = Fetcher::getUserWithDiscordId($pdo,$discordId);
        if ($rowUser === false) return false;
        return self::initFromRow($rowUser);
    }
}
?>