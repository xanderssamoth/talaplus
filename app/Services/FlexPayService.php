<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlexPayService
{
    /**
     * Initiate a payment with FlexPaie.
     */
    public function initiate(array $attributes): array
    {
        $this->validateAttributes($attributes);

        $user = User::query()->findOrFail($attributes['user_id']);
        $reference = sprintf('REF-%08d-%d', random_int(0, 99999999), $user->id);
        $type = (int) $attributes['type'];
        $amount = $attributes['amount'];
        $currency = strtoupper((string) $attributes['currency']);
        $callbackUrl = getApiURL().'/payment/store';
        $approveUrl = getWebURL()."/paid/{$amount}/{$currency}/0/{$user->id}";
        $cancelUrl = getWebURL()."/paid/{$amount}/{$currency}/1/{$user->id}";
        $declineUrl = getWebURL()."/paid/{$amount}/{$currency}/2/{$user->id}";

        $payload = [
            'merchant' => config('services.flexpay.merchant'),
            'type' => $type,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
        ];

        if ($type === 1) {
            $payload['phone'] = $attributes['phone'];
            $payload['callbackUrl'] = $callbackUrl;
        } else {
            $payload['description'] = $attributes['description'] ?? '';

            $payload['callback_url'] = $callbackUrl;
            $payload['approve_url'] = $approveUrl;
            $payload['cancel_url'] = $cancelUrl;
            $payload['decline_url'] = $declineUrl;
        }

        $response = Http::acceptJson()
            ->withToken((string) config('services.flexpay.api_token'))
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], throw: false)
            ->post($this->gateway($type), $payload)
            ->throw()
            ->json();

        $code = (string) ($response['code'] ?? '');

        if ($code !== '0') {
            throw new RuntimeException((string) ($response['message'] ?? __('api.payment.flexpay_rejected')));
        }

        $orderNumber = $response['orderNumber'] ?? null;

        if (! is_string($orderNumber) || $orderNumber === '') {
            throw new RuntimeException(__('api.payment.order_number_missing'));
        }

        /*
         * Important:
         *
         * code = 0 does NOT mean that TALA+ should credit Coins here.
         *
         * The definitive payment status will be received later
         * through the FlexPaie callback and stored in payments.status.
         */
        $payment = Payment::query()->firstOrCreate(
            [
                'order_number' => $orderNumber,
            ],
            [
                'reference' => $response['reference'] ?? $reference,
                'provider_reference' => $response['provider_reference'] ?? null,
                'amount' => $amount,
                'amount_customer' => $attributes['amount_customer'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'currency' => $currency,
                'channel' => $attributes['channel'] ?? null,

                /*
                 * The initial FlexPaie request is still pending
                 * from the application's point of view.
                 *
                 * The definitive value comes from the callback.
                 */
                'status' => 1,

                'reason' => $attributes['reason'] ?? null,
                'entity' => $attributes['entity'] ?? null,
                'entity_id' => $attributes['entity_id'] ?? null,
                'user_id' => $user->id,
                'type' => $type,
            ]
        );

        return [
            'payment' => $payment,
            'response' => $response,
        ];
    }

    /**
     * Check a transaction directly with FlexPaie.
     */
    public function checkTransaction(string $orderNumber): array
    {
        if (blank($orderNumber)) {
            throw new RuntimeException(
                __('api.payment.order_number_required')
            );
        }

        $baseUrl = config('services.flexpay.gateway_check');

        if (blank($baseUrl)) {
            throw new RuntimeException(__('api.payment.check_gateway_not_configured'));
        }

        $url = rtrim($baseUrl, '/').'/'.urlencode($orderNumber);

        return Http::acceptJson()
            ->withToken((string) config('services.flexpay.api_token'))
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], throw: false)
            ->get($url)
            ->throw()
            ->json();
    }

    /**
     * Synchronize a local payment with FlexPaie's Check Transaction API.
     */
    public function syncPayment(Payment $payment): Payment
    {
        if (blank($payment->order_number)) {
            throw new RuntimeException(__('api.payment.payment_order_number_missing'));
        }

        $response = $this->checkTransaction($payment->order_number);

        /*
         * For Check Transaction:
         *
         * code = 0 -> transaction found
         * code != 0 -> transaction could not be found / problem
         */
        if ((string) ($response['code'] ?? '') !== '0') {
            throw new RuntimeException((string) ($response['message'] ?? __('api.payment.transaction_not_found')));
        }

        $transaction = $response['transaction'] ?? null;

        if (! is_array($transaction)) {
            throw new RuntimeException(__('api.payment.transaction_details_missing'));
        }

        $payment->forceFill([
            'order_number' => $transaction['orderNumber'] ?? $payment->order_number,
            'reference' => $transaction['reference'] ?? $payment->reference,
            'amount' => $transaction['amount'] ?? $payment->amount,
            'amount_customer' => $transaction['amountCustomer'] ?? $payment->amount_customer,
            'currency' => $transaction['currency'] ?? $payment->currency,
            /*
             * Check Transaction:
             *
             * status = 0 -> paiement abouti
             * status = 1 -> paiement non abouti
             */
            'status' => isset($transaction['status']) ? (int) $transaction['status'] : $payment->status,
        ])->save();

        return $payment->refresh();
    }

    /**
     * Update a payment from the FlexPaie callback.
     */
    public function handleCallback(array $data): Payment
    {
        $orderNumber = $data['orderNumber'] ?? null;

        if (blank($orderNumber)) {
            throw new RuntimeException(__('api.payment.callback_order_number_missing'));
        }

        $payment = Payment::query()->where('order_number', $orderNumber)->first();

        if (! $payment) {
            throw new RuntimeException(__('api.payment.callback_payment_not_found'));
        }

        $payment->forceFill([
            'reference' => $data['reference'] ?? $payment->reference,
            'provider_reference' => $data['provider_reference'] ?? $data['providerReference'] ?? $payment->provider_reference,
            'amount' => $data['amount'] ?? $payment->amount,
            'amount_customer' => $data['amountCustomer'] ?? $data['amount_customer'] ?? $payment->amount_customer,
            'currency' => $data['currency'] ?? $payment->currency,
            'status' => isset($data['status']) ? (int) $data['status'] : $payment->status,
        ])->save();

        return $payment->refresh();
    }

    private function validateAttributes(array $attributes): void
    {
        foreach (['user_id', 'type', 'amount', 'currency'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                throw new RuntimeException(__('api.payment.required_attribute', ['attribute' => $field]));
            }
        }

        if (! is_numeric($attributes['user_id'])) {
            throw new RuntimeException(__('api.payment.user_id_invalid'));
        }

        $type = (int) $attributes['type'];

        if (! in_array($type, [1, 2], true)) {
            throw new RuntimeException(__('api.payment.invalid_type'));
        }

        if (! is_numeric($attributes['amount']) || (float) $attributes['amount'] <= 0) {
            throw new RuntimeException(__('api.payment.invalid_amount'));
        }

        $currency = strtoupper((string) $attributes['currency']);

        if (! in_array($currency, ['USD', 'CDF'], true)) {
            throw new RuntimeException(__('api.payment.invalid_currency'));
        }

        if ($type === 1 && blank($attributes['phone'] ?? null)) {
            throw new RuntimeException(__('api.payment.phone_required'));
        }

    }

    private function gateway(int $type): string
    {
        $gateway = $type === 1 ? config('services.flexpay.gateway_mobile') : config('services.flexpay.gateway_card');

        if (blank($gateway)) {
            throw new RuntimeException(__('api.payment.gateway_not_configured'));
        }

        return $gateway;
    }
}
