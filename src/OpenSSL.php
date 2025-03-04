<?php
namespace LDLib\OpenSSL;

use LDLib\Utils\Utils;

class OpenSSL {
    public static function encrypt_rsaoaep(string $publicKey, string $data, string $paddingMode='oaep', string $hashFunction='sha512'):string|false|null {
        $dataFilePath = Utils::getTempFilePath('data');
        $pubKeyFilePath = Utils::getTempFilePath('pubKey');
        file_put_contents($dataFilePath,$data);
        file_put_contents($pubKeyFilePath,$publicKey);
        $res = shell_exec("openssl pkeyutl -encrypt -pubin -inkey \"$pubKeyFilePath\" -in \"$dataFilePath\" -pkeyopt rsa_padding_mode:$paddingMode -pkeyopt rsa_oaep_md:$hashFunction");
        unlink($dataFilePath);
        unlink($pubKeyFilePath);
        return $res;
    }
}
?>