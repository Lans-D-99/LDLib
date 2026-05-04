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
    public ?array $roles = null;
    public ?bool $requireColons = null;
    public ?bool $managed = null;
    public ?bool $animated = null;
    public ?bool $available = null;

    public function __construct(public ?string $id, public ?string $name) { }

    public function toArray():array {
        $a = [
            'id' => $this->id,
            'name' => $this->name
        ];
        if (isset($this->roles)) $a['roles'] = $this->roles;
        if (isset($this->requireColons)) $a['require_colons'] = $this->requireColons;
        if (isset($this->managed)) $a['managed'] = $this->managed;
        if (isset($this->animated)) $a['animated'] = $this->animated;
        if (isset($this->available)) $a['available'] = $this->available;
        return $a;
    }
}
?>