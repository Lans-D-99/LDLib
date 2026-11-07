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
namespace LDLib\Discord\Functionality;

use LDLib\Discord\ApplicationCommandOptionType;
use LDLib\Discord\ApplicationCommandType;
use Swoole\Timer;

enum EntryPointCommandHandlerType:int {
    case APP_HANDLER = 1;
    case DISCORD_LAUNCH_ACTIVITY = 2;
}

class Functionalities {
    /** @var GlobalSlashCommand[] */
    public array $globalSlashCommands = [];
    /** @var GlobalUserCommand[] */
    public array $globalUserCommands = [];
    /** @var GlobalMessageCommand[] */
    public array $globalMessageCommands = [];
    /** @var GlobalPrimaryEntryPointCommand[] */
    public array $globalPrimaryEntryPointCommands = [];

    /** @var GuildSlashCommand[] */
    public array $guildSlashCommands = [];
    /** @var GuildUserCommand[] */
    public array $guildUserCommands = [];
    /** @var GuildMessageCommand[] */
    public array $guildMessageCommands = [];

    /** @var MsgComponentResponse[] */
    public array $msgComponentResponses = [];
    /** @var ModalResponse[] */
    public array $modalResponses = [];
    /** @var AutocompleteResponse[] */
    public array $autocompleteResponses = [];

    /** @var Timer[] */
    public array $periodicChecks = [];

    public function addGlobalSlashCommand(GlobalSlashCommand $cmd) { return $this->globalSlashCommands[$cmd->name] = $cmd; }
    public function addGlobalUserCommand(GlobalUserCommand $cmd) { return $this->globalUserCommands[$cmd->name] = $cmd; }
    public function addGlobalMessageCommand(GlobalMessageCommand $cmd) { return $this->globalMessageCommands[$cmd->name] = $cmd; }
    public function addGlobalPrimaryEntryPointCommand(GlobalPrimaryEntryPointCommand $cmd) { return $this->globalPrimaryEntryPointCommands[$cmd->name] = $cmd; }

    public function addGuildSlashCommand(GuildSlashCommand $cmd) { return $this->guildSlashCommands[$cmd->name] = $cmd; }
    public function addGuildUserCommand(GuildUserCommand $cmd) { return $this->guildUserCommands[$cmd->name] = $cmd; }
    public function addGuildMessageCommand(GuildMessageCommand $cmd) { return $this->guildMessageCommands[$cmd->name] = $cmd; }
    public function getGuildSlashCommand(string $name):?GuildSlashCommand { return $this->guildSlashCommands[explode(' ',$name,1)[0]]??null; }

    public function addMsgComponentResponse(MsgComponentResponse $res) { return $this->msgComponentResponses[$res->name] = $res; }
    public function addModalResponse(ModalResponse $res) { return $this->modalResponses[$res->name] = $res; }
    public function addAutocompleteResponse(AutocompleteResponse $res) { return $this->autocompleteResponses[$res->name] = $res; }

    public function getMsgComponentResponse(string $name):?MsgComponentResponse { return $this->msgComponentResponses[$name]??null; }
    public function getModalResponse(string $name):?ModalResponse { return $this->modalResponses[$name]??null; }
    public function getAutocompleteResponse(string $name):?AutocompleteResponse { return $this->autocompleteResponses[$name]??null; }

    public function addPeriodicCheck(int $msInterval, bool $checkNow, callable $f, mixed ...$params) {
        if ($checkNow) $f(...$params);
        $this->periodicChecks[] = Timer::tick($msInterval,$f,$params);
    }

    /** @var callable[] */
    public array $receivers_onGuildMemberAdd = [];
    /** @var callable[] */
    public array $receivers_onGuildMemberRemove = [];
    /** @var callable[] */
    public array $receivers_onGuildMemberUpdate = [];
    /** @var callable[] */
    public array $receivers_onMessageCreate = [];
    /** @var callable[] */
    public array $receivers_onMessageReactionAdd = [];
    /** @var callable[] */
    public array $receivers_onMessageReactionRemove = [];
    /** @var callable[] */
    public array $receivers_onMessageUpdate = [];
    /** @var callable[] */
    public array $receivers_onThreadCreate = [];
    /** @var callable[] */
    public array $receivers_onVoiceStateUpdate = [];
    public function addReceiver_onGuildMemberAdd(string $name, callable $f):callable { return $this->receivers_onGuildMemberAdd[$name] = $f; }
    public function addReceiver_onGuildMemberRemove(string $name, callable $f):callable { return $this->receivers_onGuildMemberRemove[$name] = $f; }
    public function addReceiver_onGuildMemberUpdate(string $name, callable $f):callable { return $this->receivers_onGuildMemberUpdate[$name] = $f; }
    public function addReceiver_onMessageCreate(string $name, callable $f):callable { return $this->receivers_onMessageCreate[$name] = $f; }
    public function addReceiver_onMessageReactionAdd(string $name, callable $f):callable { return $this->receivers_onMessageReactionAdd[$name] = $f; }
    public function addReceiver_onMessageReactionRemove(string $name, callable $f):callable { return $this->receivers_onMessageReactionRemove[$name] = $f; }
    public function addReceiver_onMessageUpdate(string $name, callable $f):callable { return $this->receivers_onMessageUpdate[$name] = $f; }
    public function addReceiver_onThreadCreate(string $name, callable $f):callable { return $this->receivers_onThreadCreate[$name] = $f; }
    public function addReceiver_onVoiceStateUpdate(string $name, callable $f):callable { return $this->receivers_onVoiceStateUpdate[$name] = $f; }

    public function getCommandsArray():array {
        $a = [];
        foreach ($this->guildSlashCommands as $cmd) $a[] = $cmd->toArray();
        return $a;
    }
}

class MsgComponentResponse {
    public function __construct(public string $name, public ?\Closure $resolver=null) { }
}

class ModalResponse {
    public function __construct(public string $name, public ?\Closure $resolver=null) { }
}

class AutocompleteResponse {
    public function __construct(public string $name, public ?\Closure $resolver=null) { }
}

class GlobalCommand {
    public ?array $integrationTypes = null;
    public ?array $contexts = null;

    public function __construct(public string $name, public ?\Closure $resolver=null, public ?string $defaultMemberPermissions=null, public ?array $nameLocalizations=null, public bool $nsfw=false) {
        $nameLength = strlen($name);
        if ($nameLength < 1 || $nameLength > 32) throw new \Exception("Command name too long: '{$name}'.");
    }

    public function toArray() {
        return [
            'name' => $this->name,
            'name_localizations' => $this->nameLocalizations,
            'default_member_permissions' => $this->defaultMemberPermissions,
            'integration_types' => $this->integrationTypes,
            'context' => $this->contexts,
            'nsfw' => $this->nsfw
        ];
    }
}

class GlobalSlashCommand extends GlobalCommand {
    public array $options = [];

    public function __construct(string $name, public string $description, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, public ?array $descriptionLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
        $descLength = strlen($description);
        if ($descLength < 1 || $descLength > 100) throw new \Exception("Command description too long for {$name}.");
    }

    public function addOption(ApplicationCommandOption $option) {
        $this->options[] = $option;
    }

    public function toArray() {
        $options = [];
        foreach ($this->options as $opt) $options[] = $opt->toArray();
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::CHAT_INPUT,
            'description' => $this->description,
            'description_localizations' => $this->descriptionLocalizations,
            'options' => $options
        ]);
    }
}

class GlobalUserCommand extends GlobalCommand {
    public function __construct(string $name, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::USER
        ]);
    }
}

class GlobalMessageCommand extends GlobalCommand {
    public function __construct(string $name, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::MESSAGE
        ]);
    }
}

class GlobalPrimaryEntryPointCommand extends GlobalCommand {
    public function __construct(public EntryPointCommandHandlerType $handler, string $name, public string $description, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, public ?array $descriptionLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
        $descLength = strlen($description);
        if ($descLength < 1 || $descLength > 100) throw new \Exception("Command description too long for {$name}.");
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::PRIMARY_ENTRY_POINT,
            'description' => $this->description,
            'description_localizations' => $this->descriptionLocalizations,
            'handler' => $this->handler->value
        ]);
    }
}

class GuildCommand {
    public function __construct(public string $name, public ?\Closure $resolver=null, public ?string $defaultMemberPermissions=null, public ?array $nameLocalizations=null, public bool $nsfw=false) {
        $nameLength = strlen($name);
        if ($nameLength < 1 || $nameLength > 32) throw new \Exception("Command name too long: '{$name}'.");
    }

    public function toArray() {
        return [
            'name' => $this->name,
            'name_localizations' => $this->nameLocalizations,
            'default_member_permissions' => $this->defaultMemberPermissions,
            'nsfw' => $this->nsfw
        ];
    }
}

class GuildSlashCommand extends GuildCommand {
    public array $options = [];

    public function __construct(string $name, public string $description, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, public ?array $descriptionLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
        $descLength = strlen($description);
        if ($descLength < 1 || $descLength > 100) throw new \Exception("Command description too long for {$name}.");
    }

    public function addOption(ApplicationCommandOption $option) {
        $this->options[] = $option;
    }

    public function getResolver(string $name):?callable {
        if ($name === $this->name) return $this->resolver;
        $aNameParts = preg_split('/\s+/',$name);
        $cmd = $this;
        if (count($aNameParts) > 1) {
            array_shift($aNameParts);
            $curr = $cmd;
            foreach ($aNameParts as $namePart) {
                assert($curr instanceof GuildSlashCommand || $curr instanceof ApplicationCommandSubCommandOption || $curr instanceof ApplicationCommandSubCommandGroupOption);
                foreach ($curr->options as $opt) {
                    if (!($opt instanceof ApplicationCommandSubCommandOption) && !($opt instanceof ApplicationCommandSubCommandGroupOption)) continue;
                    if ($opt->name === $namePart) { $curr = $opt; continue 2; }
                }
            }
            if ($curr instanceof ApplicationCommandSubCommandOption) return $curr->resolver;
        }
        return null;
    }

    public function toArray() {
        $options = [];
        foreach ($this->options as $opt) $options[] = $opt->toArray();
        return array_merge(parent::toArray(),[
            'description' => $this->description,
            'description_localizations' => $this->descriptionLocalizations,
            'options' => $options,
        ]);
    }
}

class GuildUserCommand extends GuildCommand {
    public function __construct(string $name, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::USER
        ]);
    }
}

class GuildMessageCommand extends GuildCommand {
    public function __construct(string $name, ?\Closure $resolver=null, ?string $defaultMemberPermissions=null, ?array $nameLocalizations=null, bool $nsfw=false) {
        parent::__construct($name,$resolver,$defaultMemberPermissions,$nameLocalizations,$nsfw);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'type' => ApplicationCommandType::MESSAGE
        ]);
    }
}

abstract class ApplicationCommandOption {
    public function __construct(public ApplicationCommandOptionType $type, public string $name, public string $description, public ?array $nameLocalizations=null, public ?array $descriptionLocalizations=null) {
        $nameLength = strlen($name);
        if ($nameLength < 1 || $nameLength > 32) throw new \Exception("Command option name too long: '{$name}'.");
        $descLength = strlen($description);
        if ($descLength < 1 || $descLength > 100) throw new \Exception("Command option description too long for {$name}.");
    }

    public function toArray() {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
            'name_localizations' => $this->nameLocalizations,
            'description' => $this->description,
            'description_localizations' => $this->descriptionLocalizations
        ];
    }
}

class ApplicationCommandSubCommandOption extends ApplicationCommandOption {
    public array $options = [];

    public function __construct(string $name, string $description, public ?\Closure $resolver=null, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::SUB_COMMAND, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function addOption(ApplicationCommandOption $option) {
        $this->options[] = $option;
    }

    public function toArray() {
        $options = [];
        foreach ($this->options as $opt) $options[] = $opt->toArray();
        return array_merge(parent::toArray(),[
            'options' => $options,
        ]);
    }
}

class ApplicationCommandSubCommandGroupOption extends ApplicationCommandOption {
    public array $options = [];

    public function __construct(string $name, string $description, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::SUB_COMMAND_GROUP, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function addOption(ApplicationCommandOption $option) {
        $this->options[] = $option;
    }

    public function toArray() {
        $options = [];
        foreach ($this->options as $opt) $options[] = $opt->toArray();
        return array_merge(parent::toArray(),[
            'options' => $options,
        ]);
    }
}

class ApplicationCommandStringOption extends ApplicationCommandOption {
    public array $choices = [];

    public function __construct(string $name, string $description, public bool $required=false, public ?int $minLength=null, public ?int $maxLength=null, public bool $autocomplete=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::STRING, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function addChoice(string $name, string $value, ?array $nameLocalizations=null) {
        $this->choices[] = ['name' => $name, 'value' => $value, 'name_localizations' => $nameLocalizations];
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required,
            'choices' => $this->choices,
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'autocomplete' => $this->autocomplete
        ]);
    }
}

class ApplicationCommandIntegerOption extends ApplicationCommandOption {
    public array $choices = [];

    public function __construct(string $name, string $description, public bool $required=false, public ?int $minValue=null, public ?int $maxValue=null, public bool $autocomplete=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::INTEGER, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function addChoice(string $name, string $value, ?array $nameLocalizations=null) {
        $this->choices[] = ['name' => $name, 'value' => $value, 'name_localizations' => $nameLocalizations];
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required,
            'choices' => $this->choices,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'autocomplete' => $this->autocomplete
        ]);
    }
}

class ApplicationCommandBooleanOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::BOOLEAN, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required
        ]);
    }
}

class ApplicationCommandUserOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::USER, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required
        ]);
    }
}

class ApplicationCommandChannelOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, public ?array $channelTypes=null, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::CHANNEL, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required,
            'channel_types' => $this->channelTypes
        ]);
    }
}

class ApplicationCommandRoleOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::ROLE, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required
        ]);
    }
}

class ApplicationCommandMentionableOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::MENTIONABLE, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required
        ]);
    }
}

class ApplicationCommandNumberOption extends ApplicationCommandOption {
    public array $choices = [];

    public function __construct(string $name, string $description, public bool $required=false, public ?int $minValue=null, public ?int $maxValue=null, public bool $autocomplete=false, ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::NUMBER, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function addChoice(string $name, string $value, ?array $nameLocalizations=null) {
        $this->choices[] = ['name' => $name, 'value' => $value, 'name_localizations' => $nameLocalizations];
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required,
            'choices' => $this->choices,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'autocomplete' => $this->autocomplete
        ]);
    }
}

class ApplicationCommandAttachmentOption extends ApplicationCommandOption {
    public function __construct(string $name, string $description, public bool $required=false, public ?array $fileTypes=null , ?array $nameLocalizations=null, ?array $descriptionLocalizations=null) {
        parent::__construct(ApplicationCommandOptionType::ATTACHMENT, $name, $description, $nameLocalizations, $descriptionLocalizations);
    }

    public function toArray() {
        return array_merge(parent::toArray(),[
            'required' => $this->required,
            'file_types' => $this->fileTypes
        ]);
    }
}
?>