# Warehouse + Delivery Agent Sales System - Backend API

A simple Laravel backend API for managing warehouse inventory and driver sales operations.

## Features

- ✅ Authentication (Admin + Driver) using Laravel Sanctum
- ✅ Products & Categories CRUD
- ✅ Warehouse stock management
- ✅ Driver stock assignment
- ✅ Sales processing with invoice generation
- ✅ Admin dashboard statistics
- ✅ Simple PDF invoice generation (optional)

## Requirements

- PHP >= 8.2
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- XAMPP (for local MySQL via phpMyAdmin)

## Installation

### 1. Install Dependencies

```bash
composer install
```

### 2. Environment Configuration

The `.env` file is already configured for MySQL. Update the database credentials if needed:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=root
DB_PASSWORD=
```

**Important:** Create the database `inventory` in phpMyAdmin before running migrations.

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Seed Database

```bash
php artisan db:seed
```

This will create:
- Admin user: `admin@inventory.com` / `password`
- Driver user: `driver@inventory.com` / `password`
- Sample categories, products, and stock data

### 6. Start Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

## API Endpoints

### Authentication

- `POST /api/login` - Login (returns token)
- `POST /api/logout` - Logout (requires auth)
- `GET /api/me` - Get current user (requires auth)

### Categories (Admin only)

- `GET /api/categories` - List all categories
- `POST /api/categories` - Create category

### Products (Admin only)

- `GET /api/products` - List all products
- `POST /api/products` - Create product
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product

### Warehouse Stock (Admin only)

- `GET /api/warehouse-stock` - Get all warehouse stock
- `POST /api/warehouse-stock/update` - Update warehouse stock

### Driver Stock (Admin only)

- `GET /api/drivers/{id}/stock` - Get driver's stock
- `POST /api/assign-stock` - Assign stock from warehouse to driver

### Sales (Driver only)

- `POST /api/sales` - Create a sale
- `GET /api/sales/{id}` - Get sale details

### Reports (Admin only)

- `GET /api/admin/stats` - Get dashboard statistics

## Sample API Requests

### Login (Admin)

```bash
POST http://localhost:8000/api/login
Content-Type: application/json

{
    "email": "admin@inventory.com",
    "password": "password"
}
```

Response:
```json
{
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@inventory.com",
        "type": "admin"
    },
    "token": "1|xxxxxxxxxxxxx"
}
```

### Create Product (Admin)

```bash
POST http://localhost:8000/api/products
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "New Product",
    "price": 99.99,
    "category_id": 1,
    "description": "Product description",
    "image": "https://example.com/image.jpg"
}
```

### Assign Stock to Driver (Admin)

```bash
POST http://localhost:8000/api/assign-stock
Authorization: Bearer {token}
Content-Type: application/json

{
    "driver_id": 2,
    "product_id": 1,
    "quantity": 10
}
```

### Create Sale (Driver)

```bash
POST http://localhost:8000/api/sales
Authorization: Bearer {token}
Content-Type: application/json

{
    "customer_name": "John Doe",
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 2,
            "quantity": 1
        }
    ]
}
```

Response:
```json
{
    "sale_id": 1,
    "invoice_number": "INV-XXXXXXXX-20241119",
    "customer_name": "John Doe",
    "total_amount": 2599.97,
    "items": [
        {
            "product_name": "Laptop",
            "quantity": 2,
            "price": 999.99,
            "subtotal": 1999.98
        },
        {
            "product_name": "Smartphone",
            "quantity": 1,
            "price": 599.99,
            "subtotal": 599.99
        }
    ],
    "created_at": "2024-11-19T20:00:00.000000Z"
}
```

### Get Admin Stats

```bash
GET http://localhost:8000/api/admin/stats
Authorization: Bearer {token}
```

Response:
```json
{
    "total_products": 6,
    "total_drivers": 1,
    "total_sales": 5,
    "total_revenue": 12500.50,
    "low_stock_products": [
        {
            "product_id": 3,
            "product_name": "Bread",
            "quantity": 8
        }
    ]
}
```

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your_token_here}
```

## Database Structure

- `users` - Admin and driver accounts
- `categories` - Product categories
- `products` - Product catalog
- `warehouse_stock` - Warehouse inventory
- `driver_stock` - Driver car inventory
- `sales` - Sales records
- `sale_items` - Sale line items

## Notes

- This is a backend-only API project
- No frontend is included
- Simple token-based authentication
- No complex permissions or roles
- Single warehouse system
- Basic invoice generation (JSON format)

## License

MIT License
