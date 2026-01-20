<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'width' => 'required|numeric|min:1',
            'depth' => 'required|numeric|min:1',
            'height' => 'required|numeric|min:1',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'nullable|in:active,inactive,maintenance',
            'max_weight' => 'nullable|numeric|min:0',
        ];
    }
}
