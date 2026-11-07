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

class User {
    public string $id;
    public string $username;
    public string $discriminator;
    public ?string $globalName;
    public ?string $avatar;

    public ?bool $bot = null;
    public ?bool $system = null;
    public ?bool $mfaEnabled = null;
    public ?string $banner = null;
    public ?int $accentColor = null;
    public ?string $locale = null;
    public ?bool $verified = null;
    public ?string $email = null;
    public ?int $flags = null;
    public ?int $premiumType = null;
    public ?int $publicFlags = null;

    public ?AvatarDecorationData $avatarDecorationData = null;
    public ?Collectibles $collectibles = null;
    public ?UserPrimaryGuild $primaryGuild = null;

    public function __construct() { }

    public static function fromArray(array $data):self {
        $user = new self();
        $user->id = $data['id'];
        $user->username = $data['username'];
        $user->discriminator = $data['discriminator'];
        $user->globalName = $data['global_name']??null;
        $user->avatar = $data['avatar']??null;

        $user->bot = $data['bot']??null;
        $user->system = $data['system']??null;
        $user->mfaEnabled = $data['mfa_enabled']??null;
        $user->banner = $data['banner']??null;
        $user->accentColor = $data['accent_color']??null;
        $user->locale = $data['locale']??null;
        $user->verified = $data['verified']??null;
        $user->email = $data['email']??null;
        $user->flags = $data['flags']??null;
        $user->premiumType = $data['premium_type']??null;
        $user->publicFlags = $data['public_flags']??null;

        if (isset($data['avatar_decoration_data']))
            $user->avatarDecorationData = AvatarDecorationData::fromArray($data['avatar_decoration_data']);

        if (isset($data['collectibles']))
            $user->collectibles = Collectibles::fromArray($data['collectibles']);

        if (isset($data['primary_guild']))
            $user->primaryGuild = UserPrimaryGuild::fromArray($data['primary_guild']);

        return $user;
    }
}

class AvatarDecorationData {
    public string $skuId;
    public string $asset;

    public function __construct(string $skuId, string $asset) {
        $this->skuId = $skuId;
        $this->asset = $asset;
    }

    public static function fromArray(array $data): self {
        return new self($data['sku_id'],$data['asset']);
    }
}

class Collectibles {
    public ?Nameplate $nameplate = null;

    public static function fromArray(array $data): self {
        $collectibles = new self();

        if (isset($data['nameplate']))
            $collectibles->nameplate = Nameplate::fromArray($data['nameplate']);

        return $collectibles;
    }
}

class Nameplate {
    public string $skuId;
    public string $asset;
    public string $label;
    public string $palette;

    public static function fromArray(array $data): self {
        $nameplate = new self();
        $nameplate->skuId = $data['sku_id'];
        $nameplate->asset = $data['asset'];
        $nameplate->label = $data['label'];
        $nameplate->palette = $data['palette'];

        return $nameplate;
    }
}

class UserPrimaryGuild {
    public ?string $identityGuildId = null;
    public ?bool $identityEnabled = null;
    public ?string $tag = null;
    public ?string $badge = null;

    public static function fromArray(array $data): self {
        $guild = new self();

        $guild->identityGuildId = $data['identity_guild_id']??null;
        $guild->identityEnabled = $data['identity_enabled']??null;
        $guild->tag = $data['tag']??null;
        $guild->badge = $data['badge']??null;

        return $guild;
    }
}
?>