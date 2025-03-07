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
namespace LDLib\Magick;

use LDLib\ErrorType;
use LDLib\OperationResult;
use LDLib\SuccessType;
use LDLib\Utils\Utils;

class Magick {
    public static function toWebp(array $file, int $quality=85, string $resize='') {
        $mimeType = Utils::getMimeType($file);
        if (!str_starts_with($mimeType,'image/')) return new OperationResult(ErrorType::INVALID_DATA, "File not supported. (mimeType: $mimeType)", [], [$file]);
        if ($mimeType === 'image/webp') return new OperationResult(SuccessType::SUCCESS, "No operation needed.", [], [$file]);

        $filePath = $file['tmp_name'];
        $newFilePath = "$filePath.webp";

        $newFileName = $file['name'];
        $newFileName = preg_replace('/\..*$/', '.webp', $newFileName,1,$c);
        if ($c == 0) $newFileName .= '.webp';

        $sResize = !empty($resize) ? "-resize $resize" : '';

        if (PHP_OS_FAMILY == 'Windows') exec("magick \"$filePath\" -quality $quality -auto-orient $sResize \"$newFilePath\"",result_code:$code);
        else exec("convert \"$filePath\" -quality $quality -auto-orient $sResize \"$newFilePath\"",result_code:$code);

        if ($code !== 0) return new OperationResult(ErrorType::FILE_OPERATION_ERROR, "Couldn't process image.", [], [$file]);

        $newFile = [
            'name' => $newFileName,
            'tmp_name' => $newFilePath,
            'size' => filesize($newFilePath),
            'type' => 'image/webp',
            'error' => 0
        ];
        return new OperationResult(SuccessType::SUCCESS, 'Image successfully converted to Webp.', [], [$newFile]);
    }
}
?>