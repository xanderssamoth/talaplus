<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('specifications');

        $data = parent::toArray($request);

        $data['specifications'] = ApiResource::collection($this->whenLoaded('specifications'));
        $data['reviews'] = $this->reviewsSummary();
        $data['promotion'] = $this->promotionSummary();

        return $data;
    }

    /**
     * @return array<string, int>
     */
    private function reviewsSummary(): array
    {
        $query = $this->resource->reactions()->where('type', 'star');

        return [
            'total' => (clone $query)->distinct('user_id')->count('user_id'),
            'five_stars' => (clone $query)->where('number_of_stars', 5)->distinct('user_id')->count('user_id'),
            'four_stars' => (clone $query)->where('number_of_stars', 4)->distinct('user_id')->count('user_id'),
            'three_stars' => (clone $query)->where('number_of_stars', 3)->distinct('user_id')->count('user_id'),
            'two_stars' => (clone $query)->where('number_of_stars', 2)->distinct('user_id')->count('user_id'),
            'one_star' => (clone $query)->where('number_of_stars', 1)->distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionSummary(): array
    {
        if ($this->price_reduction_end === null || $this->price_reduction_end->isPast()) {
            return [];
        }

        $end = Carbon::parse($this->price_reduction_end);
        $now = now();
        $reductionRate = (float) ($this->reduction_rate ?? 0);
        $price = (float) ($this->price ?? 0);

        $totalMinutes = (int) $now->diffInMinutes($end);
        $days = intdiv($totalMinutes, 1440);
        $hours = intdiv($totalMinutes % 1440, 60);
        $minutes = $totalMinutes % 60;

        return [
            'remaining' => [
                'days' => $days,
                'hours' => $hours,
                'minutes' => $minutes,
            ],
            'reduction_rate' => $reductionRate,
            'reduced_price' => max(0, $price - ($price * $reductionRate)),
        ];
    }
}
