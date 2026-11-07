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
namespace LDLib\Discord\Objects;

use LDLib\Discord\ApplicationCommandOptionType;
use LDLib\Discord\ComponentType;
use LDLib\Discord\Functionality\ApplicationCommandSubCommandGroupOption;
use LDLib\Discord\Functionality\ApplicationCommandSubCommandOption;
use LDLib\Discord\InteractionType;

enum InteractionContextType:int {
    case GUILD = 0;
    case BOT_DM = 1;
    case PRIVATE_CHANNEL = 2;
}

class Interaction {
    public string $id;
    public string $applicationId;

    public InteractionType $type;
    public InteractionData $data;
    public ?Guild $guild=null;
    public ?string $guildId=null;
    public ?Channel $channel=null;
    public ?string $channelId=null;

    public ?GuildMember $member=null;
    public ?User $user=null;

    public string $token;
    public int $version=1;

    public ?Message $message=null;

    public ?string $appPermissions=null;

    public ?string $locale=null;
    public ?string $guildLocale=null;

    /** @var Entitlement[] */
    public array $entitlements=[];

    /**
     * Keys are integration type IDs ("0" or "1").
     *
     * @var array<string, string>
     **/
    public array $authorizingIntegrationOwners=[];

    public ?InteractionContextType $context=null;

    public ?int $attachmentSizeLimit=null;

    public function getUserDiscordId():?string {
        return $this?->member?->user?->id ?? $this->user?->id;
    }

    public static function fromArray(array $data): self {
        $interaction = new self();

        $interaction->id = $data['id'];
        $interaction->applicationId = $data['application_id'];
        $interaction->type = InteractionType::from($data['type']);

        // Interaction data depends on the interaction type.
        if (isset($data['data'])) {
            $interaction->data = match ($interaction->type) {
                InteractionType::APPLICATION_COMMAND,
                InteractionType::APPLICATION_COMMAND_AUTOCOMPLETE => ApplicationCommandInteractionData::fromArray($data['data']),
                InteractionType::MESSAGE_COMPONENT => MessageComponentInteractionData::fromArray($data['data']),
                InteractionType::MODAL_SUBMIT =>ModalSubmitInteractionData::fromArray($data['data']),
                InteractionType::PING => null,
            };
        }

        $interaction->guild = isset($data['guild']) ? Guild::fromArray($data['guild']) : null;
        $interaction->guildId = $data['guild_id']??null;
        $interaction->channel = isset($data['channel']) ? Channel::fromArray($data['channel']) : null;
        $interaction->channelId = $data['channel_id']??null;
        $interaction->member = isset($data['member']) ? GuildMember::fromArray($data['member']) : null;
        $interaction->user = isset($data['user']) ? User::fromArray($data['user']) : null;
        $interaction->token = $data['token'];
        $interaction->version = $data['version'];
        $interaction->message = isset($data['message']) ? Message::fromArray($data['message']) : null;
        $interaction->appPermissions = $data['app_permissions']??null;
        $interaction->locale = $data['locale']??null;
        $interaction->guildLocale = $data['guild_locale']??null;
        $interaction->entitlements = array_map(fn(array $item) => Entitlement::fromArray($item), $data['entitlements']??[]);
        foreach ($data['authorizing_integration_owners']??[] as $key => $id) $interaction->authorizingIntegrationOwners[$key] = $id;
        $interaction->context = isset($data['context']) ? InteractionContextType::tryFrom($data['context']) : null;
        $interaction->attachmentSizeLimit = $data['attachment_size_limit']??null;

        return $interaction;
    }
}

class Entitlement {
    public string $id;
    public string $skuId;
    public string $applicationId;

    public ?string $userId = null;
    public ?string $guildId = null;

    public int $type;
    public bool $deleted;

    public ?string $startsAt = null;
    public ?string $endsAt = null;
    public ?bool $consumed = null;

    public static function fromArray(array $data): self {
        $entitlement = new self();

        $entitlement->id = $data['id'];
        $entitlement->skuId = $data['sku_id'];
        $entitlement->applicationId = $data['application_id'];

        $entitlement->userId = $data['user_id']??null;
        $entitlement->guildId = $data['guild_id']??null;

        $entitlement->type = $data['type'];
        $entitlement->deleted = $data['deleted'];

        $entitlement->startsAt = $data['starts_at']??null;
        $entitlement->endsAt = $data['ends_at']??null;
        $entitlement->consumed = $data['consumed']??null;

        return $entitlement;
    }
}

interface InteractionData {
}

class ApplicationCommandInteractionData implements InteractionData {
    public string $id;
    public string $name;
    public int $type;

    public ?ResolvedData $resolved=null;

    /** @var ApplicationCommandInteractionDataOption[] */
    public array $options=[];

    public ?string $guildId=null;
    public ?string $targetId=null;

    public function getFullName():string {
        $s = $this->name;

        foreach ($this->options as $opt) {
            if (!($opt->type === ApplicationCommandOptionType::SUB_COMMAND_GROUP->value) && !($opt->type === ApplicationCommandOptionType::SUB_COMMAND->value)) continue;
            $s .= " {$opt->name}";
            $curr = $opt;
            while ($curr instanceof ApplicationCommandSubCommandGroupOption) {
                foreach ($curr->options as $opt2) {
                    if (!($opt->type === ApplicationCommandOptionType::SUB_COMMAND_GROUP->value) && !($opt->type === ApplicationCommandOptionType::SUB_COMMAND->value)) continue;
                    $s .= " $opt2->name";
                    $curr = $opt2;
                    continue 2;
                }
                break;
            }
        }

        return $s;
    }

    public static function fromArray(array $data): self {
        $command = new self();

        $command->id = $data['id'];
        $command->name = $data['name'];
        $command->type = $data['type'];

        $command->resolved = isset($data['resolved']) ? ResolvedData::fromArray($data['resolved']) : null;

        $command->options = array_map(
            fn(array $item) => ApplicationCommandInteractionDataOption::fromArray($item),
            $data['options']??[]
        );

        $command->guildId = $data['guild_id']??null;
        $command->targetId = $data['target_id']??null;

        return $command;
    }
}

class ApplicationCommandInteractionDataOption {
    public string $name;
    public int $type;

    public mixed $value=null;

    /** @var ApplicationCommandInteractionDataOption[] */
    public array $options=[];

    public ?bool $focused=null;

    public static function fromArray(array $data): self {
        $option = new self();

        $option->name = $data['name'];
        $option->type = $data['type'];

        if (array_key_exists('value', $data)) {
            $option->value = $data['value'];
        }

        // foreach ($data['options']??[] as $opt) {
        //     switch ($opt['type']) {
        //         case ApplicationCommandOptionType::SUB_COMMAND:
        //             $option->options[] = ApplicationCommandSubCommandOption::fromArray($opt);
        //             break;
        //         default:
        //             $option->options[] = self::fromArray($opt);
        //     }

        // }
        $option->options = array_map(
            fn(array $item) => self::fromArray($item),
            $data['options']??[]
        );

        $option->focused = $data['focused']??null;

        return $option;
    }
}

class MessageComponentInteractionData implements InteractionData {
    public string $customId;
    public string $myCustomId;
    public int $componentType;

    /** @var string[] */
    public array $values=[];

    public ?ResolvedData $resolved=null;

    public static function fromArray(array $data): self {
        $component = new self();
        $component->customId = $data['custom_id'];
        $component->myCustomId = explode(':',$data['custom_id'],2)[0];
        $component->componentType = $data['component_type'];
        $component->values = $data['values']??[];
        $component->resolved = isset($data['resolved']) ? ResolvedData::fromArray($data['resolved']) : null;
        return $component;
    }
}

class ModalSubmitInteractionData implements InteractionData {
    public string $customId;
    public string $myCustomId;

    public array $components=[];

    public ?ResolvedData $resolved=null;

    public static function fromArray(array $data): self {
        $modal = new self();
        $modal->customId = $data['custom_id'];
        $modal->myCustomId = explode(':',$data['custom_id'],2)[0];
        $modal->components = $data['components']??[];
        $modal->resolved = isset($data['resolved']) ? ResolvedData::fromArray($data['resolved']) : null;
        return $modal;
    }

    public function getUserInputs():array {
        $a = [];
        foreach ($this->components as $c) {
            if ($c['type'] !== ComponentType::LABEL->value) continue;
            $comp = $c['component'];
            switch (ComponentType::tryFrom((int)$comp['type'])) {
                case ComponentType::STRING_SELECT: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::TEXT_INPUT: $a[$comp['custom_id']] = $comp['value']; break;
                case ComponentType::USER_SELECT: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::ROLE_SELECT: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::MENTIONABLE_SELECT: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::CHANNEL_SELECT: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::FILE_UPLOAD: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::RADIO_GROUP: $a[$comp['custom_id']] = $comp['value']; break;
                case ComponentType::CHECKBOX_GROUP: $a[$comp['custom_id']] = $comp['values']; break;
                case ComponentType::CHECKBOX: $a[$comp['custom_id']] = $comp['value']; break;
            }
        }
        return $a;
    }
}

class ResolvedData {
    /** @var array<string, User> */
    public array $users=[];
    /** @var array<string, GuildMember> */
    public array $members=[];
    /** @var array<string, Role> */
    public array $roles=[];
    /** @var array<string, Channel> */
    public array $channels=[];
    /** @var array<string, Message> */
    public array $messages=[];
    /** @var array<string, Attachment> */
    public array $attachments=[];

    public static function fromArray(array $data): self {
        $resolved = new self();
        foreach ($data['users']??[] as $id => $user) $resolved->users[$id] = User::fromArray($user);
        foreach ($data['members']??[] as $id => $member) $resolved->members[$id] = GuildMember::fromArray($member);
        foreach ($data['roles']??[] as $id => $role) $resolved->roles[$id] = Role::fromArray($role);
        foreach ($data['channels']??[] as $id => $channel) $resolved->channels[$id] = Channel::fromArray($channel);
        foreach ($data['messages']??[] as $id => $message) $resolved->messages[$id] = Message::fromArray($message);
        foreach ($data['attachments']??[] as $id => $attachment) $resolved->attachments[$id] = Attachment::fromArray($attachment);
        return $resolved;
    }
}
?>