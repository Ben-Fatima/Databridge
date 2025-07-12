<?php

use App\Models\User;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
|  Test setup – every test starts with a fresh, logged-in user
|--------------------------------------------------------------------------
*/
beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

/*
|--------------------------------------------------------------------------
|  1.  Happy-path CRUD
|--------------------------------------------------------------------------
*/
it('creates a product', function () {
    $payload = Product::factory()->make()->toArray();

    $this->postJson('/api/products', $payload)
         ->assertCreated()
         ->assertJsonPath('sku', $payload['sku']);

    expect(Product::count())->toBe(1);
});

it('lists products', function () {
    Product::factory()->count(3)->create();

    $response = $this->getJson('/api/products')->assertOk();

      expect($response->json('data'))->toHaveCount(3);
});

it('shows a single product', function () {
    $product = Product::factory()->create();

    $this->getJson("/api/products/{$product->id}")
         ->assertOk()
         ->assertJsonPath('id', $product->id);
});

it('updates a product', function () {
    $product = Product::factory()->create();

    $this->putJson("/api/products/{$product->id}", ['name' => 'Updated'])
         ->assertOk()
         ->assertJsonPath('name', 'Updated');
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    $this->deleteJson("/api/products/{$product->id}")
         ->assertNoContent();

    expect(Product::find($product->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
|  2.  Validation & edge-cases
|--------------------------------------------------------------------------
*/
it('fails to create when required fields are missing', function () {
    $this->postJson('/api/products', [])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['sku', 'name']);
});

it('rejects duplicate sku on create', function () {
    $existing = Product::factory()->create();

    $payload  = Product::factory()->make(['sku' => $existing->sku])->toArray();

    $this->postJson('/api/products', $payload)
         ->assertStatus(422)
         ->assertJsonValidationErrors('sku');
});

it('rejects duplicate sku on update', function () {
    $a = Product::factory()->create(['sku' => 'SKU-A']);
    $b = Product::factory()->create(['sku' => 'SKU-B']);

    $this->putJson("/api/products/{$b->id}", ['sku' => 'SKU-A', 'name' => $b->name])
         ->assertStatus(422)
         ->assertJsonValidationErrors('sku');
});

it('returns 404 for a missing product', function () {
    $this->getJson('/api/products/999999')
         ->assertNotFound();
});

/* ──────────────────────────────────────────────────────────────
|  Existing “lists products” test – now expects 'data' node
└──────────────────────────────────────────────────────────────*/
it('lists products (paginated JSON)', function () {
    Product::factory()->count(3)->create();

    $this->getJson('/api/products')
         ->assertOk()
         ->assertJsonCount(3, 'data');
});

/* ──────────────────────────────────────────────────────────────
|   Shows max 20 items on first page
└──────────────────────────────────────────────────────────────*/
it('paginates 20 per page', function () {
    Product::factory()->count(25)->create();

    $this->getJson('/api/products')
         ->assertOk()
         ->assertJsonCount(20, 'data')
         ->assertJsonPath('meta.per_page', 20)
         ->assertJsonPath('meta.total', 25);
});

/* ──────────────────────────────────────────────────────────────
|   Returns remaining items on page 2
└──────────────────────────────────────────────────────────────*/
it('returns the remaining items on page 2', function () {
    Product::factory()->count(25)->create();

    $this->getJson('/api/products?page=2')
         ->assertOk()
         ->assertJsonCount(5, 'data')
         ->assertJsonPath('meta.current_page', 2);
});
