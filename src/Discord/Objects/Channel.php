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

class Channel {
    public string $id;
    public int $type;

    public ?string $guildId = null;
    public ?int $position = null;
    public ?string $name = null;
    public ?string $topic = null;
    public ?bool $nsfw = null;
    public ?string $lastMessageId = null;
    public ?string $parentId = null;
    public ?string $ownerId = null;
    public ?string $applicationId = null;
    public ?string $lastPinTimestamp = null;
    public ?int $rateLimitPerUser = null;
    public ?int $messageCount = null;
    public ?int $memberCount = null;
    public ?int $totalMessageSent = null;
    public ?int $flags = null;
    public ?string $permissions = null;

    public array $permissionOverwrites = [];
    public array $availableTags = [];
    public array $appliedTags = [];

    public ?ThreadMetadata $threadMetadata = null;
    public ?ThreadMember $member = null;

    public array $data = [];

    public static function fromArray(array $data): self {
        $channel = new self();

        $channel->id = $data['id'];
        $channel->type = $data['type'];

        $channel->guildId = $data['guild_id']??null;
        $channel->position = $data['position']??null;
        $channel->name = $data['name']??null;
        $channel->topic = $data['topic']??null;
        $channel->nsfw = $data['nsfw']??null;
        $channel->lastMessageId = $data['last_message_id']??null;
        $channel->parentId = $data['parent_id']??null;
        $channel->ownerId = $data['owner_id']??null;
        $channel->applicationId = $data['application_id']??null;
        $channel->lastPinTimestamp = $data['last_pin_timestamp']??null;
        $channel->rateLimitPerUser = $data['rate_limit_per_user']??null;
        $channel->messageCount = $data['message_count']??null;
        $channel->memberCount = $data['member_count']??null;
        $channel->totalMessageSent = $data['total_message_sent']??null;
        $channel->flags = $data['flags']??null;
        $channel->permissions = $data['permissions']??null;

        $channel->permissionOverwrites = $data['permission_overwrites']??[];
        $channel->availableTags = $data['available_tags']??[];
        $channel->appliedTags = $data['applied_tags']??[];

        $channel->threadMetadata = isset($data['thread_metadata'])
            ? ThreadMetadata::fromArray($data['thread_metadata'])
            : null;

        $channel->member = isset($data['member'])
            ? ThreadMember::fromArray($data['member'])
            : null;

        $channel->data = $data;

        return $channel;
    }
}

class ThreadMetadata {
    public bool $archived;
    public string $archiveTimestamp;
    public int $autoArchiveDuration;
    public bool $locked;
    public ?bool $invitable = null;
    public ?string $createTimestamp = null;

    public static function fromArray(array $data): self {
        $metadata = new self();

        $metadata->archived = $data['archived'];
        $metadata->archiveTimestamp = $data['archive_timestamp'];
        $metadata->autoArchiveDuration = $data['auto_archive_duration'];
        $metadata->locked = $data['locked'];
        $metadata->invitable = $data['invitable']??null;
        $metadata->createTimestamp = $data['create_timestamp']??null;

        return $metadata;
    }
}

class ThreadMember {
    public ?string $id = null;
    public ?string $userId = null;
    public string $joinTimestamp;
    public int $flags;
    public ?GuildMember $member = null;

    public static function fromArray(array $data): self {
        $threadMember = new self();

        $threadMember->id = $data['id']??null;
        $threadMember->userId = $data['user_id']??null;
        $threadMember->joinTimestamp = $data['join_timestamp'];
        $threadMember->flags = $data['flags'];

        $threadMember->member = isset($data['member'])
            ? GuildMember::fromArray($data['member'])
            : null;

        return $threadMember;
    }
}
?>