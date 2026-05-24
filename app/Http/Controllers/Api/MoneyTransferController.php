<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\MoneyTransferResource;
use App\Models\MoneyTransfer;

final class MoneyTransferController extends ApiResourceController
{
    protected string $modelClass = MoneyTransfer::class;

    protected string $resourceClass = MoneyTransferResource::class;
}
