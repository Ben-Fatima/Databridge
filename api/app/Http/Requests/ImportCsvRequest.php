<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Assuming all users are authorized to import CSV files
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:20480',        // 20 MB
            ],
        ];
    }
}
