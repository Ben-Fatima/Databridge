<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCsvRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SplFileObject;

class CsvImportController extends Controller
{
    /**
     * Handle POST /api/import-csv
     * * @param ImportCsvRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ImportCsvRequest $request)
    {
        try {
            [$inserted, $updated] = DB::transaction(function () use ($request) {
                return $this->import($request->file('file')->getRealPath());
            });

            return response()->json(compact('inserted', 'updated'), 201);

        } catch (\Throwable $e) {
            Log::error('CSV import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

           return response()->json([
               'message' => $e instanceof RuntimeException
                   ? $e->getMessage()
                    : 'Import failed. Please check your CSV and try again.',
           ], 422);
        }
    }

    /* -----------------------------------------------------------------
    * Import CSV file
    * - reads the file, validates header, maps rows
    * - inserts new products, updates existing ones
    * - returns count of inserted/updated products
    *
    * @param string $path Path to the CSV file
    * @return array<int, int> [inserted, updated]
    * @throws RuntimeException if header is invalid or numeric fields are invalid
    */
    private function import(string $path): array
    {
        [$header, $csv] = $this->openCsv($path);

        $missing = array_diff(['sku','name','description','stock','price'], $header);
        if ($missing) {
            throw new RuntimeException(
                'Missing required columns: ' . implode(', ', $missing)
            );
        }

        $batch    = [];
        $inserted = $updated = 0;

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();

            if ($row === false || $this->isRowEmpty($row)) {
                continue;
            }

            $batch[] = $this->mapRow($header, $row);

            if (\count($batch) === 500) {
                [$ins, $upd] = $this->upsertBatch($batch);
                $inserted   += $ins;
                $updated    += $upd;
                $batch       = [];
            }
        }

        [$ins, $upd] = $this->upsertBatch($batch);

        return [$inserted + $ins, $updated + $upd];
    }

    /* -----------------------------------------------------------------
     |  Helpers
     |-----------------------------------------------------------------*/

    private function openCsv(string $path): array
    {
        $csv = new SplFileObject($path);
        $csv->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY
        );

        $header = array_map('trim', $csv->fgetcsv());

        return [$header, $csv];
    }

    private function isRowEmpty(?array $row): bool
    {
        return $row === null || $row === [] || $row === [null];
    }

    private function mapRow(array $header, array $row): array
    {
        $r = array_combine($header, $row);

        if (! is_numeric($r['stock'] ?? null) || ! is_numeric($r['price'] ?? null)) {
            throw new RuntimeException('Numeric fields invalid');
        }

        $r['stock'] = (int)   $r['stock'];
        $r['price'] = (float) $r['price'];

        return $r;
    }

    private function upsertBatch(array $batch): array
    {
        if ($batch === []) return [0, 0];

        $existing = Product::whereIn('sku', array_column($batch, 'sku'))
                    ->pluck('sku')
                    ->flip();            // now $existing->has($sku)

        $seen     = [];                         // SKUs we have counted *in this batch*
        $inserted = $updated = 0;

        foreach ($batch as $row) {
            $sku = $row['sku'];

            if (isset($seen[$sku])) {
                continue;
            }

            $seen[$sku] = true;

            $existing->has($sku) ? $updated++ : $inserted++;
        }

        Product::upsert(
            $batch,
            ['sku'],
            ['name', 'description', 'stock', 'price']
        );

    return [$inserted, $updated];
    }
}
