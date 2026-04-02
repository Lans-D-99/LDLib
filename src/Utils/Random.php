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
namespace LDLib\Utils;

class Random {
    public static function rand(int $min=0, int $max=100, bool $secure=true):int {
        $rand = new \Random\Randomizer($secure ? new \Random\Engine\Secure() : new \Random\Engine\Xoshiro256StarStar());
        return $rand->getInt($min,$max);
    }

    public static function frand(float $min=0, float $max=1, bool $secure=true, \Random\IntervalBoundary $boundary=\Random\IntervalBoundary::ClosedOpen):float {
        $rand = new \Random\Randomizer($secure ? new \Random\Engine\Secure() : new \Random\Engine\Xoshiro256StarStar());
        return $rand->getFloat($min,$max,$boundary);
    }
}
?>