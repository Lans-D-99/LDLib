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
namespace LDLib\Git;

use CzProject\GitPhp\GitRepository;

class Git {
    public static ?GitRepository $repo = null;
    public static function init(string $directory):void {
        $git = new \CzProject\GitPhp\Git();
        self::$repo = $git->open($directory);
    }
}

class User {
    public function __construct(
        public string $name,
        public string $email
    ) { }
}

class CommitAuthor extends User {
    public function __construct(
        public string $name,
        public string $email,
        public \DateTimeInterface $date,
        public ?string $avatarUrl = null
    ) { parent::__construct($name,$email); }
}

class Commit {
    public function __construct(
		public string $id,
		public string $message,
		public ?string $messageBody,
        public \DateTimeInterface $committedDate,
        public \DateTimeInterface $authoredDate,
		public CommitAuthor $author
    ) { }

    public static function loadFromGitPhp(\CzProject\GitPhp\Commit $commit):self {
        $author = new CommitAuthor(
            $commit->getAuthorName(),
            $commit->getAuthorEmail(),
            $commit->getAuthorDate()
        );
        return new self(
            $commit->getId()->toString(), $commit->getSubject(), $commit->getBody(), $commit->getCommitterDate(),
            $commit->getAuthorDate(), $author
        );
    }

    public static function loadFromGitNode(array $a):self {
        $author = new CommitAuthor(
            $a['author']['name'],
            $a['author']['email'],
            new \DateTimeImmutable($a['author']['date']),
            @$a['author']['avatarUrl']
        );
        return new self(@$a['oid'], $a['message'], $a['messageBody'], new \DateTimeImmutable($a['committedDate']), new \DateTimeImmutable($a['authoredDate']), $author);
    }
}
?>