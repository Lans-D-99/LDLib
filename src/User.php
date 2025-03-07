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