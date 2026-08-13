<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductServiceResource;
use App\Models\ProductServiceItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductServiceApiController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['product', 'service'])],
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $workspace = $request->attributes->get('workspace');

        $items = ProductServiceItem::query()
            ->forTenant($workspace->organization_id, $workspace->id)
            ->with(['category', 'taxes'])
            ->withSum('stocks as stock_quantity', 'quantity')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(array_key_exists('active', $filters), fn ($query) => $query->where('is_active', $filters['active']))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ProductServiceResource::collection($items);
    }

    public function show(Request $request, int $item)
    {
        $workspace = $request->attributes->get('workspace');
        $product = ProductServiceItem::query()
            ->forTenant($workspace->organization_id, $workspace->id)
            ->with(['category', 'taxes'])
            ->withSum('stocks as stock_quantity', 'quantity')
            ->findOrFail($item);

        return new ProductServiceResource($product);
    }
}
