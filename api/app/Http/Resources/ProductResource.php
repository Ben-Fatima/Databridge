<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use App\Models\Product;


class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
        'id'          => $this->id,
        'sku'         => $this->sku,
        'name'        => $this->name,
        'description' => $this->description,
        'stock'       => $this->stock,
        'price'       => $this->price,
    ];
    }

    /**
     * Get the resource's representation as an array.
     *
     * @return array<string, mixed>
     */
    public function index()
    {
        return ProductResource::collection(
            Product::latest()->paginate(20)
        );
    }
}
