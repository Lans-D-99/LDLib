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

use Ds\Map;
use Random\{Randomizer,IntervalBoundary};

class MapUtils {
    public static function incr(Map $map, string $k) {
        if ($map->hasKey($k)) $map[$k] = ++$map[$k];
        else $map[$k] = 1;
    }

    public static function weightedMap_GetRandomKey(Map $map) {
        $totalWeight = 0;
        $a = $map->toArray();

        foreach ($a as $k => $v) $totalWeight += $v;
        $randV = (new Randomizer())->getFloat(0,$totalWeight,IntervalBoundary::OpenOpen);

        $cumWeight = 0;
        foreach ($a as $k => $v) {
            $cumWeight += $v;
            if ($cumWeight > $randV) return $k;
        }

        throw new \Exception("Weighted Map Random Key Failure (totWeight: $totalWeight | cumWeight: $cumWeight)");
    }
}
?>