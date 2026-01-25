<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta charset="utf-8">
    <title>Driver Settlement - {{ $driver->name }}</title>
    <style>
        @charset "UTF-8";
        * {
            font-family: 'DejaVu Sans', Arial, sans-serif;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            direction: rtl;
            unicode-bidi: embed;
        }
        .arabic-text {
            direction: rtl;
            unicode-bidi: bidi-override;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            text-align: right;
        }
        .mixed-content {
            unicode-bidi: embed;
        }
        /* Ensure Arabic text is properly rendered */
        * {
            -webkit-font-feature-settings: "liga" off;
            font-feature-settings: "liga" off;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-box {
            flex: 1;
        }
        .info-box h3 {
            margin-top: 0;
            color: #666;
            font-size: 14px;
        }
        .summary-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .profit-positive {
            color: #10B981;
            font-weight: bold;
        }
        .profit-negative {
            color: #EF4444;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><span class="arabic-text">تسوية حساب السائق</span> / Driver Settlement</h1>
        <h2>{{ $driver->name }}</h2>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>معلومات السائق / Driver Information</h3>
            <p><strong>الاسم / Name:</strong> {{ $driver->name }}</p>
            <p><strong>البريد الإلكتروني / Email:</strong> {{ $driver->email }}</p>
        </div>
        <div class="info-box">
            <h3>الفترة / Period</h3>
            <p><strong>من / From:</strong> {{ $period['start_date'] }}</p>
            <p><strong>إلى / To:</strong> {{ $period['end_date'] }}</p>
            <p><strong>النوع / Type:</strong> {{ ucfirst($period['type']) }}</p>
        </div>
    </div>

    <div class="summary-box">
        <h3 style="margin-top: 0;">ملخص الحساب / Account Summary</h3>
        <div class="summary-row">
            <span>إجمالي المبيعات / Total Sales:</span>
            <span><strong>{{ $summary['total_sales'] }}</strong></span>
        </div>
        <div class="summary-row">
            <span>إجمالي الإيرادات / Total Revenue:</span>
            <span><strong>${{ number_format($summary['total_revenue'], 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span>إجمالي التكلفة / Total Cost:</span>
            <span><strong>${{ number_format($summary['total_cost'], 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span>قيمة المخزون الحالي / Current Stock Value:</span>
            <span><strong>${{ number_format($summary['current_stock_value'], 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span>إجمالي الربح / Total Profit:</span>
            <span class="profit-positive"><strong>${{ number_format($summary['total_profit'], 2) }}</strong></span>
        </div>
    </div>

    <h3>تفاصيل الأرباح / Profit Details</h3>
    <table>
        <thead>
            <tr>
                <th>رقم الفاتورة / Invoice</th>
                <th>العميل / Customer</th>
                <th>المنتج / Product</th>
                <th>الكمية / Qty</th>
                <th>سعر التكلفة / Cost Price</th>
                <th>سعر البيع / Selling Price</th>
                <th>الربح / Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($profit_details as $detail)
            <tr>
                <td>{{ $detail['invoice_number'] }}</td>
                <td>{{ $detail['customer_name'] }}</td>
                <td>{{ $detail['product_name'] }}</td>
                <td>{{ $detail['quantity'] }}</td>
                <td>${{ number_format($detail['cost_price'], 2) }}</td>
                <td>${{ number_format($detail['selling_price'], 2) }}</td>
                <td class="{{ $detail['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                    ${{ number_format($detail['total_profit'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>المبيعات / Sales</h3>
    <table>
        <thead>
            <tr>
                <th>رقم الفاتورة / Invoice</th>
                <th>العميل / Customer</th>
                <th>التاريخ / Date</th>
                <th>المبلغ الإجمالي / Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            <tr>
                <td>{{ $sale['invoice_number'] }}</td>
                <td>{{ $sale['customer_name'] }}</td>
                <td>{{ \Carbon\Carbon::parse($sale['created_at'])->format('Y-m-d H:i') }}</td>
                <td>${{ number_format($sale['total_amount'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>شكراً لعملك! / Thank you for your business!</p>
        <p>تم إنشاء التسوية في / Settlement generated on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
