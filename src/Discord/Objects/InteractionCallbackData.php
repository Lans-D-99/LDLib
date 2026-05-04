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

class InteractionCallbackData {
    private ?array $components = null;
    public ?bool $tts = null;

    public function __construct(public int $flags=0, public ?string $content=null) { }

    public function addComponent(Component $c) {
        if ($this->components === null) $this->components = [];
        $this->components[] = $c;
    }

    public function insertComponent(Component $c, int $index) {
        if ($this->components === null) $this->components = [];
        array_splice($this->components, $index, 0, [$c]);
    }

    public function length():int {
        return isset($this->components) ? count($this->components) : 0;
    }

    public function toArray():array {
        $a = [
            'flags' => $this->flags
        ];
        if (isset($this->content)) $a['content'] = $this->content;
        if (isset($this->components)) { $a['components'] = []; foreach ($this->components as $c) { $a['components'][] = $c->toArray(); } }
        return $a;
    }
}
?>