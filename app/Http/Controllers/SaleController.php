<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\DriverStock;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $driver = $request->user();
        $totalAmount = 0;
        $saleItems = [];

        // Validate driver stock and calculate total
        foreach ($validated['items'] as $item) {
            $driverStock = DriverStock::where('driver_id', $driver->id)
                ->where('product_id', $item['product_id'])
                ->first();

            if (!$driverStock || $driverStock->quantity < $item['quantity']) {
                return response()->json([
                    'message' => "Insufficient stock for product ID: {$item['product_id']}"
                ], 400);
            }

            $product = $driverStock->product;
            $itemTotal = $product->price * $item['quantity'];
            $totalAmount += $itemTotal;

            $saleItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'total' => $itemTotal,
            ];
        }

        // Create sale
        $invoiceNumber = 'INV-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd');
        
        $sale = Sale::create([
            'driver_id' => $driver->id,
            'customer_name' => $validated['customer_name'],
            'total_amount' => $totalAmount,
            'invoice_number' => $invoiceNumber,
        ]);

        // Create sale items and decrease driver stock
        foreach ($saleItems as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);

            $driverStock = DriverStock::where('driver_id', $driver->id)
                ->where('product_id', $item['product_id'])
                ->first();
            
            $driverStock->decrement('quantity', $item['quantity']);
        }

        $sale->load(['items.product', 'driver']);

        return response()->json([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'customer_name' => $sale->customer_name,
            'total_amount' => $sale->total_amount,
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->quantity * $item->price,
                ];
            }),
            'created_at' => $sale->created_at,
        ], 201);
    }

    public function show(string $id)
    {
        $sale = Sale::with(['items.product', 'driver'])->findOrFail($id);

        return response()->json([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'customer_name' => $sale->customer_name,
            'total_amount' => $sale->total_amount,
            'driver_name' => $sale->driver->name,
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->quantity * $item->price,
                ];
            }),
            'created_at' => $sale->created_at,
        ]);
    }

    public function index(Request $request)
    {
        $driver = $request->user();

        $sales = Sale::where('driver_id', $driver->id)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($sales);
    }

    public function invoice(Request $request, string $id)
    {
        $sale = Sale::with(['items.product', 'driver'])->findOrFail($id);
        
        // Check if user has access (admin or the driver who made the sale)
        $user = $request->user();
        if ($user->type !== 'admin' && $sale->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $html = view('invoice', [
            'sale' => $sale,
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        
        // Return PDF with CORS headers
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-'.$sale->invoice_number.'.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }
}
