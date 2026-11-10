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

class Guild {
    // Required fields
    public string $id;
    public ?string $name=null;

    // General information
    public ?string $icon=null;
    public ?string $iconHash=null;
    public ?string $splash=null;
    public ?string $discoverySplash=null;
    public ?bool $owner=null;
    public ?string $ownerId=null;
    public ?string $permissions=null;

    public ?string $afkChannelId=null;
    public ?int $afkTimeout=null;

    public ?bool $widgetEnabled=null;
    public ?string $widgetChannelId=null;

    public ?int $verificationLevel=null;
    public ?int $defaultMessageNotifications=null;
    public ?int $explicitContentFilter=null;

    /** @var Role[] */
    public array $roles=[];

    /** @var Emoji[] */
    public array $emojis=[];

    /** @var string[] */
    public array $features=[];

    public ?int $mfaLevel=null;
    public ?string $applicationId=null;
    public ?string $systemChannelId=null;
    public ?int $systemChannelFlags=null;
    public ?string $rulesChannelId=null;

    public ?int $maxPresences=null;
    public ?int $maxMembers=null;
    public ?string $vanityUrlCode=null;
    public ?string $description=null;
    public ?string $banner=null;

    public ?int $premiumTier=null;
    public ?int $premiumSubscriptionCount=null;
    public ?string $preferredLocale=null;
    public ?string $publicUpdatesChannelId=null;

    public ?int $maxVideoChannelUsers=null;
    public ?int $maxStageVideoChannelUsers=null;

    public ?int $approximateMemberCount=null;
    public ?int $approximatePresenceCount=null;

    public ?WelcomeScreen $welcomeScreen=null;
    public ?int $nsfwLevel=null;

    /** @var Sticker[] */
    public array $stickers=[];

    public ?bool $premiumProgressBarEnabled=null;
    public ?string $safetyAlertsChannelId=null;
    public ?IncidentsData $incidentsData=null;

    // GUILD_CREATE Gateway-specific fields
    public ?string $joinedAt=null;
    public ?bool $large=null;
    public ?bool $unavailable=null;
    public ?int $memberCount=null;

    /** @var GuildMember[] */
    public array $members=[];

    /** @var Channel[] */
    public array $channels=[];

    /** @var Channel[] */
    public array $threads=[];

    // Gateway objects not yet modeled
    public array $voiceStates=[];
    public array $presences=[];
    public array $stageInstances=[];
    public array $guildScheduledEvents=[];
    public array $soundboardSounds=[];

    public static function fromArray(array $data): self {
        $guild = new self();

        $guild->id = $data['id'];
        $guild->name = $data['name']??null;

        $guild->icon = $data['icon']??null;
        $guild->iconHash = $data['icon_hash']??null;
        $guild->splash = $data['splash']??null;
        $guild->discoverySplash = $data['discovery_splash']??null;
        $guild->owner = $data['owner']??null;
        $guild->ownerId = $data['owner_id']??null;
        $guild->permissions = $data['permissions']??null;

        $guild->afkChannelId = $data['afk_channel_id']??null;
        $guild->afkTimeout = $data['afk_timeout']??null;

        $guild->widgetEnabled = $data['widget_enabled']??null;
        $guild->widgetChannelId = $data['widget_channel_id']??null;

        $guild->verificationLevel = $data['verification_level']??null;
        $guild->defaultMessageNotifications = $data['default_message_notifications']??null;

        $guild->explicitContentFilter = $data['explicit_content_filter']??null;

        $guild->roles = array_map(fn(array $item) => Role::fromArray($item), $data['roles']??[]);

        $guild->emojis = array_map(fn(array $item) => Emoji::fromArray($item), $data['emojis']??[]);

        $guild->features = $data['features']??[];

        $guild->mfaLevel = $data['mfa_level']??null;
        $guild->applicationId = $data['application_id']??null;
        $guild->systemChannelId = $data['system_channel_id']??null;
        $guild->systemChannelFlags = $data['system_channel_flags']??null;
        $guild->rulesChannelId = $data['rules_channel_id']??null;

        $guild->maxPresences = $data['max_presences']??null;
        $guild->maxMembers = $data['max_members']??null;
        $guild->vanityUrlCode = $data['vanity_url_code']??null;
        $guild->description = $data['description']??null;
        $guild->banner = $data['banner']??null;

        $guild->premiumTier = $data['premium_tier']??null;
        $guild->premiumSubscriptionCount = $data['premium_subscription_count']??null;

        $guild->preferredLocale = $data['preferred_locale']??null;
        $guild->publicUpdatesChannelId = $data['public_updates_channel_id']??null;

        $guild->maxVideoChannelUsers = $data['max_video_channel_users']??null;
        $guild->maxStageVideoChannelUsers = $data['max_stage_video_channel_users']??null;
        $guild->approximateMemberCount = $data['approximate_member_count']??null;
        $guild->approximatePresenceCount = $data['approximate_presence_count']??null;

        $guild->welcomeScreen = isset($data['welcome_screen']) ? WelcomeScreen::fromArray($data['welcome_screen']) : null;
        $guild->nsfwLevel = $data['nsfw_level']??null;
        $guild->stickers = array_map(fn(array $item) => Sticker::fromArray($item), $data['stickers']??[]);
        $guild->premiumProgressBarEnabled = $data['premium_progress_bar_enabled']??null;
        $guild->safetyAlertsChannelId = $data['safety_alerts_channel_id']??null;
        $guild->incidentsData = isset($data['incidents_data']) ? IncidentsData::fromArray($data['incidents_data']) : null;

        // GUILD_CREATE Gateway-specific fields
        $guild->joinedAt = $data['joined_at']??null;
        $guild->large = $data['large']??null;
        $guild->unavailable = $data['unavailable']??null;
        $guild->memberCount = $data['member_count']??null;

        $guild->members = array_map(fn(array $item) => GuildMember::fromArray($item), $data['members']??[]);
        $guild->channels = array_map(fn(array $item) => Channel::fromArray($item), $data['channels']??[]);
        $guild->threads = array_map(fn(array $item) => Channel::fromArray($item), $data['threads']??[]);

        $guild->voiceStates = $data['voice_states']??[];
        $guild->presences = $data['presences']??[];
        $guild->stageInstances = $data['stage_instances']??[];
        $guild->guildScheduledEvents = $data['guild_scheduled_events']??[];
        $guild->soundboardSounds = $data['soundboard_sounds']??[];

        return $guild;
    }
}

class GuildMember {
    public ?User $user=null;
    public ?string $nick=null;
    public ?string $avatar=null;
    public ?string $banner=null;

    /** @var string[] */
    public array $roles=[];

    public ?string $joinedAt=null;
    public ?string $premiumSince=null;
    public ?bool $deaf=null;
    public ?bool $mute=null;
    public int $flags=0;
    public ?bool $pending=null;
    public ?string $permissions=null;
    public ?string $communicationDisabledUntil=null;

    public ?array $avatarDecorationData=null;
    public ?array $collectibles=null;

    // Extra field on Guild Member Add gateway event
    public ?string $guildId=null;

    public static function fromArray(array $data): self {
        $member = new self();

        $member->user = isset($data['user']) ? User::fromArray($data['user']) : null;
        $member->nick = $data['nick']??null;
        $member->avatar = $data['avatar']??null;
        $member->banner = $data['banner']??null;
        $member->roles = $data['roles']??[];

        $member->joinedAt = $data['joined_at']??null;
        $member->premiumSince = $data['premium_since']??null;
        $member->deaf = $data['deaf']??null;
        $member->mute = $data['mute']??null;
        $member->flags = $data['flags']??0;
        $member->pending = $data['pending']??null;
        $member->permissions = $data['permissions']??null;
        $member->communicationDisabledUntil = $data['communication_disabled_until']??null;
        $member->avatarDecorationData = $data['avatar_decoration_data']??null;
        $member->collectibles = $data['collectibles']??null;

        $member->guildId = $data['guild_id']??null;

        return $member;
    }
}

class WelcomeScreen {
    public ?string $description=null;

    /** @var WelcomeScreenChannel[] */
    public array $welcomeChannels=[];

    public static function fromArray(array $data): self {
        $screen = new self();
        $screen->description = $data['description']??null;
        $screen->welcomeChannels = array_map(fn(array $item) => WelcomeScreenChannel::fromArray($item), $data['welcome_channels']??[]);
        return $screen;
    }
}

class WelcomeScreenChannel {
    public string $channelId;
    public string $description;
    public ?string $emojiId=null;
    public ?string $emojiName=null;

    public static function fromArray(array $data): self {
        $channel = new self();
        $channel->channelId = $data['channel_id'];
        $channel->description = $data['description'];
        $channel->emojiId = $data['emoji_id']??null;
        $channel->emojiName = $data['emoji_name']??null;
        return $channel;
    }
}

class IncidentsData {
    public ?string $invitesDisabledUntil=null;
    public ?string $dmsDisabledUntil=null;
    public ?string $dmSpamDetectedAt=null;
    public ?string $raidDetectedAt=null;

    public static function fromArray(array $data): self {
        $incidents = new self();
        $incidents->invitesDisabledUntil = $data['invites_disabled_until']??null;
        $incidents->dmsDisabledUntil = $data['dms_disabled_until']??null;
        $incidents->dmSpamDetectedAt = $data['dm_spam_detected_at']??null;
        $incidents->raidDetectedAt = $data['raid_detected_at']??null;
        return $incidents;
    }
}
?>