<?php
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