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

class PollMedia {
    public ?string $text = null;
    public ?array $emoji = null;

    public static function fromArray(array $data): self {
        $media = new self();

        $media->text = $data['text']??null;
        $media->emoji = $data['emoji']??null;

        return $media;
    }
}

class PollAnswer {
    public int $answerId;
    public PollMedia $pollMedia;

    public static function fromArray(array $data): self {
        $answer = new self();

        $answer->answerId = $data['answer_id'];
        $answer->pollMedia = PollMedia::fromArray($data['poll_media']);

        return $answer;
    }
}

class PollAnswerCount {
    public int $id;
    public int $count;
    public bool $meVoted;

    public static function fromArray(array $data): self {
        $count = new self();

        $count->id = $data['id'];
        $count->count = $data['count'];
        $count->meVoted = $data['me_voted'];

        return $count;
    }
}

class PollResults {
    public bool $isFinalized;

    /** @var PollAnswerCount[] */
    public array $answerCounts = [];

    public static function fromArray(array $data): self {
        $results = new self();

        $results->isFinalized = $data['is_finalized'];

        $results->answerCounts = array_map(
            fn(array $item) => PollAnswerCount::fromArray($item),
            $data['answer_counts']??[]
        );

        return $results;
    }
}

class Poll {
    public PollMedia $question;

    /** @var PollAnswer[] */
    public array $answers = [];

    public ?string $expiry = null;
    public bool $allowMultiselect = false;
    public int $layoutType = 1;
    public ?PollResults $results = null;

    public static function fromArray(array $data): self {
        $poll = new self();

        $poll->question = PollMedia::fromArray($data['question']);

        $poll->answers = array_map(
            fn(array $item) => PollAnswer::fromArray($item),
            $data['answers']??[]
        );

        $poll->expiry = $data['expiry']??null;
        $poll->allowMultiselect = $data['allow_multiselect']??false;
        $poll->layoutType = $data['layout_type']??1;

        $poll->results = isset($data['results'])
            ? PollResults::fromArray($data['results'])
            : null;

        return $poll;
    }
}
?>