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

class VoiceState {
    public ?string $guildId=null;
    public ?string $channelId=null;
    public string $userId;
    public ?GuildMember $member=null;
    public string $sessionId;
    public bool $deaf;
    public bool $mute;
    public bool $selfDeaf;
    public bool $selfMute;
    public bool $selfStream;
    public bool $selfVideo;
    public bool $suppress;

    public static function fromArray(array $data) {
        $c = new self();
        $c->guildId = $data['guild_id']??null;
        $c->channelId = $data['channel_id'];
        $c->userId = $data['user_id'];
        $c->member = $data['member']??null;
        $c->sessionId = $data['session_id'];
        $c->deaf = $data['deaf'];
        $c->mute = $data['mute'];
        $c->selfDeaf = $data['self_deaf'];
        $c->selfMute = $data['self_mute'];
        $c->selfStream = $data['self_stream']??false;
        $c->selfVideo = $data['self_video']??false;
        $c->suppress = $data['suppress'];
        return $c;
    }
}
?>