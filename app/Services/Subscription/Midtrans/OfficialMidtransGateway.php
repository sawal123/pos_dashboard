<?php

namespace App\Services\Subscription\Midtrans;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

final class OfficialMidtransGateway implements MidtransGateway
{
    public function isConfigured(): bool
    {
        return $this->serverKey() !== '';
    }

    public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse
    {
        $this->configureSdk();

        $response = Snap::createTransaction([
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => $payment->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'custom_field1' => (string) $payment->id,
            'custom_field2' => (string) $business->id,
            'custom_field3' => $payment->plan.':'.$payment->billing_period,
        ]);

        $token = isset($response->token) && is_string($response->token) ? $response->token : '';
        $redirectUrl = isset($response->redirect_url) && is_string($response->redirect_url) ? $response->redirect_url : '';

        if ($token === '' || $redirectUrl === '') {
            throw new \RuntimeException('Midtrans Snap response is incomplete.');
        }

        return new MidtransSnapResponse($token, $redirectUrl);
    }

    public function verifyNotification(array $payload): ?MidtransNotificationResult
    {
        if (! $this->hasValidSignature($payload)) {
            return null;
        }

        $orderId = $this->stringValue($payload['order_id'] ?? null);

        if ($orderId === '') {
            return null;
        }

        $this->configureSdk();

        $status = Transaction::status($orderId);
        $verified = $this->objectToArray($status);

        $transactionStatus = $this->stringValue($verified['transaction_status'] ?? $payload['transaction_status'] ?? null);

        if ($transactionStatus === '') {
            return null;
        }

        return new MidtransNotificationResult(
            orderId: $orderId,
            transactionStatus: $transactionStatus,
            fraudStatus: $this->nullableString($verified['fraud_status'] ?? $payload['fraud_status'] ?? null),
            transactionId: $this->nullableString($verified['transaction_id'] ?? $payload['transaction_id'] ?? null),
            paymentType: $this->nullableString($verified['payment_type'] ?? $payload['payment_type'] ?? null),
            payload: $verified !== [] ? $verified : $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasValidSignature(array $payload): bool
    {
        $signature = $this->stringValue($payload['signature_key'] ?? null);
        $orderId = $this->stringValue($payload['order_id'] ?? null);
        $statusCode = $this->stringValue($payload['status_code'] ?? null);
        $grossAmount = $this->stringValue($payload['gross_amount'] ?? null);

        if ($signature === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $this->serverKey() === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey());

        return hash_equals($expected, $signature);
    }

    private function configureSdk(): void
    {
        $this->loadSdkWhenAutoloadIsUnavailable();

        Config::$serverKey = $this->serverKey();
        Config::$clientKey = $this->clientKey();
        Config::$isProduction = config('premium.midtrans.is_production') === true;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    private function loadSdkWhenAutoloadIsUnavailable(): void
    {
        if (class_exists(Snap::class)) {
            return;
        }

        $path = base_path('vendor/midtrans/midtrans-php/Midtrans.php');

        if (File::exists($path)) {
            require_once $path;
        }
    }

    private function serverKey(): string
    {
        $key = config('premium.midtrans.server_key');

        return is_string($key) ? trim($key) : '';
    }

    private function clientKey(): string
    {
        $key = config('premium.midtrans.client_key');

        return is_string($key) ? trim($key) : '';
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return $string !== '' ? $string : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        }

        return [];
    }
}
