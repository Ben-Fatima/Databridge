<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Response;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Product::all();
    }

    /**
     * Store a newly created resource in storage.
     * @param \App\Http\Requests\StoreProductRequest $request
     */
    public function store(StoreProductRequest $request)
    {
         return Product::create($request->validated());
    }

    /**
     * Display the specified resource.
     * @param \App\Models\Product $product
     * @return \App\Models\Product
     */
    public function show(Product $product)
    {
        return $product;
    }

    /**
     * Update the specified resource in storage.
     * @param \App\Http\Requests\UpdateProductRequest $request
     * @param \App\Models\Product $product
     * @return \App\Models\Product
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());
        return $product->refresh();
    }

    /**
     * Remove the specified resource from storage.
     * @param \App\Models\Product $product
     * @return array
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return Response::json(['message' => 'Product deleted successfully'], 204);
    }
}
