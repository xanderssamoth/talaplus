<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\GiftTransactionResource;
use App\Models\GiftTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GiftTransactionController extends ApiResourceController
{
    protected string $modelClass = GiftTransaction::class;

    protected string $resourceClass = GiftTransactionResource::class;

    public function myTransactions(Request $request): JsonResponse
    {
        $transactions = GiftTransaction::query()
            ->where(function ($query) use ($request): void {
                $query->where('sender_id', $request->user()->id)
                    ->orWhere('receiver_id', $request->user()->id);
            })
            ->with(['sender', 'receiver', 'pricing', 'history'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(GiftTransactionResource::collection($transactions), $this->apiMessage('find_all_success'), $transactions->lastPage(), $transactions->total());
    }

    public function show(int $id): JsonResponse
    {
        $transaction = GiftTransaction::query()
            ->where(function ($query): void {
                $query->where('sender_id', request()->user()->id)
                    ->orWhere('receiver_id', request()->user()->id);
            })
            ->with(['sender', 'receiver', 'pricing', 'history'])
            ->findOrFail($id);

        return $this->handleResponse(GiftTransactionResource::make($transaction), $this->apiMessage('find_success'));
    }
}
