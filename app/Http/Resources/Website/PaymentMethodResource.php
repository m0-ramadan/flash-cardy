<?php

namespace App\Http\Resources\Website;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'icon' => $this->icon,
            'icon_type' => $this->icon_type,
            'icon_url' => $this->icon_url,
            'is_active' => $this->is_active,
            'is_payment' => $this->is_payment,
            'is_wallet' => $this->is_wallet,
        ];
    }
}
