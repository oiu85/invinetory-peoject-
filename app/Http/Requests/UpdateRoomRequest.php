<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'width' => 'sometimes|numeric|min:1',
            'depth' => 'sometimes|numeric|min:1',
            'height' => 'sometimes|numeric|min:1',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'nullable|in:active,inactive,maintenance',
            'max_weight' => 'nullable|numeric|min:0',
        ];
    }
}
