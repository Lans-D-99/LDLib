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
namespace LDDev\Utils\Units;

class Units {
    public static function cmToFeetInches(float $cm):string {
        $totalInches = $cm / 2.54;

        $feet = floor($totalInches/12);
        $inches = $totalInches-($feet*12);
        // Round inches to 1 decimal place
        $inches = round($inches, 1);
        // Handle rounding to exactly 12 inches
        if ($inches >= 12) { $feet++; $inches = 0; }

        return sprintf("%d' %.1f\"", $feet, $inches);
    }

    public static function heightToCm(string $height):?float {
        $height = strtolower(trim($height));

        // 178 cm / 178
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:cm|centimeters?)?$/',$height,$m) > 0)
            return (float)$m[1];

        // 1.78 m
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:m|meters?)$/',$height,$m) > 0)
            return (float)$m[1]*100;

        // 70 in / 70 inches
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:in|inch|inches)$/',$height,$m) > 0)
            return (float)$m[1]*2.54;

        // 5'10", 5' 10", 5ft 10in, 5 feet 10 inches
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:\'|ft|feet)\s*(\d+(?:\.\d+)?)?\s*(?:"|in|inch|inches)?$/',$height,$m) > 0) {
            $feet = (float)$m[1];
            $inches = isset($m[2]) && $m[2] !== '' ? (float)$m[2] : 0;

            if ($inches >= 12) return null; // Inches must be less than 12

            return ($feet*30.48)+($inches*2.54);
        }

        return null;
    }

    public static function weightToKg(string $weight):?float {
        $weight = strtolower(trim($weight));

        // 80 kg / 80
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:kg|kgs|kilograms?)?$/',$weight,$m) > 0)
            return (float)$m[1];

        // 176 lb / 176 lbs / 176 pounds
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:lb|lbs|pounds?)$/',$weight,$m) > 0)
            return (float)$m[1]*0.45359237;

        // 12 st / 12 stone
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:st|stone)$/',$weight,$m) > 0)
            return (float)$m[1]*6.35029318;

        // 12 st 4 lb / 12 stone 4 pounds
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:st|stone)\s*(\d+(?:\.\d+)?)?\s*(?:lb|lbs|pounds)?$/',$weight,$m) > 0) {
            $stone = (float)$m[1];
            $pounds = isset($m[2]) && $m[2] !== '' ? (float)$m[2] : 0;
            if ($pounds >= 14) return null;
            return ($stone*6.35029318)+($pounds*0.45359237);
        }

        return null;
    }
}
?>