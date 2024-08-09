<?php
namespace BaseWebsite\User;

use Ds\Set;
use LDLib\{SuccessType,ErrorType,OperationResult};
use LDLib\Database\LDPDO;
use LDLib\User\IIdentifiableUser;
use LDLib\User\User;

class RegisteredUser extends User implements IIdentifiableUser {
    public function __construct(int $id, Set $titles, string $username, \DateTimeImmutable $registrationDate) {
        parent::__construct($id,$titles,$username,$registrationDate);
    }

    public function isAdministrator():bool {
        return $this->roles->contains('Administrator');
    }

    public static function initFromRow(array $row) {
        $data = array_key_exists('data',$row) && array_key_exists('metadata',$row) ? $row['data'] : $row;
        return new self($data['id'],new Set(explode(',',$data['roles'])),$data['name'],new \DateTimeImmutable($data['registration_date']));
    }

    public function getID():int { return $this->id; }
    public function hasRole(string $s):bool { return $this->roles->contains($s); }

    public static function validateUserInfos(LDPDO $pdo, ?string $username=null, ?string $password=null) {
        if ($username !== null) {
            if (mb_strlen($username, "utf8") > 30) return new OperationResult(ErrorType::INVALID_DATA, 'The username must not have more than 30 characters.');
            else if (preg_match(RegisteredUser::$usernameRegex, $username) < 1) return new OperationResult(ErrorType::INVALID_DATA, 'The username contains invalid characters.');
            else if ($pdo->query("SELECT * FROM users WHERE name='$username' LIMIT 1")->fetch() !== false) return new OperationResult(ErrorType::DUPLICATE, 'This username is already taken.');
        }

        if ($password !== null) {
            if (strlen($password) < 6) return new OperationResult(ErrorType::INVALID_DATA, 'The password length must be greater than 5 characters.');
            else if (strlen($password) > 150) return new OperationResult(ErrorType::INVALID_DATA, 'The password length must not be greater than 150 characters.');
        }

        return new OperationResult(SuccessType::SUCCESS);
    }
}
?>