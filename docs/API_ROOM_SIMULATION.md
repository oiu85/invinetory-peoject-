# Room Simulation API Documentation

## Overview

The Room Simulation API provides endpoints for managing warehouse rooms, generating optimal layouts using LAFF + MaxRects algorithm, and automatic storage suggestions.

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

All room simulation endpoints require admin privileges.

## Endpoints

### Room Management

#### List All Rooms
```
GET /api/rooms
```

#### Create Room
```
POST /api/rooms
```

Request Body:
```json
{
  "name": "Main Storage Room",
  "description": "Primary storage room",
  "width": 1000,
  "depth": 800,
  "height": 300,
  "warehouse_id": 1,
  "status": "active",
  "max_weight": 10000
}
```

#### Get Room Details
```
GET /api/rooms/{id}
```

#### Update Room
```
PUT /api/rooms/{id}
```

#### Delete Room
```
DELETE /api/rooms/{id}
```

#### Get Room Statistics
```
GET /api/rooms/{id}/stats
```

### Layout Generation

#### Generate Layout
```
POST /api/rooms/{id}/generate-layout
```

Request Body:
```json
{
  "algorithm": "laff_maxrects",
  "allow_rotation": true,
  "items": [
    {
      "product_id": 1,
      "quantity": 5,
      "dimensions": {
        "width": 50,
        "depth": 50,
        "height": 30
      }
    }
  ],
  "options": {
    "max_layers": null,
    "prefer_bottom": true,
    "minimize_height": false
  }
}
```

Response:
```json
{
  "id": 1,
  "room_id": 1,
  "algorithm_used": "laff_maxrects",
  "utilization_percentage": 85.5,
  "total_items_placed": 45,
  "total_items_attempted": 50,
  "layout_data": {...}
}
```

### Storage Suggestions

#### Get Storage Suggestions
```
GET /api/warehouse-stock/{product_id}/suggest-storage
```

#### Apply Storage Suggestion
```
POST /api/warehouse-stock/apply-suggestion
```

#### Get Pending Suggestions
```
GET /api/warehouse-stock/pending-suggestions
```

## Storage Suggestion Response Format

When updating warehouse stock, if quantity is added, storage suggestions are automatically included:

```json
{
  "success": true,
  "message": "Stock updated successfully. Storage suggestions available.",
  "stock_updated": {
    "product_id": 1,
    "product_name": "Product A",
    "new_quantity": 50,
    "previous_quantity": 30,
    "quantity_added": 20
  },
  "storage_suggestions": {
    "recommended_room": {
      "room_id": 1,
      "room_name": "Main Warehouse Room A",
      "priority": "high"
    },
    "placement_options": [
      {
        "room_id": 1,
        "x_position": 0,
        "y_position": 0,
        "z_position": 90,
        "stack_on_existing": true,
        "can_fit_quantity": 7
      }
    ],
    "recommendations": [
      "Recommended: Stack 7 items on existing stack at position (0, 0) starting from Z=90"
    ]
  }
}
```
