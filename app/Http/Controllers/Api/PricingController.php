<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PricingResource;
use App\Models\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PricingController extends ApiResourceController
{
    protected string $modelClass = Pricing::class;

    protected string $resourceClass = PricingResource::class;

    public function index(Request $request): JsonResponse
    {
        $pricings = Pricing::query()->latest('id')->get();
        $grouped = $pricings
            ->groupBy(fn (Pricing $pricing): string => $pricing->reason ?? 'unassigned')
            ->map(fn ($items): array => PricingResource::collection($items)->resolve($request));

        return $this->handleResponse($grouped, $this->apiMessage('find_all_success'), null, $pricings->count());
    }

    public function findByReason(Request $request, string $reason): JsonResponse
    {
        $pricings = Pricing::query()->where('reason', $reason)->latest('id')->get();

        return $this->handleResponse(
            PricingResource::collection($pricings)->resolve($request),
            $this->apiMessage('find_all_success'),
            null,
            $pricings->count()
        );
    }
}
