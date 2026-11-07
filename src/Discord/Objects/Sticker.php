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

enum StickerType: int {
    case STANDARD = 1;
    case GUILD = 2;
}

enum StickerFormatType: int {
    case PNG = 1;
    case APNG = 2;
    case LOTTIE = 3;
    case GIF = 4;
}

class Sticker {
    public string $id;
    public ?string $packId = null;
    public string $name;
    public ?string $description = null;
    public ?string $tags = null;
    public ?string $asset = null;
    public ?StickerType $type = null;
    public StickerFormatType $formatType;

    public ?bool $available = null;
    public ?string $guildId = null;
    public ?User $user = null;
    public ?int $sortValue = null;

    public static function fromArray(array $data): self {
        $sticker = new self();

        $sticker->id = $data['id'];
        $sticker->packId = $data['pack_id']??null;
        $sticker->name = $data['name'];
        $sticker->description = $data['description']??null;
        $sticker->tags = $data['tags']??null;
        $sticker->asset = $data['asset']??null;
        $sticker->type = isset($data['type']) ? StickerType::from($data['type']) : null;
        $sticker->formatType = StickerFormatType::from($data['format_type']);
        $sticker->available = $data['available']??null;
        $sticker->guildId = $data['guild_id']??null;
        $sticker->user = isset($data['user']) ? User::fromArray($data['user']) : null;
        $sticker->sortValue = $data['sort_value']??null;

        return $sticker;
    }
}
?>