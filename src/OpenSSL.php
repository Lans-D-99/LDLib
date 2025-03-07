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