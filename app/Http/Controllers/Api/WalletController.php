<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PaymentResource;
use App\Http\Resources\Api\WalletResource;
use App\Models\Pricing;
use App\Models\Wallet;
use App\Services\FlexPayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class WalletController extends ApiResourceController
{
    protected string $modelClass = Wallet::class;

    protected string $resourceClass = WalletResource::class;

    public function __construct(
        private FlexPayService $flexPayService,
    ) {}

    public function myWallet(Request $request): JsonResponse
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['coins_balance' => 0]
        );

        return $this->handleResponse(WalletResource::make($wallet->load('user')), $this->apiMessage('find_success'));
    }

    public function show(int $id): JsonResponse
    {
        $wallet = Wallet::query()->where('user_id', request()->user()->id)->findOrFail($id);

        return $this->handleResponse(WalletResource::make($wallet->load('user')), $this->apiMessage('find_success'));
    }

    public function purchaseCoins(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pricing_id' => ['required', 'integer', 'exists:pricings,id'],
            'type' => ['required', 'integer', 'in:1,2'],
            'phone' => ['required_if:type,1', 'nullable', 'string', 'max:45'],
            'channel' => ['nullable', 'string', 'max:45'],
        ]);

        $pricing = Pricing::query()->findOrFail($validated['pricing_id']);
        if ($pricing->reason !== 'coin_price' || $pricing->coins_amount === null || $pricing->coins_amount < 1 || $pricing->pricing_cost === null || $pricing->pricing_cost <= 0 || blank($pricing->currency)) {
            return $this->handleError(null, __('api.wallet.invalid_coin_package'), 422);
        }

        try {
            $result = $this->flexPayService->initiate([
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'amount' => $pricing->pricing_cost,
                'currency' => strtoupper($pricing->currency),
                'phone' => $validated['phone'] ?? null,
                'description' => 'Coin package purchase',
                'channel' => $validated['channel'] ?? null,
                'reason' => 'coin_price',
                'entity' => 'pricing',
                'entity_id' => $pricing->id,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->handleError(null, 'InvalidArgumentException: '.$exception->getMessage(), 422);
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->handleError(null, __('api.payment.service_unavailable'), 503);
        } catch (RequestException $exception) {
            report($exception);

            $details = $this->flexPayRequestErrorDetails($exception);

            Log::warning('FlexPay rejected a coin purchase request.', [
                'endpoint' => 'wallet.coins.purchase',
                ...$details,
            ]);

            return $this->handleError(
                app()->environment(['local', 'testing']) ? $details : null,
                __('api.payment.request_failed'),
                502
            );
        } catch (RuntimeException $exception) {
            return $this->handleError(null, 'RuntimeException: '.$exception->getMessage(), 422);
        }

        return $this->handleResponse([
            'payment' => PaymentResource::make($result['payment']),
            'coins_amount' => $pricing->coins_amount,
            'order_number' => $result['payment']->order_number,
            'url' => $result['response']['url'] ?? null,
        ], trim(($result['response']['message'] ?? '').' '.$this->apiMessage('created', 'payment')));
    }

    /**
     * Return provider diagnostics only for the local development environment.
     *
     * @return array{provider_status: int|null, provider_response: mixed}
     */
    private function flexPayRequestErrorDetails(RequestException $exception): array
    {
        $response = $exception->response;

        return [
            'provider_status' => $response?->status(),
            'provider_response' => $response?->json() ?? $response?->body(),
        ];
    }
}
