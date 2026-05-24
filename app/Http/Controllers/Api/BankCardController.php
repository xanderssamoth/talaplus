<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\BankCardResource;
use App\Models\BankCard;

final class BankCardController extends ApiResourceController
{
    protected string $modelClass = BankCard::class;

    protected string $resourceClass = BankCardResource::class;
}
