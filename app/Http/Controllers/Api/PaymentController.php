<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use App\Services\FlexPayService;
use App\Services\PaymentNotificationService;
use App\Services\PremiumService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class PaymentController extends ApiResourceController
{
    protected string $modelClass = Payment::class;

    protected string $resourceClass = PaymentResource::class;

    public function __construct(
        private FlexPayService $flexPayService,
        private PremiumService $premiumService,
        private PaymentNotificationService $paymentNotificationService,
    ) {}

    /**
     * Endpoint called by FlexPaie to update a payment.
     */
    public function store(Request $request): JsonResponse
    {
        $hasFinalStatus = $request->has('status')
            && in_array((int) $request->input('status'), [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED], true);

        try {
            $payment = $this->flexPayService->handleCallback($request->all());
        } catch (RuntimeException $exception) {
            return $this->handleError(null, $exception->getMessage(), 422);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $this->handleError(null, __('api.payment.service_unavailable'), 503);
        }

        if ($hasFinalStatus && $payment->reason === 'user_certfied' && in_array($payment->status, [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED], true)) {
            try {
                if ($payment->status === Payment::STATUS_SUCCESSFUL) {
                    $this->premiumService->completePayment($payment);
                } else {
                    $this->premiumService->settleFailedPayment($payment);
                }
            } catch (Throwable $exception) {
                report($exception);

                if ($payment->status === Payment::STATUS_FAILED) {
                    return $this->handleError(null, __('api.premium.payment_unverified'), 503);
                }
            }

            $payment->refresh();
        }

        if ($hasFinalStatus && in_array($payment->status, [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED], true)) {
            $this->paymentNotificationService->record($payment);
        }

        return $this->handleResponse(
            [
                'payment_id' => $payment->id,
                'order_number' => $payment->order_number,
                'status' => $payment->status,
                ...PaymentResource::labels($payment),
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
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $this->handleError(null, __('api.payment.service_unavailable'), 503);
        }

        if ($payment->reason === 'user_certfied' && in_array($payment->status, [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED], true)) {
            try {
                if ($payment->status === Payment::STATUS_SUCCESSFUL) {
                    $this->premiumService->completePayment($payment);
                } else {
                    $this->premiumService->settleFailedPayment($payment);
                }
            } catch (Throwable $exception) {
                report($exception);

                return $this->handleError(null, __('api.premium.payment_unverified'), 503);
            }
        }

        if (in_array($payment->status, [Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED], true)) {
            $this->paymentNotificationService->record($payment);
        }

        return $this->handleResponse(
            [
                'payment_id' => $payment->id,
                'order_number' => $payment->order_number,
                'status' => $payment->status,
                ...PaymentResource::labels($payment),
            ],
            __('api.payment.synchronized_success')
        );
    }
}
