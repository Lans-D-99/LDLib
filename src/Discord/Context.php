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
namespace LDLib\Discord\Context;

use LDLib\Database\LDPDO;
use LDLib\Discord\Auth\RegisteredDiscordUser;
use LDLib\Discord\Discord;
use LDLib\Discord\InteractionCallbackType;
use LDLib\Discord\MessageFlags;
use LDLib\Discord\Objects\ApplicationCommandInteractionData;
use LDLib\Discord\Objects\ApplicationCommandInteractionDataOption;
use LDLib\Discord\Objects\Channel;
use LDLib\Discord\Objects\CreateMessage;
use LDLib\Discord\Objects\GuildMember;
use LDLib\Discord\Objects\Interaction;
use LDLib\Discord\Objects\Message;
use LDLib\Discord\Objects\MessageComponentInteractionData;
use LDLib\Discord\Objects\MessageReactionAdd;
use LDLib\Discord\Objects\MessageReactionRemove;
use LDLib\Discord\Objects\ModalSubmitInteractionData;
use LDLib\Discord\Objects\VoiceState;
use Swoole\WebSocket\Frame;

class Context {
    public array $raw;
    public RegisteredDiscordUser|false|null $user;

    public function __construct(public Discord $client, public Frame $frame) {
        $this->raw = json_decode($frame->data,true);
        $this->user = null;
    }
}

class GuildMemberAddContext extends Context {
    public GuildMember $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = GuildMember::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->user->id);
    }
}
class GuildMemberRemoveContext extends Context {
    public GuildMember $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = GuildMember::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->user->id);
    }
}
class GuildMemberUpdateContext extends Context {
    public GuildMember $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = GuildMember::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->user->id);
    }
}

class ThreadCreateContext extends Context {
    public Channel $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = Channel::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->ownerId);
    }
}
class VoiceStateUpdateContext extends Context {
    public VoiceState $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = VoiceState::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->userId);
    }
}

class MessageCreateContext extends Context {
    public Message $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = Message::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->author->id);
    }
}
class MessageUpdateContext extends Context {
    public Message $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = Message::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->author->id);
    }
}

class MessageReactionAddContext extends Context {
    public MessageReactionAdd $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = MessageReactionAdd::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->userId);
    }
}
class MessageReactionRemoveContext extends Context {
    public MessageReactionRemove $o;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = MessageReactionRemove::fromArray($this->raw['d']);
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->userId);
    }
}

class InteractionContext extends Context {
    public Interaction $o;
    public ?string $customId=null;
    public array $args;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client,$frame);
        $this->o = Interaction::fromArray($this->raw['d']);
        if ($this->o->data instanceof MessageComponentInteractionData || $this->o->data instanceof ModalSubmitInteractionData) {
            $pos = mb_strpos($this->o->data->customId,':');
            $this->customId = $pos === false ? $this->o->data->customId : substr($this->o->data->customId,0,$pos);
            $this->args = $pos === false ? [] : explode(',',substr($this->o->data->customId,$pos+1));
        }
        if (isset($pdo)) $this->user = RegisteredDiscordUser::initFromDiscordId($pdo,$this->o->user?->id??$this->o->member?->user->id);
    }

    public function respondToInteraction(array $data, InteractionCallbackType $cbType=InteractionCallbackType::CHANNEL_MESSAGE_WITH_SOURCE) {
        $this->client->api_createInteractionResponse($this->o->id,$this->o->token,$cbType,$data);
    }

    public function respondToInteractionQuick(string $content, int $flags=MessageFlags::EPHEMERAL->value, InteractionCallbackType $cbType=InteractionCallbackType::CHANNEL_MESSAGE_WITH_SOURCE) {
        $msg = new CreateMessage($flags,$content);
        $this->client->api_createInteractionResponse($this->o->id,$this->o->token,$cbType,$msg->toArray());
    }
}

class MsgComponentResponseContext extends InteractionContext { }
class SlashCommandContext extends InteractionContext { }
class ModalResponseContext extends InteractionContext { }

class AutocompleteResponseContext extends InteractionContext {
    public ?ApplicationCommandInteractionDataOption $focusedOption=null;

    public function __construct(Discord $client, Frame $frame, ?LDPDO $pdo=null) {
        parent::__construct($client, $frame);
        assert($this->o->data instanceof ApplicationCommandInteractionData);
        foreach ($this->o->data->options as $opt) if ($opt->focused === true) { $this->focusedOption = $opt; break; }
    }
}
?>