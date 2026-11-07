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

class Emoji {
    public ?string $id = null;
    public ?string $name = null;

    /** @var string[] */
    public array $roles = [];

    public ?User $user = null;
    public ?bool $requireColons = null;
    public ?bool $managed = null;
    public ?bool $animated = null;
    public ?bool $available = null;

    public static function fromArray(array $data): self {
        $emoji = new self();

        $emoji->id = isset($data['id']) ? $data['id'] : null;
        $emoji->name = $data['name']??null;
        $emoji->roles = $data['roles']??[];

        $emoji->user = isset($data['user']) ? User::fromArray($data['user']) : null;

        $emoji->requireColons = $data['require_colons']??null;
        $emoji->managed = $data['managed']??null;
        $emoji->animated = $data['animated']??null;
        $emoji->available = $data['available']??null;

        return $emoji;
    }

    public function toArray():array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles,
            'require_colons' => $this->requireColons,
            'managed' => $this->managed,
            'animated' => $this->animated,
            'available' => $this->available
        ];
    }
}
?>