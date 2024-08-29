<?php
namespace LDLib\User;

use Ds\Set;

abstract class User {
    public static string $usernameRegex = '/^[\w\-]+$/u';

    public readonly int $id;
    public readonly string $username;
    public readonly Set $roles;
    public readonly \DateTimeImmutable $registrationDate;

    public function __construct(int $id, Set $roles, string $username, \DateTimeImmutable $registrationDate) {
        $this->id = $id;
        $this->roles = $roles;
        $this->username = $username;
        $this->registrationDate = $registrationDate;
    }
}

abstract class UserSettings {
    
}

interface IIdentifiableUser {
    public function getID():int;
    public function hasRole(string $s):bool;
}
?>