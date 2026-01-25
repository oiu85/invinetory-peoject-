<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class StockListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'sort' => 'nullable|string|in:name,quantity,price',
            'order' => 'nullable|string|in:asc,desc',
            'low_stock_only' => 'nullable|boolean',
            'low_stock_threshold' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Selected category does not exist.',
            'sort.in' => 'Sort field must be one of: name, quantity, price.',
            'order.in' => 'Order must be either asc or desc.',
            'per_page.max' => 'Per page cannot exceed 100 items.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'sort' => $this->get('sort', 'name'),
            'order' => $this->get('order', 'asc'),
            'per_page' => $this->get('per_page', 15),
        ]);
    }
}
