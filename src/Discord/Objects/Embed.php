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

class Embed {
    public ?int $flags = 0;
    public ?string $timestamp = null;
    public ?string $url = null;
    public ?array $footer = null;
    public ?array $image = null;
    public ?array $thumbnail = null;
    public ?array $video = null;
    public ?array $provider = null;
    public ?array $author = null;
    public ?array $fields = null;

    public function __construct(public ?string $title=null, public ?string $description=null, public ?int $color=null, public string $type="rich") { }

    public function toArray() {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'color' => $this->color,
            'flags' => $this->flags,
            'timestamp' => $this->timestamp,
            'url' => $this->url,
            'footer' => $this->footer,
            'image' => $this->image,
            'thumbnail' => $this->thumbnail,
            'video' => $this->video,
            'provider' => $this->provider,
            'author' => $this->author,
            'fields' => $this->fields
        ];
    }
}
?>