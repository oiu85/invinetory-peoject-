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
            'algorithm' => 'nullable|string|in:laff_maxrects,maxrects,skyline,compartment,compartment_grid',
            'allow_rotation' => 'nullable|boolean',
            // No max limit - quantities validated against warehouse stock
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            // Dimensions are optional - backend will use product dimensions from database
            'items.*.dimensions' => 'nullable|array',
            'items.*.dimensions.width' => 'nullable|numeric|min:0.01',
            'items.*.dimensions.depth' => 'nullable|numeric|min:0.01',
            'items.*.dimensions.height' => 'nullable|numeric|min:0.01',
            'options' => 'nullable|array',
            'options.max_layers' => 'nullable|integer|min:1',
            'options.prefer_bottom' => 'nullable|boolean',
            'options.minimize_height' => 'nullable|boolean',
            'options.column_max_height' => 'nullable|numeric|min:0',
            'options.grid' => 'nullable|array',
            'options.grid.columns' => 'nullable|integer|min:1|max:100',
            'options.grid.rows' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Prepare the data for validation.
     * Convert string booleans to actual booleans.
     */
    protected function prepareForValidation(): void
    {
        // Convert string booleans to actual booleans for options
        if ($this->has('options')) {
            $options = $this->input('options', []);
            
            if (isset($options['prefer_bottom'])) {
                $options['prefer_bottom'] = filter_var($options['prefer_bottom'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            
            if (isset($options['minimize_height'])) {
                $options['minimize_height'] = filter_var($options['minimize_height'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            
            $this->merge(['options' => $options]);
        }
        
        // Convert allow_rotation if present
        if ($this->has('allow_rotation')) {
            $this->merge([
                'allow_rotation' => filter_var($this->input('allow_rotation'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required to generate a layout.',
            'items.min' => 'At least one item is required to generate a layout.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.exists' => 'One or more selected products do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.integer' => 'Quantity must be a whole number.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
