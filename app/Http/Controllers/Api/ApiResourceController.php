<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

abstract class ApiResourceController extends BaseController
{
    /**
     * @var class-string<Model>
     */
    protected string $modelClass;

    /**
     * @var class-string<JsonResource>
     */
    protected string $resourceClass;

    protected ?string $translationKey = null;

    public function index(Request $request): JsonResponse
    {
        $records = ($this->modelClass)::query()->latest('id')->paginate(10)->withQueryString();

        return $this->handleResponse(
            ($this->resourceClass)::collection($records),
            $this->apiMessage('find_all_success'),
            $records->lastPage(),
            $records->total()
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->handleResponse(
            ($this->resourceClass)::make(($this->modelClass)::query()->findOrFail($id)),
            $this->apiMessage('find_success')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $record = new ($this->modelClass)();
        $record->fill($this->payload($request));
        $record->save();

        return $this->handleResponse(
            ($this->resourceClass)::make($record->refresh()),
            $this->apiMessage('created')
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = ($this->modelClass)::query()->findOrFail($id);
        $record->fill($this->payload($request));
        $record->save();

        return $this->handleResponse(
            ($this->resourceClass)::make($record->refresh()),
            $this->apiMessage('updated')
        );
    }

    public function destroy(int $id): JsonResponse
    {
        ($this->modelClass)::query()->findOrFail($id)->delete();

        return $this->handleResponse(null, $this->apiMessage('deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $model = new ($this->modelClass)();

        return Arr::only($request->all(), $model->getFillable());
    }

    protected function apiMessage(string $action, ?string $entity = null): string
    {
        $entity ??= $this->translationKey ?? Str::snake(class_basename($this->modelClass));

        return __("api.entities.{$entity}.{$action}");
    }

    /**
     * Starts a FlexPay mobile-money or bank-card transaction and stores its pending payment.
     *
     * @param  array{
     *     user_id: int,
     *     type: 1|2,
     *     amount: int|float|string,
     *     currency: 'USD'|'CDF',
     *     phone?: string|null,
     *     description?: string|null,
     *     callback_url: string,
     *     approve_url?: string|null,
     *     cancel_url?: string|null,
     *     decline_url?: string|null,
     *     channel?: string|null,
     *     amount_customer?: int|float|string|null,
     *     reason?: 'media_create'|'media_boost'|'gift'|'product_sale'|'user_certfied'|'ad'|null,
     *     entity?: 'media'|'cart'|'user'|'pricing'|null,
     *     entity_id?: int|null
     * }  $attributes
     * @return array{payment: Payment, response: array<string, mixed>}
     */
    protected function initiateFlexPayPayment(array $attributes): array
    {
        $this->validateFlexPayPaymentAttributes($attributes);

        $user = User::query()->findOrFail($attributes['user_id']);
        $reference = sprintf('REF-%08d-%d', random_int(0, 99999999), $user->id);
        $type = (int) $attributes['type'];
        $payload = [
            'merchant' => config('services.flexpay.merchant'),
            'type' => $type,
            'reference' => $reference,
            'amount' => $attributes['amount'],
            'currency' => $attributes['currency'],
        ];

        if ($type === 1) {
            $payload['phone'] = $attributes['phone'];
            $payload['callbackUrl'] = $attributes['callback_url'];
        } else {
            $payload['description'] = $attributes['description'] ?? '';
            $payload['callback_url'] = $attributes['callback_url'];
            $payload['approve_url'] = $attributes['approve_url'];
            $payload['cancel_url'] = $attributes['cancel_url'];
            $payload['decline_url'] = $attributes['decline_url'];
        }

        $response = Http::acceptJson()
            ->withToken((string) config('services.flexpay.api_token'))
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], throw: false)
            ->post($this->flexPayGateway($type), $payload)
            ->throw()
            ->json();

        if (($response['code'] ?? null) !== '0' && ($response['code'] ?? null) !== 0) {
            throw new RuntimeException((string) ($response['message'] ?? 'FlexPay rejected the transaction request.'));
        }

        $orderNumber = $response['orderNumber'] ?? null;
        if (! is_string($orderNumber) || $orderNumber === '') {
            throw new RuntimeException('FlexPay did not return an order number.');
        }

        $payment = Payment::query()->firstOrCreate(
            ['order_number' => $orderNumber],
            [
                'reference' => $response['reference'] ?? $reference,
                'provider_reference' => $response['provider_reference'] ?? null,
                'amount' => $attributes['amount'],
                'amount_customer' => $attributes['amount_customer'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'currency' => $attributes['currency'],
                'channel' => $attributes['channel'] ?? null,
                'type' => $type,
                'status' => 1,
                'reason' => $attributes['reason'] ?? null,
                'entity' => $attributes['entity'] ?? null,
                'entity_id' => $attributes['entity_id'] ?? null,
                'user_id' => $user->id,
            ]
        );

        return ['payment' => $payment, 'response' => $response];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateFlexPayPaymentAttributes(array $attributes): void
    {
        if (! isset($attributes['user_id'], $attributes['type'], $attributes['amount'], $attributes['currency'], $attributes['callback_url'])) {
            throw new InvalidArgumentException('The user_id, type, amount, currency, and callback_url payment attributes are required.');
        }

        if (! in_array((string) $attributes['type'], ['1', '2'], true)) {
            throw new InvalidArgumentException('The FlexPay payment type must be 1 (mobile money) or 2 (bank card).');
        }

        if (! is_numeric($attributes['amount']) || (float) $attributes['amount'] <= 0) {
            throw new InvalidArgumentException('The FlexPay payment amount must be greater than zero.');
        }

        if (! in_array($attributes['currency'], ['USD', 'CDF'], true)) {
            throw new InvalidArgumentException('The FlexPay payment currency must be USD or CDF.');
        }

        if ((int) $attributes['type'] === 1 && empty($attributes['phone'])) {
            throw new InvalidArgumentException('A phone number is required for a FlexPay mobile-money payment.');
        }

        if ((int) $attributes['type'] === 2 && (! isset($attributes['approve_url'], $attributes['cancel_url'], $attributes['decline_url']))) {
            throw new InvalidArgumentException('The approval, cancellation, and decline URLs are required for a FlexPay bank-card payment.');
        }
    }

    private function flexPayGateway(int $type): string
    {
        $gateway = $type === 1
            ? config('services.flexpay.gateway_mobile')
            : config('services.flexpay.gateway_card');

        if (! is_string($gateway) || $gateway === '') {
            throw new RuntimeException('The FlexPay gateway URL is not configured.');
        }

        return $gateway;
    }
}
