<?php
/**
 * Feature tests for  POST /api/import-csv
 */

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/* --------------------------------------------------------------
|  helpers
|--------------------------------------------------------------*/
function csv(array $rows): UploadedFile
{
    $tmp = tmpfile();
    foreach ($rows as $row) fputcsv($tmp, $row);
    rewind($tmp);
    $content = stream_get_contents($tmp);
    fclose($tmp);

    return UploadedFile::fake()->createWithContent('sample.csv', $content);
}

function actingUser(): void
{
    Sanctum::actingAs(User::factory()->create());
}

/* --------------------------------------------------------------
|  1. Happy-path – import NEW products
|  - should insert new products
|  - should return count of inserted/updated
|--------------------------------------------------------------*/
it('imports NEW products and counts inserts', function () {
    actingUser();

    $file = csv([
        ['sku','name','description','stock','price'],
        ['SKU-1','A','desc',5,1.99],
        ['SKU-2','B','desc',3,9.99],
    ]);

    $res = $this->postJson('/api/import-csv', ['file' => $file]);
    $res->assertCreated()
        ->assertExactJson(['inserted' => 2, 'updated' => 0]);

    expect(Product::count())->toBe(2);
});

/* --------------------------------------------------------------
|  2. Happy-path – mix inserts + updates
|  - should insert new products
|  - should update existing products
|  - should return count of inserted/updated
|--------------------------------------------------------------*/
it('upserts existing SKUs and counts correctly', function () {
    Product::factory()->create(['sku' => 'SKU-1', 'name' => 'old']);
    actingUser();

    $file = csv([
        ['sku','name','description','stock','price'],
        ['SKU-1','new name','desc',10,2.99],
        ['SKU-3','C','desc',4,4.99],
    ]);

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertCreated()
         ->assertExactJson(['inserted' => 1, 'updated' => 1]);

    expect(Product::whereSku('SKU-1')->first()->name)->toBe('new name');
});

/* --------------------------------------------------------------
|  3. Reject non-CSV mime
|--------------------------------------------------------------*/
it('rejects non-CSV uploads', function () {
    actingUser();

    $file = UploadedFile::fake()->create('not-a-csv.pdf', 10, 'application/pdf');

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertStatus(422)
         ->assertJsonValidationErrors('file');
});

/* --------------------------------------------------------------
|  4. Reject file larger than 20 MB (20 * 1024 KB)
|--------------------------------------------------------------*/
it('rejects file bigger than 20MB', function () {
    actingUser();

    $file = UploadedFile::fake()->create('big.csv', 20_481);  // size in KB

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertStatus(422)
         ->assertJsonValidationErrors('file');
});

/* --------------------------------------------------------------
|  5. Wrong header -> semantic 422, no data inserted
|--------------------------------------------------------------*/
it('fails when header missing required columns & leaves DB clean', function () {
    actingUser();

    $file = csv([
        ['sku','title','oops'],  // wrong header
        ['SKU-10','Name','desc'],
    ]);

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertStatus(422)
         ->assertJsonPath('message', 'Missing required columns: name, description, stock, price');

    expect(Product::count())->toBe(0);    // rolled back
});

/* --------------------------------------------------------------
|  6. Runtime error row -> full rollback
|--------------------------------------------------------------*/
it('rolls back transaction when a bad row throws', function () {
    actingUser();

    $file = csv([
        ['sku','name','description','stock','price'],
        ['SKU-20','Valid','desc',5,3.33],
        ['SKU-21','Bad numeric','desc','not-a-number',9.99],
    ]);

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertStatus(422);

    expect(Product::count())->toBe(0);
});

/* --------------------------------------------------------------
|  7. Guest requests are rejected (middleware)
|--------------------------------------------------------------*/
it('denies guests', function () {
    $file = csv([
        ['sku','name','description','stock','price'],
        ['SKU-1','A','d',1,1.99],
    ]);

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertUnauthorized();
});

/* --------------------------------------------------------------
|  8. Duplicate SKU inside same CSV – last one wins
|--------------------------------------------------------------*/
it('handles duplicate rows within the file', function () {
    actingUser();

    $file = csv([
        ['sku','name','description','stock','price'],
        ['DUP','First','d',1,1.00],
        ['DUP','Second','d',9,9.99],
    ]);

    $this->postJson('/api/import-csv', ['file' => $file])
         ->assertCreated()
         ->assertExactJson(['inserted' => 1, 'updated' => 0]);

    expect(Product::whereSku('DUP')->value('name'))->toBe('Second')
        ->and(Product::whereSku('DUP')->value('stock'))->toBe(9);
});
