<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'type' => $this->type,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'reorder_level' => $this->reorder_level,
            'unit' => $this->unit,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'taxes' => $this->whenLoaded('taxes', fn () => $this->taxes->map->only(['id', 'name', 'rate'])),
            'stock_quantity' => $this->when(
                $this->resource->getAttribute('stock_quantity') !== null,
                $this->resource->getAttribute('stock_quantity'),
            ),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
