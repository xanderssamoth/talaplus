<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Payment;

class PaymentNotificationService
{
    public function record(Payment $payment): void
    {
        $type = match ($payment->status) {
            Payment::STATUS_SUCCESSFUL => 'payment_successful',
            Payment::STATUS_FAILED => 'payment_failed',
            default => null,
        };

        if ($type === null || $payment->user_id === null) {
            return;
        }

        AdminNotification::query()->updateOrCreate(
            ['payment_id' => $payment->id],
            ['type' => $type, 'to_user_id' => $payment->user_id]
        );
    }
}
