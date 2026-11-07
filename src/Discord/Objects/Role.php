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

class Role {
    public string $id;
    public string $name;
    public int $color;
    public array $colors;
    public bool $hoist;
    public ?string $icon = null;
    public ?string $unicodeEmoji = null;
    public int $position;
    public string $permissions;
    public bool $managed;
    public bool $mentionable;
    public ?array $tags = null;
    public int $flags;

    public static function fromArray(array $data):self {
        $role = new self();

        $role->id = (string) $data['id'];
        $role->name = $data['name'];
        $role->color = $data['color'];
        $role->colors = $data['colors'];
        $role->hoist = $data['hoist'];
        $role->icon = $data['icon'] ?? null;
        $role->unicodeEmoji = $data['unicode_emoji'] ?? null;
        $role->position = $data['position'];
        $role->permissions = (string) $data['permissions'];
        $role->managed = $data['managed'];
        $role->mentionable = $data['mentionable'];
        $role->tags = $data['tags'] ?? null;
        $role->flags = $data['flags'];

        return $role;
    }
}
?>