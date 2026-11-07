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

use LDLib\Discord\ChannelType;
use LDLib\Discord\Components\Component;

enum MessageType:int {
    case DEFAULT_MESSAGE = 0;
    case RECIPIENT_ADD = 1;
    case RECIPIENT_REMOVE = 2;
    case CALL = 3;
    case CHANNEL_NAME_CHANGE = 4;
    case CHANNEL_ICON_CHANGE = 5;
    case CHANNEL_PINNED_MESSAGE = 6;
    case USER_JOIN = 7;
    case GUILD_BOOST = 8;
    case GUILD_BOOST_TIER_1 = 9;
    case GUILD_BOOST_TIER_2 = 10;
    case GUILD_BOOST_TIER_3 = 11;
    case CHANNEL_FOLLOW_ADD = 12;

    case GUILD_DISCOVERY_DISQUALIFIED = 14;
    case GUILD_DISCOVERY_REQUALIFIED = 15;
    case GUILD_DISCOVERY_GRACE_PERIOD_INITIAL_WARNING = 16;
    case GUILD_DISCOVERY_GRACE_PERIOD_FINAL_WARNING = 17;

    case THREAD_CREATED = 18;
    case REPLY = 19;
    case CHAT_INPUT_COMMAND = 20;
    case THREAD_STARTER_MESSAGE = 21;
    case GUILD_INVITE_REMINDER = 22;
    case CONTEXT_MENU_COMMAND = 23;
    case AUTO_MODERATION_ACTION = 24;
    case ROLE_SUBSCRIPTION_PURCHASE = 25;
    case INTERACTION_PREMIUM_UPSELL = 26;

    case STAGE_START = 27;
    case STAGE_END = 28;
    case STAGE_SPEAKER = 29;
    case STAGE_RAISE_HAND = 30;
    case STAGE_TOPIC = 31;

    case GUILD_APPLICATION_PREMIUM_SUBSCRIPTION = 32;

    case GUILD_INCIDENT_ALERT_MODE_ENABLED = 36;
    case GUILD_INCIDENT_ALERT_MODE_DISABLED = 37;
    case GUILD_INCIDENT_REPORT_RAID = 38;
    case GUILD_INCIDENT_REPORT_FALSE_ALARM = 39;

    case PURCHASE_NOTIFICATION = 44;
    case POLL_RESULT = 46;
}

class Message {
    // Required fields
    public string $id;
    public string $channelId;
    public User $author;

    public string $content='';
    public string $timestamp='';
    public ?string $editedTimestamp=null;

    public bool $tts=false;
    public bool $mentionEveryone=false;

    /** @var User[] */
    public array $mentions=[];
    /** @var string[] */
    public array $mentionRoles=[];
    /** @var ChannelMention[] */
    public array $mentionChannels=[];
    /** @var Attachment[] */
    public array $attachments=[];
    /** @var Embed[] */
    public array $embeds=[];
    /** @var Reaction[] */
    public array $reactions=[];

    public int|string|null $nonce=null;

    public bool $pinned=false;
    public ?string $webhookId=null;
    public MessageType $type = MessageType::DEFAULT_MESSAGE;

    public ?MessageActivity $activity=null;
    public ?array $application=null;
    public ?string $applicationId=null;
    public ?int $flags=null;

    public ?MessageReference $messageReference=null;
    public ?array $messageSnapshots=null;
    public ?Message $referencedMessage=null;

    public ?array $interactionMetadata=null;

    public ?array $thread=null;
    public array $components=[];

    /** @var MessageStickerItem[] */
    public array $stickerItems=[];

    public ?int $position=null;
    public ?RoleSubscriptionData $roleSubscriptionData=null;
    public ?array $resolved=null;
    public ?Poll $poll=null;
    public ?MessageCall $call=null;
    public ?array $sharedClientTheme=null;

    // Additional MESSAGE_CREATE / MESSAGE_UPDATE gateway fields
    public ?string $guildId=null;
    public ?GuildMember $member=null;
    public ?ChannelType $channelType=null;

    public static function fromArray(array $data): self {
        $message = new self();

        $message->id = $data['id'];
        $message->channelId = $data['channel_id'];
        $message->author = User::fromArray($data['author']);

        $message->content = $data['content'];
        $message->timestamp = $data['timestamp'];
        $message->editedTimestamp = $data['edited_timestamp']??null;

        $message->tts = $data['tts'];
        $message->mentionEveryone = $data['mention_everyone'];

        $message->mentions = array_map(fn($v) => User::fromArray($v), $data['mentions']??[]);
        $message->mentionRoles = $data['mention_roles']??[];
        $message->mentionChannels = array_map(fn($v) => User::fromArray($v), $data['mentions']??[]);
        $message->attachments = array_map(fn($v) => Attachment::fromArray($v), $data['attachments']??[]);
        $message->embeds = array_map(fn($v) => Embed::fromArray($v), $data['embeds']??[]);
        $message->reactions = array_map(fn($v) => Reaction::fromArray($v), $data['reactions']??[]);

        $message->nonce = $data['nonce']??null;

        $message->pinned = $data['pinned']??false;
        $message->webhookId = $data['webhook_id']??null;
        $message->type = MessageType::tryFrom($data['type'])??0;

        $message->activity = isset($data['activity']) ? MessageActivity::fromArray($data['activity']) : null;
        $message->application = $data['application']??null;
        $message->applicationId = $data['application_id']??null;
        $message->flags = $data['flags']??null;

        $message->messageReference = isset($data['message_reference']) ? MessageReference::fromArray($data['message_reference']) : null;
        $message->messageSnapshots = $data['message_snapshots']??[];
        $message->referencedMessage = isset($data['referenced_message']) ? Message::fromArray($data['referenced_message']) : null;

        $message->interactionMetadata = $data['interaction_metadata']??null;

        $message->thread = $data['thread']??null;
        $message->components = $data['components']??[];

        $message->stickerItems = array_map(fn($v) => Reaction::fromArray($v), $data['sticker_items']??[]);

        $message->position = $data['position']??null;
        $message->roleSubscriptionData = isset($data['role_subscription_data']) ? RoleSubscriptionData::fromArray($data['role_subscription_data']) : null;
        $message->resolved = $data['resolved']??null;
        $message->poll = isset($data['poll']) ? Poll::fromArray($data['poll']) : null;
        $message->call = isset($data['call']) ? MessageCall::fromArray($data['call']) : null;
        $message->sharedClientTheme = $data['shared_client_theme']??null;

        // Gateway-specific fields
        $message->guildId = $data['guild_id']??null;
        $message->member = isset($data['member']) ? GuildMember::fromArray($data['member']) : null;
        $message->channelType = isset($data['channel_type']) ? ChannelType::tryFrom($data['channel_type']) : null;

        return $message;
    }
}

class ChannelMention {
    public string $id;
    public string $guildId;
    public ChannelType $type;
    public string $name;

    public static function fromArray(array $data): self {
        $mention = new self();

        $mention->id = $data['id'];
        $mention->guildId = $data['guild_id'];
        $mention->type = ChannelType::from($data['type']);
        $mention->name = $data['name'];

        return $mention;
    }
}

class Attachment {
    public string $id;
    public string $filename;
    public ?string $title=null;
    public ?string $description=null;
    public ?string $contentType=null;
    public int $size;
    public string $url;
    public string $proxyUrl;
    public ?int $height=null;
    public ?int $width=null;
    public ?string $placeholder=null;
    public ?int $placeholderVersion=null;
    public ?bool $ephemeral=null;
    public ?float $durationSecs=null;
    public ?string $waveform=null;
    public ?int $flags=null;
    public ?array $clipParticipants=null;
    public ?string $clipCreatedAt=null;
    public ?array $application=null;

    public static function fromArray(array $data): self {
        $attachment = new self();

        $attachment->id = $data['id'];
        $attachment->filename = $data['filename'];
        $attachment->size = $data['size'];
        $attachment->url = $data['url'];
        $attachment->proxyUrl = $data['proxy_url'];

        $attachment->title = $data['title']??null;
        $attachment->description = $data['description']??null;
        $attachment->contentType = $data['content_type']??null;
        $attachment->height = $data['height']??null;
        $attachment->width = $data['width']??null;
        $attachment->placeholder = $data['placeholder']??null;
        $attachment->placeholderVersion = $data['placeholder_version']??null;
        $attachment->ephemeral = $data['ephemeral']??null;
        $attachment->durationSecs = isset($data['duration_secs']) ? $data['duration_secs'] : null;
        $attachment->waveform = $data['waveform']??null;
        $attachment->flags = $data['flags']??null;
        $attachment->clipParticipants = $data['clip_participants']??null;
        $attachment->clipCreatedAt = $data['clip_created_at']??null;
        $attachment->application = $data['application']??null;

        return $attachment;
    }
}

class MessageReactionAdd {
    public ?string $userId=null;
    public ?string $channelId=null;
    public ?string $messageId=null;
    public ?string $guildId=null;
    public ?GuildMember $member=null;

    // Partial emoji object
    public Emoji $emoji;

    public ?string $messageAuthorId=null;
    public bool $burst;
    /** @var string[] */
    public ?array $burstColors=null;

    public int $type;

    public static function fromArray(array $data):self {
        $c = new self();
        $c->userId = $data['user_id'];
        $c->channelId = $data['channel_id'];
        $c->messageId = $data['message_id'];
        $c->guildId = $data['guild_id'];
        $c->member = isset($data['member']) ? GuildMember::fromArray($data['member']) : null;
        $c->emoji = Emoji::fromArray($data['emoji']);
        $c->messageAuthorId = $data['message_author_id']??null;
        $c->burst = $data['burst']??false;
        $c->burstColors = $data['burst_colors']??null;
        $c->type = $data['type'];
        return $c;
    }
}

class MessageReactionRemove {
    public ?string $userId=null;
    public ?string $channelId=null;
    public ?string $messageId=null;
    public ?string $guildId=null;

    // Partial emoji object
    public Emoji $emoji;

    public bool $burst;
    public int $type;

    public static function fromArray(array $data):self {
        $c = new self();
        $c->userId = $data['user_id'];
        $c->channelId = $data['channel_id'];
        $c->messageId = $data['message_id'];
        $c->guildId = $data['guild_id'];
        $c->emoji = Emoji::fromArray($data['emoji']);
        $c->burst = $data['burst']??false;
        $c->type = $data['type'];
        return $c;
    }
}

class MessageReactionRemoveAll {
    public ?string $channelId=null;
    public ?string $messageId=null;
    public ?string $guildId=null;

    public static function fromArray(array $data):self {
        $c = new self();
        $c->channelId = $data['channel_id'];
        $c->messageId = $data['message_id'];
        $c->guildId = $data['guild_id'];
        return $c;
    }
}

class MessageReactionRemoveEmoji {
    public ?string $channelId=null;
    public ?string $messageId=null;
    public ?string $guildId=null;

    // Partial emoji object
    public Emoji $emoji;

    public static function fromArray(array $data):self {
        $c = new self();
        $c->channelId = $data['channel_id'];
        $c->messageId = $data['message_id'];
        $c->guildId = $data['guild_id'];
        $c->emoji = Emoji::fromArray($data['emoji']);
        return $c;
    }
}

class Reaction {
    public int $count;
    public array $countDetails;
    public bool $me;
    public bool $meBurst;

    // Partial emoji object
    public array $emoji;

    /** @var string[] */
    public array $burstColors;

    public static function fromArray(array $data): self {
        $reaction = new self();

        $reaction->count = $data['count'];
        $reaction->countDetails = $data['count_details'];
        $reaction->me = $data['me'];
        $reaction->meBurst = $data['me_burst'];
        $reaction->emoji = $data['emoji'];
        $reaction->burstColors = $data['burst_colors'];

        return $reaction;
    }
}

class MessageActivity {
    public int $type;
    public ?string $partyId = null;

    public static function fromArray(array $data): self {
        $activity = new self();
        $activity->type = $data['type'];
        $activity->partyId = $data['party_id']??null;
        return $activity;
    }
}

class MessageReference {
    public ?int $type=null;
    public ?string $messageId=null;
    public ?string $channelId=null;
    public ?string $guildId=null;
    public ?bool $failIfNotExists=null;

    public static function fromArray(array $data): self {
        $reference = new self();
        $reference->type = $data['type']??null;
        $reference->messageId = $data['message_id']??null;
        $reference->channelId = $data['channel_id']??null;
        $reference->guildId = $data['guild_id']??null;
        $reference->failIfNotExists = $data['fail_if_not_exists']??null;
        return $reference;
    }
}

class MessageStickerItem {
    public string $id;
    public string $name;
    public int $formatType;

    public static function fromArray(array $data): self {
        $sticker = new self();
        $sticker->id = $data['id'];
        $sticker->name = $data['name'];
        $sticker->formatType = $data['format_type'];
        return $sticker;
    }
}

class RoleSubscriptionData {
    public string $roleSubscriptionListingId;
    public string $tierName;
    public int $totalMonthsSubscribed;
    public bool $isRenewal;

    public static function fromArray(array $data): self {
        $subscription = new self();
        $subscription->roleSubscriptionListingId = $data['role_subscription_listing_id'];
        $subscription->tierName = $data['tier_name'];
        $subscription->totalMonthsSubscribed = $data['total_months_subscribed'];
        $subscription->isRenewal = $data['is_renewal'];
        return $subscription;
    }
}

class MessageCall {
    /** @var string[] */
    public array $participants=[];
    public ?string $endedTimestamp=null;

    public static function fromArray(array $data): self {
        $call = new self();
        $call->participants = $data['participants'];
        $call->endedTimestamp = $data['ended_timestamp']??null;
        return $call;
    }
}

class SharedClientTheme {
    /** @var string[] */
    public array $colors=[];
    public int $gradientAngle;
    public int $baseMix;
    public ?int $baseTheme=null;

    public static function fromArray(array $data): self {
        $theme = new self();
        $theme->colors = $data['colors']??[];
        $theme->gradientAngle = $data['gradient_angle'];
        $theme->baseMix = $data['base_mix'];
        $theme->baseTheme = $data['base_theme']??null;
        return $theme;
    }
}

class CreateMessage {
    public array $components=[];
    public ?array $allowedMentions=null;

    public function __construct(public int $flags=0, public ?string $content=null) { }

    public function addComponent(Component $c) {
        $this->components[] = $c;
    }

    public function toArray() {
        $a = [ 'flags' => $this->flags ];
        if (!empty($this->components)) { $a['components'] = []; foreach ($this->components as $c) $a['components'][] = $c->toArray(); }
        else $a['content'] = $this->content;
        if (!empty($this->allowedMentions)) $a['allowed_mentions'] = $this->allowedMentions;
        return $a;
    }
}
?>