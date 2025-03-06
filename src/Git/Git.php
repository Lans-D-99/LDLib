<?php
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