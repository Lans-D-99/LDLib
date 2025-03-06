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
?>