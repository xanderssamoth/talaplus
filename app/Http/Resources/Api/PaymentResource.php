<?php

namespace App\Http\Resources\Api;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            ...self::labels($this->resource),
        ];
    }

    /**
     * @return array{type_text: ?string, status_text: ?string, reason_text: ?string}
     */
    public static function labels(Payment $payment): array
    {
        $typeKey = match ($payment->type) {
            1 => 'mobile_money',
            2 => 'bank_card',
            default => null,
        };

        $statusKey = match ($payment->status) {
            0 => 'completed',
            1 => 'pending',
            2 => 'failed',
            default => null,
        };

        $reasonKey = in_array($payment->reason, [
            'media_create', 'media_boost', 'gift', 'product_sale',
            'user_certfied', 'ad', 'coin_price', 'earnings_send',
        ], true) ? $payment->reason : null;

        return [
            'type_text' => $typeKey !== null ? __("api.payment.types.{$typeKey}") : null,
            'status_text' => $statusKey !== null ? __("api.payment.statuses.{$statusKey}") : null,
            'reason_text' => $reasonKey !== null ? __("api.payment.reasons.{$reasonKey}") : null,
        ];
    }
}
