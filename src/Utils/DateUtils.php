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
namespace LDLib\Utils\DateUtils;

use DateInterval;
use DateTime;

function date_at_start_of_week(string $dateString, bool $startIsSunday=false) {
    $dt = new DateTime($dateString);
    if ($startIsSunday) {
        $dayOfWeek = (int)$dt->format('w');
        if ($dayOfWeek > 0) $dt->sub(new DateInterval('P'.$dayOfWeek.'D'));
    } else {
        $dayOfWeek = (int)$dt->format('N');
        if ($dayOfWeek > 1) $dt->sub(new DateInterval('P'.($dayOfWeek-1).'D'));
    }
    return $dt;
}

function date_at_end_of_week(string $dateString, bool $startIsSunday=false) {
    $dt = new DateTime($dateString);
    if ($startIsSunday) {
        $dayOfWeek = (int)$dt->format('w');
        if ($dayOfWeek < 6) $dt->add(new DateInterval('P'.(6-$dayOfWeek).'D'));
    } else {
        $dayOfWeek = (int)$dt->format('N');
        if ($dayOfWeek < 7) $dt->add(new DateInterval('P'.(7-$dayOfWeek).'D'));
    }
    return $dt;
}
?>