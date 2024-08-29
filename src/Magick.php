<?php
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