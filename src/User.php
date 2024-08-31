<?php
namespace LDLib\User;

use Ds\Set;

abstract class User implements IIdentifiableUser {
    public static string $usernameRegex = '/^[\w\-]+$/u';

    public readonly int $id;
    public readonly string $username;
    public readonly Set $roles;

    public function __construct(int $id, Set $roles, string $username) {
        $this->id = $id;
        $this->roles = $roles;
        $this->username = $username;
    }

    public function getID():int { return $this->id; }
    public function hasRole(string $s):bool { return $this->roles->contains($s); }
}

abstract class UserSettings {
    
}

interface IIdentifiableUser {
    public function getID():int;
    public function hasRole(string $s):bool;
}
?>