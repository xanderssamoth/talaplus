<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class GiftTransactionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['sender'] = UserResource::make($this->whenLoaded('sender'));
        $data['receiver'] = UserResource::make($this->whenLoaded('receiver'));
        $data['pricing'] = PricingResource::make($this->whenLoaded('pricing'));
        $data['history'] = HistoryResource::make($this->whenLoaded('history'));

        return $data;
    }
}
