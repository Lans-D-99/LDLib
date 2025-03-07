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
namespace LDLib\Stripe;

class StripeClient {
    public function __construct(private ?string $secretKey=null) {
        $this->secretKey ??= $_SERVER['LD_STRIPE_SECRET_KEY']??'';
    }

    public function newPaymentIntent(int $amount, string $currency) {
        $ch = curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch,[
            CURLOPT_USERPWD => "{$this->secretKey}:",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                "amount" => $amount,
                "currency" => $currency
            ]),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $v = curl_exec($ch);

        return $v;
    }
}
?>