<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'algorithm' => 'nullable|string|in:laff_maxrects,maxrects,skyline',
            'allow_rotation' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.dimensions.width' => 'required_without:items.*.product_id|numeric|min:0.01',
            'items.*.dimensions.depth' => 'required_without:items.*.product_id|numeric|min:0.01',
            'items.*.dimensions.height' => 'required_without:items.*.product_id|numeric|min:0.01',
            'options.max_layers' => 'nullable|integer|min:1',
            'options.prefer_bottom' => 'nullable|boolean',
            'options.minimize_height' => 'nullable|boolean',
        ];
    }
}
