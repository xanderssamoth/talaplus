<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;

final class PaymentController extends ApiResourceController
{
    protected string $modelClass = Payment::class;

    protected string $resourceClass = PaymentResource::class;
}
