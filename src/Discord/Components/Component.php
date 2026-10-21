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
namespace LDLib\Discord\Components;

use LDLib\Discord\ButtonComponentStyle;
use LDLib\Discord\ComponentType;
use LDLib\Discord\Objects\Emoji;
use LDLib\Discord\TextInputStyle;

abstract class Component {
    public ComponentType $type { get => $this->type; }
    public ?int $id { get => $this->id; }

    public function __construct(ComponentType $type, ?int $id) {
        $this->type = $type;
        $this->id = $id;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'type' => $this->type
        ];
    }
}

abstract class ComponentsContainer extends Component {
    private array $components = [];

    public function __construct(ComponentType $type, ?int $id=null) {
        parent::__construct($type, $id);
    }

    public function addComponent(Component $c) {
        $this->components[] = $c;
    }

    public function insertComponent(Component $c, int $index) {
        array_splice($this->components, $index, 0, [$c]);
    }

    public function length():int {
        return count($this->components);
    }

    public function toArray() {
        $a = [];
        foreach ($this->components as $c) { $a[] = $c->toArray(); }
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'components' => $a
        ];
    }
}

class ActionRow extends ComponentsContainer {
    public function __construct(?int $id=null) {
        parent::__construct(ComponentType::ACTION_ROW, $id);
    }
}

class Button extends Component {
    public ?Emoji $emoji = null { get => $this->emoji; }

    public function __construct(
        public ?string $label, public ?string $customId, public ButtonComponentStyle $style=ButtonComponentStyle::SECONDARY,
        public ?string $url=null, public ?string $skuId=null, public bool $disabled=false, ?int $id=null
    ) {
        parent::__construct(ComponentType::BUTTON, $id);
    }

    public function setEmoji(?string $id, ?string $name, ?bool $animated=null) {
        $this->emoji = new Emoji($id, $name);
        $this->emoji->animated = $animated;
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'style' => $this->style->value,
            'label' => $this->label,
            'emoji' => $this->emoji?->id === null ? null : $this->emoji->toArray(),
            'custom_id' => $this->customId,
            'skuId' => $this->skuId,
            'url' => $this->url,
            'disabled' => $this->disabled
        ]);
    }
}

class StringSelect extends Component {
    private array $options = [];

    public function __construct(
        public string $customId, public ?string $placeholder=null, public ?int $minValues=1, public ?int $maxValues=1, public ?bool $required=null, public bool $disabled=false, ?int $id=null) {
        parent::__construct(ComponentType::STRING_SELECT, $id);
    }

    public function addOption(string $label, string $value, ?string $description=null, ?string $emojiId=null, ?string $emojiName=null, ?bool $emojiAnimated=null, ?bool $default=false) {
        $emoji = new Emoji($emojiId,$emojiName);
        $emoji->animated = $emojiAnimated;
        $this->options[] = [
            'label' => $label,
            'value' => $value,
            'description' => $description,
            'emoji' => $emoji->id === null ? null : $emoji->toArray(),
            'default' => $default
        ];
    }

    public function optionsCount() {
        return count($this->options);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->required)) $a['required'] = $this->required;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
            'min_values' => $this->minValues,
            'max_values' => $this->maxValues,
            'disabled' => $this->disabled
        ]);
    }
}

class TextInput extends Component {
    public function __construct(public string $customId, public string $placeholder='', public string $value='', public TextInputStyle $style=TextInputStyle::SHORT, public ?int $minLength=null, public ?int $maxLength=null, public ?bool $required=true, ?int $id=null) {
        parent::__construct(ComponentType::TEXT_INPUT, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->minLength)) $a['min_length'] = $this->minLength;
        if (isset($this->maxLength)) $a['max_length'] = $this->maxLength;
        if (!empty($this->value)) $a['value'] = $this->value;
        if (!empty($this->placeholder)) $a['placeholder'] = $this->placeholder;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'style' => $this->style->value,
            'required' => $this->required
        ]);
    }
}

class UserSelect extends Component {
    public array $defaultValues = [];

    public function __construct(public string $customId, public string $placeholder='', public ?int $minValues=null, public ?int $maxValues=null, public ?bool $required=true, public bool $disabled=false, ?int $id=null) {
        parent::__construct(ComponentType::USER_SELECT, $id);
    }

    public function addDefaultValue(string $id) {
        $this->defaultValues[] = [ 'id' => $id, 'type' => 'user' ];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->minValues)) $a['min_values'] = $this->minValues;
        if (isset($this->maxValues)) $a['max_values'] = $this->maxValues;
        if (isset($this->required)) $a['required'] = $this->required;
        if (isset($this->disabled)) $a['disabled'] = $this->disabled;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'placeholder' => $this->placeholder,
            'default_values' => $this->defaultValues,
            'disabled' => $this->disabled
        ]);
    }
}

class RoleSelect extends Component {
    public array $defaultValues = [];

    public function __construct(public string $customId, public string $placeholder='', public ?int $minValues=null, public ?int $maxValues=null, public ?bool $required=true, public bool $disabled=false, ?int $id=null) {
        parent::__construct(ComponentType::ROLE_SELECT, $id);
    }

    public function addDefaultValue(string $id) {
        $this->defaultValues[] = [ 'id' => $id, 'type' => 'user' ];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->minValues)) $a['min_values'] = $this->minValues;
        if (isset($this->maxValues)) $a['max_values'] = $this->maxValues;
        if (isset($this->required)) $a['required'] = $this->required;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'placeholder' => $this->placeholder,
            'default_values' => $this->defaultValues,
            'disabled' => $this->disabled
        ]);
    }
}

class MentionableSelect extends Component {
    public array $defaultValues = [];

    public function __construct(public string $customId, public string $placeholder='', public ?int $minValues=null, public ?int $maxValues=null, public ?bool $required=true, public bool $disabled=false, ?int $id=null) {
        parent::__construct(ComponentType::MENTIONABLE_SELECT, $id);
    }

    public function addDefaultValue(string $id) {
        $this->defaultValues[] = [ 'id' => $id, 'type' => 'user' ];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->minValues)) $a['min_values'] = $this->minValues;
        if (isset($this->maxValues)) $a['max_values'] = $this->maxValues;
        if (isset($this->required)) $a['required'] = $this->required;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'placeholder' => $this->placeholder,
            'default_values' => $this->defaultValues,
            'disabled' => $this->disabled
        ]);
    }
}

class ChannelSelect extends Component {
    public array $defaultValues = [];

    public function __construct(
        public string $customId, public ?array $channelTypes=null, public string $placeholder='', public ?int $minValues=null, public ?int $maxValues=null,
        public ?bool $required=true, public bool $disabled=false, ?int $id=null
    ) {
        parent::__construct(ComponentType::CHANNEL_SELECT, $id);
    }

    public function addDefaultValue(string $id) {
        $this->defaultValues[] = [ 'id' => $id, 'type' => 'user' ];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        if (isset($this->minValues)) $a['min_values'] = $this->minValues;
        if (isset($this->maxValues)) $a['max_values'] = $this->maxValues;
        if (isset($this->required)) $a['required'] = $this->required;
        return array_merge($a,[
            'custom_id' => $this->customId,
            'channel_types' => $this->channelTypes,
            'placeholder' => $this->placeholder,
            'default_values' => $this->defaultValues,
            'disabled' => $this->disabled
        ]);
    }
}

class Section extends ComponentsContainer {
    public function __construct(public Component $accessory, ?int $id=null) {
        parent::__construct(ComponentType::SECTION, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'accessory' => $this->accessory->toArray()
        ]);
    }
}

class TextDisplay extends Component {
    public function __construct(public string $content, ?int $id=null) {
        parent::__construct(ComponentType::TEXT_DISPLAY, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'content' => $this->content
        ]);
    }
}

class Thumbnail extends Component {
    public function __construct(public string $mediaURL, public ?string $description=null, public bool $spoiler=false, ?int $id=null) {
        parent::__construct(ComponentType::THUMBNAIL, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'media' => [ 'url' => $this->mediaURL],
            'description' => $this->description,
            'spoiler' => $this->spoiler
        ]);
    }
}

class MediaGallery extends Component {
    public array $items = [];

    public function __construct(?int $id=null) {
        parent::__construct(ComponentType::MEDIA_GALLERY, $id);
    }

    public function addMediaItem(string $url, ?string $description=null, ?bool $spoiler=false) {
        $this->items[] = [
            'media' => [ 'url' => $url ],
            'description' => $description,
            'spoiler' => $spoiler
        ];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'items' => $this->items
        ]);
    }
}

class File extends Component {
    public function __construct(public string $attachmentURL, public bool $spoiler=false, ?int $id=null) {
        parent::__construct(ComponentType::FILE, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'file' => [ 'url' => $this->attachmentURL ],
            'spoiler' => $this->spoiler
        ]);
    }
}

class Separator extends Component {
    public function __construct(public bool $divider=true, public int $spacing=1, ?int $id=null) {
        parent::__construct(ComponentType::SEPARATOR, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'divider' => $this->divider,
            'spacing' => $this->spacing
        ]);
    }
}

class Container extends ComponentsContainer {
    public function __construct(public ?int $accentColor=null, public bool $spoiler=false, ?int $id=null) {
        parent::__construct(ComponentType::CONTAINER, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'accent_color' => $this->accentColor,
            'spoiler' => $this->spoiler
        ]);
    }
}

class Label extends Component {
    public function __construct(public string $label, public Component $component, public ?string $description=null, ?int $id=null) {
        parent::__construct(ComponentType::LABEL, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'label' => $this->label,
            'component' => $this->component->toArray(),
            'description' => $this->description
        ]);
    }
}

class FileUpload extends Component {
    public function __construct(public string $customId, public ?int $minValues=1, public ?int $maxValues=1, public bool $required=true, public ?array $fileTypes=null, ?int $id=null) {
        parent::__construct(ComponentType::FILE_UPLOAD, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'custom_id' => $this->customId,
            'min_values' => $this->minValues,
            'max_values' => $this->maxValues,
            'required' => $this->required,
            'file_types' => $this->fileTypes
        ]);
    }
}

class RadioGroup extends Component {
    public array $options = [];
    public function __construct(public string $customId, public bool $required=true, ?int $id=null) {
        return parent::__construct(ComponentType::RADIO_GROUP, $id);
    }

    public function addOption(string $value, string $label, ?string $description=null, bool $default=false) {
        $this->options[] = ['value' => $value, 'label' => $label, 'description' => $description, 'default' => $default];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'custom_id' => $this->customId,
            'options' => $this->options,
            'required' => $this->required
        ]);
    }
}

class CheckboxGroup extends Component {
    public array $options = [];
    public function __construct(public string $customId, public int $minValues=1, public ?int $maxValues=null, public bool $required=true, ?int $id=null) {
        return parent::__construct(ComponentType::CHECKBOX_GROUP, $id);
    }

    public function addOption(string $value, string $label, ?string $description=null, bool $default=false) {
        $this->options[] = ['value' => $value, 'label' => $label, 'description' => $description, 'default' => $default];
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'custom_id' => $this->customId,
            'options' => $this->options,
            'min_values' => $this->minValues,
            'max_values' => $this->maxValues,
            'required' => $this->required
        ]);
    }
}

class Checkbox extends Component {
    public array $options = [];
    public function __construct(public string $customId, public bool $default=false, ?int $id=null) {
        return parent::__construct(ComponentType::CHECKBOX, $id);
    }

    #[\Override]
    public function toArray() {
        $a = parent::toArray();
        return array_merge($a,[
            'custom_id' => $this->customId,
            'default' => $this->default
        ]);
    }
}
?>
