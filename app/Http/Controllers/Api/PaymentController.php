<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use App\Services\FlexPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PaymentController extends ApiResourceController
{
    protected string $modelClass = Payment::class;

    protected string $resourceClass = PaymentResource::class;

    public function __construct(
        private FlexPayService $flexPayService,
    ) {}

    /**
     * Endpoint called by FlexPaie to update a payment.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $payment = $this->flexPayService->handleCallback($request->all());
        } catch (RuntimeException $exception) {
            return $this->handleError(null, $exception->getMessage(), 422);
        }

        return $this->handleResponse(
            [
                'payment_id' => $payment->id,
                'order_number' => $payment->order_number,
                'status' => (int) $payment->status,
            ],
            $this->apiMessage('updated')
        );
    }

    /**
     * Manually synchronize a payment with FlexPaie's Check Transaction API.
     */
    public function check(int $id): JsonResponse
    {
        $payment = Payment::query()->findOrFail($id);

        try {
            $payment = $this->flexPayService->syncPayment($payment);
        } catch (RuntimeException $exception) {
            return $this->handleError(null, $exception->getMessage(), 422);
        }

        return $this->handleResponse(
            [
                'payment_id' => $payment->id,
                'order_number' => $payment->order_number,
                'status' => (int) $payment->status,
            ],
            __('api.payment.synchronized_success')
        );
    }
}
