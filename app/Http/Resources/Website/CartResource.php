<?php

namespace App\Http\Resources\Website;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('product')->get();

        return [
            'id' => $this->id,
            'items_count' => $items->sum('quantity'),
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'items' => CartItemResource::collection($items),
        ];
    }
}
