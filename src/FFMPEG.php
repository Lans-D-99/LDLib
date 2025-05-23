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
namespace LDLib\FFMPEG;

use LDLib\ErrorType;
use LDLib\OperationResult;
use LDLib\SuccessType;
use LDLib\Utils\Utils;

class FFMPEG {
    public static function toMp4(array $file) {
        $mimeType = Utils::getMimeType($file);
        if (!str_starts_with($mimeType,'video/') && $mimeType !== 'image/gif') return new OperationResult(ErrorType::INVALID_DATA, "File not supported. (mimeType: $mimeType)", [], [$file]);

        $filePath = $file['tmp_name'];
        $newFilePath = "$filePath.mp4";

        $newFileName = preg_replace('/\..*$/', '.mp4', $file['name'],1,$c);
        if ($c == 0) $newFileName .= '.mp4';

        exec("ffmpeg -i \"$filePath\" -movflags +faststart -pix_fmt yuv420p -vcodec libx264 -acodec aac -vf \"scale=trunc(iw/2)*2:trunc(ih/2)*2\" \"$newFilePath\"",result_code:$code);

        if ($code !== 0) return new OperationResult(ErrorType::FILE_OPERATION_ERROR, "Couldn't process file.", [], [$file]);

        $newFile = [
            'name' => $newFileName,
            'tmp_name' => $newFilePath,
            'size' => filesize($newFilePath),
            'type' => 'video/mp4',
            'error' => 0
        ];
        return new OperationResult(SuccessType::SUCCESS, 'Video successfully converted to MP4.', [], [$newFile]);
    }

    public static function toWebm(array $file, int $crf=23) {
        $mimeType = Utils::getMimeType($file);
        if (!str_starts_with($mimeType,'video/') && $mimeType !== 'image/gif') return new OperationResult(ErrorType::INVALID_DATA, "File not supported. (mimeType: $mimeType)", [], [$file]);

        $filePath = $file['tmp_name'];
        $newFilePath = "$filePath.webm";

        $newFileName = preg_replace('/\..*$/', '.webm', $file['name'],1,$c);
        if ($c == 0) $newFileName .= '.webm';

        exec("ffmpeg -i \"$filePath\" -c:v libvpx-vp9 -pix_fmt yuv420p -b:v 0 -crf $crf \"$newFilePath\"",result_code:$code);

        if ($code !== 0) return new OperationResult(ErrorType::FILE_OPERATION_ERROR, "Couldn't process file.", [], [$file]);

        $newFile = [
            'name' => $newFileName,
            'tmp_name' => $newFilePath,
            'size' => filesize($newFilePath),
            'type' => 'video/webm',
            'error' => 0
        ];

        return new OperationResult(SuccessType::SUCCESS, 'Video successfully converted to WEBM.', [], [$newFile]);
    }
}
?>