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

use LDLib\Discord\Components\Component;

class Modal {
    private array $components;

    public function __construct(public string $customId, public string $title) { }

    public function addComponent(Component $c) {
        $this->components[] = $c;
    }

    public function insertComponent(Component $c, int $index) {
        array_splice($this->components, $index, 0, [$c]);
    }

    public function length():int {
        return isset($this->components) ? count($this->components) : 0;
    }

    public function toArray():array {
        $a = [];
        foreach ($this->components as $c) { $a[] = $c->toArray(); }
        return [
            'custom_id' => $this->customId,
            'title' => $this->title,
            'components' => $a
        ];
    }
}
?>