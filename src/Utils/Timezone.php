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
namespace LDLib\Utils\Timezone;

use DateTimeZone;

class Timezone {
    public static function getAliasesArray():array {
        return [
            // ─────────────────────────────────────────────
            // UTC / GMT
            // ─────────────────────────────────────────────

            'utc' => 'UTC',
            'ut' => 'UTC',
            'universal time' => 'UTC',
            'coordinated universal time' => 'UTC',
            'greenwich mean time' => 'UTC',
            'z' => 'UTC',
            'zulu' => 'UTC',

            // ─────────────────────────────────────────────
            // US / Canada — Pacific
            // ─────────────────────────────────────────────

            'pst' => 'America/Los_Angeles',
            'pdt' => 'America/Los_Angeles',
            'pt' => 'America/Los_Angeles',
            'pacific' => 'America/Los_Angeles',
            'pacific time' => 'America/Los_Angeles',
            'pacific standard' => 'America/Los_Angeles',
            'pacific standard time' => 'America/Los_Angeles',
            'pacific daylight' => 'America/Los_Angeles',
            'pacific daylight time' => 'America/Los_Angeles',
            'pacific coast' => 'America/Los_Angeles',
            'west coast' => 'America/Los_Angeles',
            'west coast time' => 'America/Los_Angeles',

            // ─────────────────────────────────────────────
            // US / Canada — Mountain
            // ─────────────────────────────────────────────

            'mst' => 'America/Denver',
            'mdt' => 'America/Denver',
            'mt' => 'America/Denver',
            'mountain' => 'America/Denver',
            'mountain time' => 'America/Denver',
            'mountain standard' => 'America/Denver',
            'mountain standard time' => 'America/Denver',
            'mountain daylight' => 'America/Denver',
            'mountain daylight time' => 'America/Denver',

            // ─────────────────────────────────────────────
            // US / Canada — Central
            // ─────────────────────────────────────────────

            'cst' => 'America/Chicago',
            'cdt' => 'America/Chicago',
            'ct' => 'America/Chicago',
            'central' => 'America/Chicago',
            'central time' => 'America/Chicago',
            'central standard' => 'America/Chicago',
            'central standard time' => 'America/Chicago',
            'central daylight' => 'America/Chicago',
            'central daylight time' => 'America/Chicago',

            // ─────────────────────────────────────────────
            // US / Canada — Eastern
            // ─────────────────────────────────────────────

            'est' => 'America/New_York',
            'edt' => 'America/New_York',
            'et' => 'America/New_York',
            'eastern' => 'America/New_York',
            'eastern time' => 'America/New_York',
            'eastern standard' => 'America/New_York',
            'eastern standard time' => 'America/New_York',
            'eastern daylight' => 'America/New_York',
            'eastern daylight time' => 'America/New_York',
            'east coast' => 'America/New_York',
            'east coast time' => 'America/New_York',

            // ─────────────────────────────────────────────
            // Alaska / Hawaii
            // ─────────────────────────────────────────────

            'akst' => 'America/Anchorage',
            'akdt' => 'America/Anchorage',
            'alaska' => 'America/Anchorage',
            'alaska time' => 'America/Anchorage',
            'alaska standard time' => 'America/Anchorage',
            'alaska daylight time' => 'America/Anchorage',

            'hst' => 'Pacific/Honolulu',
            'hdt' => 'Pacific/Honolulu',
            'hawaii' => 'Pacific/Honolulu',
            'hawaii time' => 'Pacific/Honolulu',
            'hawaiian time' => 'Pacific/Honolulu',

            // ─────────────────────────────────────────────
            // Europe — UK / Ireland
            // ─────────────────────────────────────────────

            'gmt' => 'Europe/London',
            'bst' => 'Europe/London',
            'british time' => 'Europe/London',
            'british summer time' => 'Europe/London',
            'uk time' => 'Europe/London',
            'london time' => 'Europe/London',
            'united kingdom time' => 'Europe/London',

            'irish time' => 'Europe/Dublin',
            'irish standard time' => 'Europe/Dublin',
            'irish summer time' => 'Europe/Dublin',
            'dublin time' => 'Europe/Dublin',

            // ─────────────────────────────────────────────
            // Europe — Western / Central
            // ─────────────────────────────────────────────

            'wet' => 'Europe/Lisbon',
            'west european time' => 'Europe/Lisbon',
            'west european standard time' => 'Europe/Lisbon',
            'west european summer time' => 'Europe/Lisbon',

            'cet' => 'Europe/Paris',
            'cest' => 'Europe/Paris',
            'central european time' => 'Europe/Paris',
            'central european standard time' => 'Europe/Paris',
            'central european summer time' => 'Europe/Paris',
            'central europe' => 'Europe/Paris',
            'central european' => 'Europe/Paris',

            // ─────────────────────────────────────────────
            // Europe — Eastern
            // ─────────────────────────────────────────────

            'eet' => 'Europe/Athens',
            'eest' => 'Europe/Athens',
            'eastern european time' => 'Europe/Athens',
            'eastern european standard time' => 'Europe/Athens',
            'eastern european summer time' => 'Europe/Athens',
            'eastern europe' => 'Europe/Athens',

            // ─────────────────────────────────────────────
            // Russia
            // ─────────────────────────────────────────────

            'msk' => 'Europe/Moscow',
            'moscow time' => 'Europe/Moscow',
            'moscow standard time' => 'Europe/Moscow',

            // ─────────────────────────────────────────────
            // Middle East
            // ─────────────────────────────────────────────

            'ast' => 'Asia/Riyadh',
            'arabia standard time' => 'Asia/Riyadh',
            'arabian standard time' => 'Asia/Riyadh',
            'arabia time' => 'Asia/Riyadh',

            'gst' => 'Asia/Dubai',
            'gulf standard time' => 'Asia/Dubai',
            'dubai time' => 'Asia/Dubai',
            'uae time' => 'Asia/Dubai',

            'idt' => 'Asia/Jerusalem',
            'israel daylight time' => 'Asia/Jerusalem',
            'israel standard time' => 'Asia/Jerusalem',
            'israel time' => 'Asia/Jerusalem',

            // ─────────────────────────────────────────────
            // India / South Asia
            // ─────────────────────────────────────────────

            'ist' => 'Asia/Kolkata',
            'india standard time' => 'Asia/Kolkata',
            'indian standard time' => 'Asia/Kolkata',
            'india time' => 'Asia/Kolkata',
            'kolkata time' => 'Asia/Kolkata',
            'delhi time' => 'Asia/Kolkata',
            'mumbai time' => 'Asia/Kolkata',

            // ─────────────────────────────────────────────
            // Southeast Asia
            // ─────────────────────────────────────────────

            'ict' => 'Asia/Bangkok',
            'indochina time' => 'Asia/Bangkok',
            'indochina standard time' => 'Asia/Bangkok',
            'thailand time' => 'Asia/Bangkok',
            'bangkok time' => 'Asia/Bangkok',

            'wib' => 'Asia/Jakarta',
            'western indonesia time' => 'Asia/Jakarta',

            'wita' => 'Asia/Makassar',
            'central indonesia time' => 'Asia/Makassar',

            'wit' => 'Asia/Jayapura',
            'eastern indonesia time' => 'Asia/Jayapura',

            // ─────────────────────────────────────────────
            // China / Hong Kong / Singapore
            // ─────────────────────────────────────────────

            'cst china' => 'Asia/Shanghai',
            'china standard time' => 'Asia/Shanghai',
            'china time' => 'Asia/Shanghai',
            'beijing time' => 'Asia/Shanghai',
            'shanghai time' => 'Asia/Shanghai',

            'hkt' => 'Asia/Hong_Kong',
            'hong kong time' => 'Asia/Hong_Kong',
            'hong kong standard time' => 'Asia/Hong_Kong',

            'sgt' => 'Asia/Singapore',
            'singapore time' => 'Asia/Singapore',
            'singapore standard time' => 'Asia/Singapore',

            // ─────────────────────────────────────────────
            // Japan / Korea
            // ─────────────────────────────────────────────

            'jst' => 'Asia/Tokyo',
            'japan standard time' => 'Asia/Tokyo',
            'japan time' => 'Asia/Tokyo',
            'tokyo time' => 'Asia/Tokyo',

            'kst' => 'Asia/Seoul',
            'korea standard time' => 'Asia/Seoul',
            'korea time' => 'Asia/Seoul',
            'seoul time' => 'Asia/Seoul',

            // ─────────────────────────────────────────────
            // Australia
            // ─────────────────────────────────────────────

            'awst' => 'Australia/Perth',
            'australian western standard time' => 'Australia/Perth',
            'western australia time' => 'Australia/Perth',
            'perth time' => 'Australia/Perth',

            'acst' => 'Australia/Adelaide',
            'acdt' => 'Australia/Adelaide',
            'australian central standard time' => 'Australia/Adelaide',
            'australian central daylight time' => 'Australia/Adelaide',
            'central australia time' => 'Australia/Adelaide',
            'adelaide time' => 'Australia/Adelaide',

            'aest' => 'Australia/Sydney',
            'aedt' => 'Australia/Sydney',
            'australian eastern standard time' => 'Australia/Sydney',
            'australian eastern daylight time' => 'Australia/Sydney',
            'eastern australia time' => 'Australia/Sydney',
            'sydney time' => 'Australia/Sydney',
            'melbourne time' => 'Australia/Melbourne',

            // ─────────────────────────────────────────────
            // New Zealand
            // ─────────────────────────────────────────────

            'nzst' => 'Pacific/Auckland',
            'nzdt' => 'Pacific/Auckland',
            'new zealand standard time' => 'Pacific/Auckland',
            'new zealand daylight time' => 'Pacific/Auckland',
            'new zealand time' => 'Pacific/Auckland',
            'auckland time' => 'Pacific/Auckland',

            // ─────────────────────────────────────────────
            // South America
            // ─────────────────────────────────────────────

            'art' => 'America/Argentina/Buenos_Aires',
            'argentina time' => 'America/Argentina/Buenos_Aires',
            'argentina standard time' => 'America/Argentina/Buenos_Aires',
            'buenos aires time' => 'America/Argentina/Buenos_Aires',

            'brt' => 'America/Sao_Paulo',
            'brasilia time' => 'America/Sao_Paulo',
            'brazil time' => 'America/Sao_Paulo',
            'brasilia standard time' => 'America/Sao_Paulo',

            'clt' => 'America/Santiago',
            'clst' => 'America/Santiago',
            'chile time' => 'America/Santiago',
            'santiago time' => 'America/Santiago',

            // ─────────────────────────────────────────────
            // Africa
            // ─────────────────────────────────────────────

            'wat' => 'Africa/Lagos',
            'west africa time' => 'Africa/Lagos',
            'west africa standard time' => 'Africa/Lagos',

            'cat' => 'Africa/Harare',
            'central africa time' => 'Africa/Harare',
            'central africa standard time' => 'Africa/Harare',

            'eat' => 'Africa/Nairobi',
            'east africa time' => 'Africa/Nairobi',
            'east africa standard time' => 'Africa/Nairobi',

            'sast' => 'Africa/Johannesburg',
            'south africa standard time' => 'Africa/Johannesburg',
            'south africa time' => 'Africa/Johannesburg',
            'johannesburg time' => 'Africa/Johannesburg',
        ];

    }
    public static function stringToIANATimezone(string $timezone, bool $tryResolveAliases=false):?DateTimeZone {
        $timezone = preg_replace('/\s+/',' ',trim($timezone));

        $timezoneAliases = self::getAliasesArray();
        if ($tryResolveAliases && isset($timezoneAliases[strtolower($timezone)])) return new DateTimeZone($timezoneAliases[$timezone]);

        if (preg_match('/^\w+\/\w+$/i',$timezone)) try { return new DateTimeZone($timezone); } catch (\Exception) { return null; }
        return null;
    }

    public static function stringToTimezone(string $timezone, bool $tryResolveAliases=false):?DateTimeZone {
        $timezone = preg_replace('/\s+/',' ',trim($timezone));

        $timezoneAliases = self::getAliasesArray();
        if ($tryResolveAliases && isset($timezoneAliases[strtolower($timezone)])) return new DateTimeZone($timezoneAliases[$timezone]);

        try { return new DateTimeZone($timezone); } catch (\Exception) { return null; }
    }
}
?>