<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\Validation\RoomValidationService;
use App\Services\Validation\StockValidationService;
use App\Models\Room;
use App\Models\Product;
use App\Models\ProductDimension;

class GenerateLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $roomId = $this->route('id');
            if (!$roomId) {
                return;
            }

            $room = Room::find($roomId);
            if (!$room) {
                $validator->errors()->add('room', 'Room not found');
                return;
            }

            // Validate room dimensions
            $roomValidationService = app(RoomValidationService::class);
            $roomDimValidation = $roomValidationService->validateRoomDimensions($room);
            if (!$roomDimValidation['valid']) {
                foreach ($roomDimValidation['errors'] as $error) {
                    $validator->errors()->add('room', $error);
                }
            }

            // Validate products and stock
            $items = $this->input('items', []);
            if (empty($items)) {
                return;
            }

            // Prepare items with dimensions for validation
            $itemsWithDimensions = [];
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                if (!$productId) continue;

                $product = Product::find($productId);
                if (!$product) continue;

                $dimension = ProductDimension::where('product_id', $productId)->first();
                if (!$dimension) {
                    $validator->errors()->add("items.{$productId}", "Product #{$productId} does not have dimensions defined");
                    continue;
                }

                $itemsWithDimensions[] = [
                    'product_id' => $productId,
                    'quantity' => $item['quantity'] ?? 1,
                    'width' => $dimension->width,
                    'depth' => $dimension->depth,
                    'height' => $dimension->height,
                ];
            }

            // Validate products fit in room
            $productValidation = $roomValidationService->validateRoomForProducts($room, $itemsWithDimensions);
            if (!$productValidation['valid']) {
                foreach ($productValidation['errors'] as $error) {
                    $validator->errors()->add('items', $error);
                }
            }

            // Validate stock availability
            $stockValidationService = app(StockValidationService::class);
            $stockValidation = $stockValidationService->validateQuantitiesAgainstStock($items, $roomId);
            if (!$stockValidation['valid']) {
                foreach ($stockValidation['errors'] as $error) {
                    $validator->errors()->add('items', $error);
                }
            }
        });
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
            'room' => 'Room validation failed.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'Validation failed',
                'message' => 'The request data is invalid. Please check the errors.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
