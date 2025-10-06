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
namespace LDLib\Discord;

use LDLib\Cache\LDValkey;
use LDLib\Client\WSClient;
use LDLib\Database\LDPDO;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use Swoole\Coroutine;

use function LDLib\Net\curl_quickRequest;

enum Intents:int {
    case GUILDS = 1;
    case GUILD_MEMBERS = 1 << 1;
    case GUILD_MODERATION = 1 << 2;
    case GUILD_EXPRESSIONS = 1 << 3;
    case GUILD_INTEGRATIONS = 1 << 4;
    case GUILD_WEBHOOKS = 1 << 5;
    case GUILD_INVITES = 1 << 6;
    case GUILD_VOICE_STATES = 1 << 7;
    case GUILD_PRESENCES = 1 << 8;
    case GUILD_MESSAGES = 1 << 9;
    case GUILD_MESSAGE_REACTIONS = 1 << 10;
    case GUILD_MESSAGE_TYPING = 1 << 11;
    case DIRECT_MESSAGES = 1 << 12;
    case DIRECT_MESSAGE_REACTIONS = 1 << 13;
    case DIRECT_MESSAGE_TYPING = 1 << 14;
    case MESSAGE_CONTENT = 1 << 15;
    case GUILD_SCHEDULED_EVENTS = 1 << 16;
    case AUTO_MODERATION_CONFIGURATION = 1 << 20;
    case AUTO_MODERATION_EXECUTION = 1 << 21;
    case GUILD_MESSAGE_POLLS = 1 << 24;
    case DIRECT_MESSAGE_POLLS = 1 << 25;
}

enum Permissions:int {
    case CREATE_INSTANT_INVITE = 1;
    case KICK_MEMBERS = 1 << 1;
    case BAN_MEMBERS = 1 << 2;
    case ADMINISTRATOR = 1 << 3;
    case MANAGE_CHANNELS = 1 << 4;
    case MANAGE_GUILD = 1 << 5;
    case ADD_REACTIONS = 1 << 6;
    case VIEW_AUDIT_LOG = 1 << 7;
    case PRIORITY_SPEAKER = 1 << 8;
    case STREAM = 1 << 9;
    case VIEW_CHANNEL = 1 << 10;
    case SEND_MESSAGES = 1 << 11;
    case SEND_TTS_MESSAGES = 1 << 12;
    case MANAGE_MESSAGES = 1 << 13;
    case EMBED_LINKS = 1 << 14;
    case ATTACH_FILES = 1 << 15;
    case READ_MESSAGE_HISTORY = 1 << 16;
    case MENTION_EVERYONE = 1 << 17;
    case USE_EXTERNAL_EMOJIS = 1 << 18;
    case VIEW_GUILD_INSIGHTS = 1 << 19;
    case CONNECT = 1 << 20;
    case SPEAK = 1 << 21;
    case MUTE_MEMBERS = 1 << 22;
    case DEAFEN_MEMBERS = 1 << 23;
    case MOVE_MEMBERS = 1 << 24;
    case USE_VAD = 1 << 25;
    case CHANGE_NICKNAME = 1 << 26;
    case MANAGE_NICKNAMES = 1 << 27;
    case MANAGE_ROLES = 1 << 28;
    case MANAGE_WEBHOOKS = 1 << 29;
    case MANAGE_GUILD_EXPRESSIONS = 1 << 30;
    case USE_APPLICATION_COMMANDS = 1 << 31;
    case REQUEST_TO_SPEAK = 1 << 32;
    case MANAGE_EVENTS = 1 << 33;
    case MANAGE_THREADS = 1 << 34;
    case CREATE_PUBLIC_THREADS = 1 << 35;
    case CREATE_PRIVATE_THREADS = 1 << 36;
    case USE_EXTERNAL_STICKERS = 1 << 37;
    case SEND_MESSAGES_IN_THREADS = 1 << 38;
    case USE_EMBEDDED_ACTIVITIES = 1 << 39;
    case MODERATE_MEMBERS = 1 << 40;
    case VIEW_CREATOR_MONETIZATION_ANALYTICS = 1 << 41;
    case USE_SOUNDBOARD = 1 << 42;
    case CREATE_GUILD_EXPRESSIONS = 1 << 43;
    case CREATE_EVENTS = 1 << 44;
    case USE_EXTERNAL_SOUNDS = 1 << 45;
    case SEND_VOICE_MESSAGES = 1 << 46;
    case SEND_POLLS = 1 << 49;
    case USE_EXTERNAL_APPS = 1 << 50;
}

enum InteractionType:int {
    case PING = 1;
    case APPLICATION_COMMAND = 2;
    case MESSAGE_COMPONENT = 3;
    case APPLICATION_COMMAND_AUTOCOMPLETE = 4;
    case MODAL_SUBMIT = 5;
}

enum InteractionCallbackType:Int {
    case PONG = 1;
    case CHANNEL_MESSAGE_WITH_SOURCE = 4;
    case DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE = 5;
    case DEFERRED_UPDATE_MESSAGE = 6;
    case UPDATE_MESSAGE = 7;
    case APPLICATION_COMMAND_AUTOCOMPLETE_RESULT = 8;
    case MODAL = 9;
    case PREMIUM_REQUIRED = 10;
    case LAUNCH_ACTIVITY = 12;
}

enum ComponentType:Int {
    case ACTION_ROW = 1;
    case BUTTON = 2;
    case STRING_SELECT = 3;
    case TEXT_INPUT = 4;
    case USER_SELECT = 5;
    case ROLE_SELECT = 6;
    case MENTIONABLE_SELECT = 7;
    case CHANNEL_SELECT = 8;
    case SECTION = 9;
    case TEXT_DISPLAY = 10;
    case THUMBNAIL = 11;
    case MEDIA_GALLERY = 12;
    case FILE = 13;
    case SEPARATOR = 14;
    case CONTAINER = 17;
    case LABEL = 18;
}

enum ActionRowChildComponent:Int {
    case BUTTON = 2;
    case STRING_SELECT = 3;
    case USER_SELECT = 5;
    case ROLE_SELECT = 6;
    case MENTIONABLE_SELECT = 7;
    case CHANNEL_SELECT = 8;
}

enum ContainerChildComponent:Int {
    case ACTION_ROW = 1;
    case SECTION = 9;
    case TEXT_DISPLAY = 10;
    case MEDIA_GALLERY = 12;
    case SEPARATOR = 14;
    case FILE = 13;
}

enum LabelChildComponent:Int {
    case TEXT_INPUT = 4;
    case STRING_SELECT = 3;
    case USER_SELECT = 5;
    case ROLE_SELECT = 6;
    case MENTIONABLE_SELECT = 7;
    case CHANNEL_SELECT = 8;
}

enum TextInputStyle:Int {
    case SHORT = 1;
    case PARAGRAPH = 2;
}

enum ApplicationCommandOptionType:Int {
    case SUB_COMMAND = 1;
    case SUB_COMMAND_GROUP = 2;
    case STRING = 3;
    case INTEGER = 4;
    case BOOLEAN = 5;
    case USER = 6;
    case CHANNEL = 7;
    case ROLE = 8;
    case MENTIONABLE = 9;
    case NUMBER = 10;
    case ATTACHMENT = 11;
}

enum MessageFlags:Int {
    case CROSSPOSTED = 1;
    case IS_CROSSPOST = 1 << 1;
    case SUPPRESS_EMBEDS = 1 << 2;
    case SOURCE_MESSAGE_DELETED = 1 << 3;
    case URGENT = 1 << 4;
    case HAS_THREAD = 1 << 5;
    case EPHEMERAL = 1 << 6;
    case LOADING = 1 << 7;
    case FAILED_TO_MENTION_SOME_ROLES_IN_THREAD = 1 << 8;
    case SUPPRESS_NOTIFICATIONS = 1 << 12;
    case IS_VOICE_MESSAGE = 1 << 13;
    case HAS_SNAPSHOT = 1 << 14;
    case IS_COMPONENTS_V2 = 1 << 15;
}

class Discord {
    public string $botId;

    public ?string $wssURL = null;
    public ?string $connectURL = null;
    public WSClient $wsClient;

    public bool $isIdentified = false;
    public ?string $resumeGatewayURL = null;
    public ?string $sessionID = null;
    public ?int $lastSequenceNumber = null;

    public array $queued = [];

    public \Closure $onMessageCreate;
    public \Closure $onInteractionCreate;

    public function __construct(
        public string $botToken,
        public LDValkey $valkey,
        public int $intents=0,
        public string $apiUrl="https://discord.com/api/v10",
        public string $connectUrlPath ="/?v=10&encoding=json"
    ) {
        $this->botId = $_SERVER['BOT_ID'];
        $this->wssURL = $this->connectURL = $valkey->get('wssURL');
        $this->resumeGatewayURL = $valkey->get('resumeGatewayURL');
        $this->sessionID = $valkey->get('sessionID');
        $this->lastSequenceNumber = $valkey->get('lastSequenceNumber');
        $this->onMessageCreate = fn($o) => var_dump($o->data);
        $this->onInteractionCreate = fn($o) => var_dump($o->data);

        if ($this->wssURL == null) {
            $v = curl_quickRequest("$apiUrl/gateway/bot",[
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
            ],10)['res']??null;
            if ($v == null) throw new \Exception("Couldn't connect to gateway.");
            $json = json_decode($v,true);

            $this->wssURL = $this->connectURL = $json['url'];
            $valkey->set('wssURL',$json['url'],['EX' => 3600*24*3]);

        }
        if (isset($this->resumeGatewayURL)) {
            $this->connectURL = $this->resumeGatewayURL;
        }
    }

    public function updateCommands(array $commands):bool {
        $res = $this->api_getCurrentUserGuilds();
        if ($res['httpCode'] !== 200) {
            Logger::log(LogLevel::ERROR, 'DISCORD', "Couldn't overwrite commands: {$res['res']}");
            return false;
        }
        
        $guilds = json_decode($res['res'],true);
        foreach ($guilds as $guild) {
            $res = $this->api_overwriteGuildApplicationCommands($guild['id'],$commands);
            if ($res['httpCode'] !== 200) {
                Logger::log(LogLevel::ERROR, 'DISCORD', "Couldn't overwrite commands: {$res['res']}");
                return false;
            }
        }

        return true;
    }

    public function connect() { // returning TRUE means reconnect instead of ending the script
        $host = preg_replace('/^wss:\/\//','',$this->connectURL,1);
        $this->wsClient = new WSClient(new \Swoole\Coroutine\Http\Client($host,443,true));
        $res = $this->wsClient->connect("{$this->connectUrlPath}",false);
        if ($res !== true) return $res;

        $nextHeartbeat = null;
        $heartbeatInterval = null;
        $heartbeatReceptionMaxTime = null;
        $connectFinalizationMaxTime = null;

        // return 2 to loop immediately
        // return 1 to continue normally
        // return 0 to do reconnection
        // return -1 to end connection
        $processData = function($frame,$msTime) use(&$processData,&$nextHeartbeat,&$heartbeatInterval,&$heartbeatReceptionMaxTime,&$connectFinalizationMaxTime):int {
            $discordV = json_decode($frame->data,true);

            switch ($discordV['op']??null) {
                case 0: // Dispatch
                    $this->isIdentified = true;
                    $d = $discordV['d']??null;
                    $t = $discordV['t']??null;
                    $s = $discordV['s']??null;
                    if ($s !== null) {
                        if ($this->lastSequenceNumber == null) $this->lastSequenceNumber = $s;
                        else {
                            if ($s <= $this->lastSequenceNumber) break;
                            else if ($s === $this->lastSequenceNumber+1) $this->lastSequenceNumber = $s;
                            else {
                                Logger::log(LogLevel::WARN,'DISCORD',"Received an event to queue. (curr seq: {$this->lastSequenceNumber} · event seq: $s)");
                                $this->queued[$s] = $discordV; break;
                            }
                        }
                        $this->valkey->set('lastSequenceNumber',$this->lastSequenceNumber,['EX' => 3600]);
                    }

                    if (isset($d['resume_gateway_url'],$d['session_id'])) {
                        $this->resumeGatewayURL = $d['resume_gateway_url'];
                        $this->sessionID = $d['session_id'];
                        $this->valkey->set('resumeGatewayURL',$this->resumeGatewayURL,['EX' => 3600*24]);
                        $this->valkey->set('sessionID',$this->sessionID,['EX' => 3600*24]);
                    }

                    switch ($t) {
                        case 'READY': $connectFinalizationMaxTime = null; break;
                        case 'RESUMED': $connectFinalizationMaxTime = null; break;
                        case 'MESSAGE_CREATE': $this->onMessageCreate->call($this,$frame); break;
                        case 'INTERACTION_CREATE': $this->onInteractionCreate->call($this,$frame); break;
                        default: Logger::log(LogLevel::WARN, 'DISCORD', "Unknown gateway event: $t"); break;
                    }

                    if (isset($this->queued[$s+1])) {
                        $nextS = $s+1;
                        Logger::log(LogLevel::WARN,'DISCORD',"Processing event in queue. (curr seq: {$this->lastSequenceNumber} · event seq: {$nextS})");
                        $newFrame = $this->queued[$nextS];
                        unset($this->queued[$nextS]);
                        $msTime = round(microtime(true) * 1000);
                        $processData($newFrame,$msTime);
                    }

                    break;
                case 1: // Heartbeat
                    $nextHeartbeat = $msTime;
                    return 2;
                case 7: // Reconnect
                    $this->isIdentified = false;
                    $this->wsClient->client->close();
                    return 0;
                case 9: // Invalid Session
                    $d = $discordV['d'];
                    if ($d === true) $this->resume();
                    else {
                        $this->valkey->del(['resumeGatewayURL','sessionID']);
                        $this->resumeGatewayURL = $this->sessionID = null;
                        $this->isIdentified = false;
                        $this->wsClient->client->close();
                        return 0;
                    }
                    break;
                case 10: // Hello
                    $d = $discordV['d'];
                    $heartbeatInterval = $d['heartbeat_interval'];
                    $nextHeartbeat = $msTime + ($_SERVER['LD_LOCAL'] === '1' ? 0 : $heartbeatInterval * random_int(0,1));
                    $heartbeatReceptionMaxTime = $nextHeartbeat + 10000;
                    break;
                case 11: // Heartbeat ACK
                    $heartbeatReceptionMaxTime = null;
                    if (!$this->isIdentified) {
                        if (isset($this->resumeGatewayURL,$this->sessionID)) $this->resume();
                        else $this->identify();
                        $connectFinalizationMaxTime = $msTime + 10000;
                    }
                    break;
                default: Logger::log(LogLevel::ERROR,'DISCORD',"Unknown op code: {$discordV['op']}."); break;
            }

            return 1;
        };

        while (true) {
            $msTime = round(microtime(true) * 1000);
            if ($heartbeatReceptionMaxTime != null && $msTime >= $heartbeatReceptionMaxTime) {
                Logger::log(LogLevel::WARN, 'DISCORD', "Hearbeat wasn't received in time.");
                $this->isIdentified = false;
                $this->wsClient->client->close();
                return true;
            }
            if ($connectFinalizationMaxTime != null && $msTime >= $connectFinalizationMaxTime) {
                Logger::log(LogLevel::WARN, 'DISCORD', "Identify or Resume didn't receive a successful response in time.");
                $this->isIdentified = false;
                $this->wsClient->client->close();
                return true;
            }
            if ($nextHeartbeat != null && $msTime >= $nextHeartbeat) {
                $nextHeartbeat += $heartbeatInterval;
                $heartbeatReceptionMaxTime = $msTime + 10000;
                $d = $this->lastSequenceNumber??'null';
                Logger::log(LogLevel::TRACE, 'DISCORD', "Send heartbeat: {\"op\":1, \"d\":$d}");
                $this->wsClient->client->push("{\"op\":1, \"d\":$d}");
            }

            $frame = $this->wsClient->client->recv();
            if ($frame == null) { Coroutine::sleep(0.1); continue; }
            if ($frame->opcode === 8) {
                Logger::log(LogLevel::WARN, 'DISCORD', "Connection closed. (code: $frame?->code · reason: $frame?->reason)");
                break;
            }

            switch ($processData($frame,$msTime)) {
                case 2: continue 2;
                case 1: break;
                case 0: return true;
                case -1: return false;
                default: break;
            }

            Coroutine::sleep(0.1);
        }
    }

    public function registerRole(LDPDO $pdo, string $serverId, ?string $name="new role", ?string $permissions=null, int $color=0, bool $hoist=false, ?string $icon=null, ?string $unicodeEmoji=null, bool $mentionable=false) {
        $stmt = $pdo->prepare('SELECT * FROM server_roles WHERE server_id=? AND name=?');
        $stmt->execute([$serverId,$name]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $res = $this->api_getGuildRole($serverId,$row['id']);
            if ($res['httpCode'] !== 200) { Logger::log(LogLevel::WARN,'DISCORD',"registerRole: '$name' not found. (server_id: $serverId)"); $row = false; }
            else {
                $role = json_decode($res['res'],true);
                if ($role['name'] !== $row['name']) {
                    $this->api_modifyGuildRole($serverId,$row['id'],$row['name']);
                }
            }
        }
        if ($row === false) {
            $res = $this->api_createGuildRole($serverId,$name,$permissions,$color,$hoist,$icon,$unicodeEmoji,$mentionable);
            if ($res['httpCode'] !== 200) { Logger::log(LogLevel::ERROR,'DISCORD',"registerRole: '$name' failure. (server_id: $serverId)"); return null; }
            $role = json_decode($res['res'],true);
            $stmt = $pdo->prepare('INSERT INTO server_roles(server_id,name,id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=VALUE(id) RETURNING *');
            $stmt->execute([$serverId,$name,$role['id']]);
            $row = $stmt->fetch();
        }
        return $row;
    }

    private function identify(?int $intents=null) {
        $intents ??= $this->intents;
        $this->lastSequenceNumber = null;
        Logger::log(LogLevel::INFO,'DISCORD',"Send IDENTIFY event. (intents: $intents)");
        return $this->wsClient->client->push(json_encode([
            'op' => 2,
            'd' => [
                'token' => $this->botToken??'null',
                'intents' => $intents,
                'properties' => [
                    'os' => 'linux',
                    'browser' => 'ldlib',
                    'device' => 'ldlib'
                ]
            ]
        ]));
    }

    private function resume() {
        Logger::log(LogLevel::INFO,'DISCORD',"Send RESUME event. (sessionId: {$this->sessionID} · seq: {$this->lastSequenceNumber})");
        return $this->wsClient->client->push(json_encode([
            'op' => 6,
            'd' => [
                'token' => $this->botToken??'null',
                'session_id' => $this->sessionID??'null',
                'seq' => $this->lastSequenceNumber??'null'
            ]
        ]));
    }

    public function api_getCurrentUserGuilds() {
        $res = curl_quickRequest("{$this->apiUrl}/users/@me/guilds",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "api_getCurrentUserGuilds : unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_createDM(string $recipientId) {
        $res = curl_quickRequest("{$this->apiUrl}/users/@me/channels",[
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode([
                'recipient_id' => $recipientId
            ], JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "api_createDM : unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_createMessage(string $channelId, array $data) {
        $res = curl_quickRequest("{$this->apiUrl}/channels/{$channelId}/messages",[
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "api_createMessage : unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_getGuildMember(string $serverId, string $userId) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/members/$userId",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "getGuildMember '$serverId'-'$userId': unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_addGuildMemberRole(string $serverId, string $userId, string $roleId) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/members/$userId/roles/$roleId",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 204) Logger::log(LogLevel::ERROR, 'DISCORD', "addGuildMemberRole '$userId'-'$roleId': unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_removeGuildMemberRole(string $serverId, string $userId, string $roleId) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/members/$userId/roles/$roleId",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 204) Logger::log(LogLevel::ERROR, 'DISCORD', "addGuildMemberRole '$userId'-'$roleId': unexpected http code {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_getGuildRole(string $serverId, string $roleId) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/roles/$roleId",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 200 && $res['httpCode'] !== 429) Logger::log(LogLevel::ERROR, 'DISCORD', "getGuildRole '$roleId' error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_createGuildRole(string $serverId, ?string $name="new role", ?string $permissions=null, int $color=0, bool $hoist=false, ?string $icon=null, ?string $unicodeEmoji=null, bool $mentionable=false) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/roles",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode([
                'name' => $name,
                'permissions' => $permissions,
                'color' => $color,
                'hoist' => $hoist,
                'icon' => $icon,
                'unicode_emoji' => $unicodeEmoji,
                'mentionable' => $mentionable
            ], JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "createGuildRole error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_modifyGuildRole(string $serverId, string $roleId, ?string $name=null, ?string $permissions=null, ?int $color=null, ?bool $hoist=null, ?string $icon=null, ?string $unicodeEmoji=null, ?bool $mentionable=null) {
        $res = curl_quickRequest("{$this->apiUrl}/guilds/$serverId/roles/$roleId",[
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode([
                'name' => $name,
                'permissions' => $permissions,
                'color' => $color,
                'hoist' => $hoist,
                'icon' => $icon,
                'unicode_emoji' => $unicodeEmoji,
                'mentionable' => $mentionable
            ], JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "modifyGuildRole '$serverId'-'$roleId': unexpected httpCode {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_overwriteGuildApplicationCommands(string $serverId, array $commands) {
        $res = curl_quickRequest("{$this->apiUrl}/applications/{$this->botId}/guilds/$serverId/commands",[
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode($commands, JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "overwriteGuildApplicationCommands error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_createInteractionResponse(string $interactionId, string $interactionToken, InteractionCallbackType $type, array $data) {
        $res = curl_quickRequest("{$this->apiUrl}/interactions/$interactionId/$interactionToken/callback?with_response=true",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode([
                'type' => $type->value,
                'data' => $data
            ], JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "createInteractionResponse error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_editOriginalInteractionResponse(string $interactionToken, array $data) {
        $res = curl_quickRequest("{$this->apiUrl}/webhooks/{$this->botId}/$interactionToken/messages/@original",[
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "editOriginalInteractionResponse error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_createFollowupMessage(string $interactionToken, array $data, $wait=false, $withComponents=false) {
        $bWait = $wait ? 'true' : 'false';
        $bComponents = $withComponents ? 'true' : 'false';
        $res = curl_quickRequest("{$this->apiUrl}/webhooks/{$this->botId}/$interactionToken?wait=$bWait&with_components=$bComponents",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "createFollowupMessage error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_getFollowupMessage(string $interactionToken, string $messageId) {
        $res = curl_quickRequest("{$this->apiUrl}/webhooks/{$this->botId}/$interactionToken/messages/$messageId",[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"]
        ],10);
        if ($res['httpCode'] !== 200 && $res['httpCode'] !== 429) Logger::log(LogLevel::ERROR, 'DISCORD', "getFollowupMessage '$messageId' error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_editFollowupMessage(string $interactionToken, string $messageId, array $data) {
        $res = curl_quickRequest("{$this->apiUrl}/webhooks/{$this->botId}/$interactionToken/messages/$messageId",[
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bot {$this->botToken}"],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR)
        ],10);
        if ($res['httpCode'] !== 200) Logger::log(LogLevel::ERROR, 'DISCORD', "editFollowupMessage '$messageId' error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public function api_deleteFollowupMessage(string $interactionToken, string $messageId) {
        $res = curl_quickRequest("{$this->apiUrl}/webhooks/{$this->botId}/$interactionToken/messages/$messageId",[
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$this->botToken}"],
        ],10);
        if ($res['httpCode'] !== 204) Logger::log(LogLevel::ERROR, 'DISCORD', "deleteFollowupMessage '$messageId' error {$res['httpCode']}: {$res['res']}");
        return $res;
    }

    public static function msgEscapeCharacters(string $s) {
        return str_replace(['*','/','#','\\'],['\*','\/','\#','\\\\'],$s);
    }
}
?>