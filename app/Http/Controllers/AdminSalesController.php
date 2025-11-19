<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class AdminSalesController extends Controller
{
    public function index()
    {
        $sales = Sale::with(['driver', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($sales);
    }
}

