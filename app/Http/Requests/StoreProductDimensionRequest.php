<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'width' => 'required|numeric|min:0.01',
            'depth' => 'required|numeric|min:0.01',
            'height' => 'required|numeric|min:0.01',
            'weight' => 'nullable|numeric|min:0',
            'rotatable' => 'nullable|boolean',
            'fragile' => 'nullable|boolean',
        ];
    }
}
