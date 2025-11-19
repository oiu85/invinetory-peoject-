<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\DriverStock;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class FakerDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_SA'); // Arabic locale

        $this->command->info('🌱 بدء ملء قاعدة البيانات بالبيانات الوهمية...');

        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@inventory.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'type' => 'admin',
            ]
        );
        $this->command->info('✅ تم إنشاء المستخدم الإداري');

        // Create Categories for Plastic Bags Warehouse
        $categoryNames = [
            ['name' => 'أكياس التسوق', 'description' => 'أكياس التسوق البلاستيكية بأنواعها المختلفة'],
            ['name' => 'أكياس القمامة', 'description' => 'أكياس القمامة والمخلفات بجميع الأحجام'],
            ['name' => 'أكياس الطعام', 'description' => 'أكياس حفظ الطعام والتغليف'],
            ['name' => 'أكياس السحاب', 'description' => 'أكياس السحاب (Ziploc) بجميع الأحجام'],
            ['name' => 'أكياس التجميد', 'description' => 'أكياس حفظ الأطعمة في الفريزر'],
            ['name' => 'أكياس النفايات الطبية', 'description' => 'أكياس النفايات الطبية والخطرة'],
            ['name' => 'أكياس التغليف', 'description' => 'أكياس تغليف المنتجات والهدايا'],
            ['name' => 'أكياس الزراعة', 'description' => 'أكياس الزراعة والشتلات'],
        ];

        $categories = [];
        foreach ($categoryNames as $catData) {
            $category = Category::create($catData);
            $categories[] = $category;
        }
        $this->command->info('✅ تم إنشاء ' . count($categories) . ' فئة');

        // Unsplash image URLs for plastic bags
        $unsplashImages = [
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
            'https://images.unsplash.com/photo-1586075010923-2dd45780fb0d?w=800',
            'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=800',
            'https://images.unsplash.com/photo-1606761568499-6d2451b23c66?w=800',
            'https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=800',
            'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=800',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
            'https://images.unsplash.com/photo-1586075010923-2dd45780fb0d?w=800',
            'https://images.unsplash.com/photo-1606761568499-6d2451b23c66?w=800',
            'https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=800',
        ];

        // Arabic product names for plastic bags warehouse
        $plasticBagProducts = [
            // Shopping Bags
            ['name' => 'كيس تسوق كبير', 'price' => 2.50, 'category' => 0, 'description' => 'كيس تسوق بلاستيكي كبير الحجم، مناسب للاستخدام المتكرر'],
            ['name' => 'كيس تسوق متوسط', 'price' => 1.75, 'category' => 0, 'description' => 'كيس تسوق بلاستيكي متوسط الحجم'],
            ['name' => 'كيس تسوق صغير', 'price' => 1.00, 'category' => 0, 'description' => 'كيس تسوق بلاستيكي صغير الحجم'],
            ['name' => 'كيس تسوق قوي', 'price' => 3.00, 'category' => 0, 'description' => 'كيس تسوق بلاستيكي عالي الجودة ومقاوم للتمزق'],
            ['name' => 'كيس تسوق قابل لإعادة الاستخدام', 'price' => 4.50, 'category' => 0, 'description' => 'كيس تسوق بلاستيكي متين قابل لإعادة الاستخدام'],
            
            // Garbage Bags
            ['name' => 'كيس قمامة 10 لتر', 'price' => 3.50, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي سعة 10 لتر'],
            ['name' => 'كيس قمامة 20 لتر', 'price' => 5.00, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي سعة 20 لتر'],
            ['name' => 'كيس قمامة 30 لتر', 'price' => 6.50, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي سعة 30 لتر'],
            ['name' => 'كيس قمامة 50 لتر', 'price' => 8.00, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي سعة 50 لتر'],
            ['name' => 'كيس قمامة 100 لتر', 'price' => 12.00, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي سعة 100 لتر'],
            ['name' => 'كيس قمامة معزز', 'price' => 7.00, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي معزز ومقاوم للثقب'],
            ['name' => 'كيس قمامة معطّر', 'price' => 4.50, 'category' => 1, 'description' => 'كيس قمامة بلاستيكي معطّر برائحة عطرة'],
            
            // Food Bags
            ['name' => 'كيس حفظ طعام صغير', 'price' => 1.50, 'category' => 2, 'description' => 'كيس بلاستيكي صغير لحفظ الطعام'],
            ['name' => 'كيس حفظ طعام متوسط', 'price' => 2.00, 'category' => 2, 'description' => 'كيس بلاستيكي متوسط لحفظ الطعام'],
            ['name' => 'كيس حفظ طعام كبير', 'price' => 2.75, 'category' => 2, 'description' => 'كيس بلاستيكي كبير لحفظ الطعام'],
            ['name' => 'كيس تغليف ساندويتش', 'price' => 1.25, 'category' => 2, 'description' => 'كيس بلاستيكي لتغليف الساندويتشات'],
            ['name' => 'كيس حفظ خضار', 'price' => 2.25, 'category' => 2, 'description' => 'كيس بلاستيكي لحفظ الخضروات'],
            
            // Ziploc Bags
            ['name' => 'كيس سحاب صغير', 'price' => 3.00, 'category' => 3, 'description' => 'كيس بلاستيكي بسحاب صغير الحجم'],
            ['name' => 'كيس سحاب متوسط', 'price' => 4.50, 'category' => 3, 'description' => 'كيس بلاستيكي بسحاب متوسط الحجم'],
            ['name' => 'كيس سحاب كبير', 'price' => 6.00, 'category' => 3, 'description' => 'كيس بلاستيكي بسحاب كبير الحجم'],
            ['name' => 'كيس سحاب عائلي', 'price' => 8.00, 'category' => 3, 'description' => 'كيس بلاستيكي بسحاب عائلي الحجم'],
            ['name' => 'كيس سحاب شفاف', 'price' => 4.00, 'category' => 3, 'description' => 'كيس بلاستيكي بسحاب شفاف عالي الجودة'],
            
            // Freezer Bags
            ['name' => 'كيس تجميد صغير', 'price' => 3.50, 'category' => 4, 'description' => 'كيس بلاستيكي خاص بالتجميد صغير الحجم'],
            ['name' => 'كيس تجميد متوسط', 'price' => 5.00, 'category' => 4, 'description' => 'كيس بلاستيكي خاص بالتجميد متوسط الحجم'],
            ['name' => 'كيس تجميد كبير', 'price' => 7.00, 'category' => 4, 'description' => 'كيس بلاستيكي خاص بالتجميد كبير الحجم'],
            ['name' => 'كيس تجميد مقاوم للصقيع', 'price' => 8.50, 'category' => 4, 'description' => 'كيس بلاستيكي مقاوم للصقيع عالي الجودة'],
            
            // Medical Waste Bags
            ['name' => 'كيس نفايات طبية صغير', 'price' => 5.50, 'category' => 5, 'description' => 'كيس نفايات طبية صغير الحجم'],
            ['name' => 'كيس نفايات طبية متوسط', 'price' => 7.50, 'category' => 5, 'description' => 'كيس نفايات طبية متوسط الحجم'],
            ['name' => 'كيس نفايات طبية كبير', 'price' => 10.00, 'category' => 5, 'description' => 'كيس نفايات طبية كبير الحجم'],
            ['name' => 'كيس نفايات خطرة', 'price' => 12.00, 'category' => 5, 'description' => 'كيس نفايات خطرة معزز'],
            
            // Packaging Bags
            ['name' => 'كيس تغليف شفاف', 'price' => 2.00, 'category' => 6, 'description' => 'كيس تغليف بلاستيكي شفاف'],
            ['name' => 'كيس تغليف ملون', 'price' => 2.50, 'category' => 6, 'description' => 'كيس تغليف بلاستيكي ملون'],
            ['name' => 'كيس تغليف هدايا', 'price' => 3.50, 'category' => 6, 'description' => 'كيس تغليف بلاستيكي للهدايا'],
            ['name' => 'كيس تغليف منتجات', 'price' => 2.25, 'category' => 6, 'description' => 'كيس تغليف بلاستيكي للمنتجات'],
            
            // Agriculture Bags
            ['name' => 'كيس زراعة صغير', 'price' => 1.75, 'category' => 7, 'description' => 'كيس بلاستيكي للزراعة صغير الحجم'],
            ['name' => 'كيس زراعة متوسط', 'price' => 2.50, 'category' => 7, 'description' => 'كيس بلاستيكي للزراعة متوسط الحجم'],
            ['name' => 'كيس زراعة كبير', 'price' => 3.50, 'category' => 7, 'description' => 'كيس بلاستيكي للزراعة كبير الحجم'],
            ['name' => 'كيس شتلات', 'price' => 2.00, 'category' => 7, 'description' => 'كيس بلاستيكي للشتلات'],
        ];

        // Create Products
        $products = [];
        $imageIndex = 0;
        
        foreach ($plasticBagProducts as $productData) {
            $category = $categories[$productData['category']];
            
            $product = Product::create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'category_id' => $category->id,
                'description' => $productData['description'],
                'image' => $unsplashImages[$imageIndex % count($unsplashImages)],
            ]);
            
            $products[] = $product;
            $imageIndex++;
            
            // Create warehouse stock (random quantity between 100-1000 for bags)
            WarehouseStock::create([
                'product_id' => $product->id,
                'quantity' => $faker->numberBetween(100, 1000),
            ]);
        }
        $this->command->info('✅ تم إنشاء ' . count($products) . ' منتج مع مخزون المستودع');

        // Create Drivers with Arabic names
        $arabicNames = [
            'أحمد محمد', 'محمد علي', 'خالد حسن', 'عبدالله سعيد', 'يوسف إبراهيم',
            'عمر أحمد', 'حسام الدين', 'محمود خليل', 'طارق فؤاد', 'سامي راشد'
        ];
        
        $drivers = [];
        for ($i = 0; $i < 10; $i++) {
            $driver = User::create([
                'name' => $arabicNames[$i] ?? $faker->name(),
                'email' => 'driver' . ($i + 1) . '@inventory.com',
                'password' => Hash::make('password'),
                'type' => 'driver',
            ]);
            $drivers[] = $driver;
        }
        $this->command->info('✅ تم إنشاء 10 سائقين');

        // Assign stock to drivers
        foreach ($drivers as $driver) {
            $numProducts = $faker->numberBetween(8, 20);
            $selectedProducts = $faker->randomElements($products, min($numProducts, count($products)));
            
            foreach ($selectedProducts as $product) {
                $quantity = $faker->numberBetween(10, 100); // More quantity for bags
                
                // Get warehouse stock
                $warehouseStock = WarehouseStock::where('product_id', $product->id)->first();
                
                if ($warehouseStock && $warehouseStock->quantity >= $quantity) {
                    // Decrease warehouse stock
                    $warehouseStock->decrement('quantity', $quantity);
                    
                    // Create driver stock
                    DriverStock::create([
                        'driver_id' => $driver->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);
                }
            }
        }
        $this->command->info('✅ تم توزيع المخزون على السائقين');

        // Arabic customer names
        $arabicCustomerNames = [
            'عبدالرحمن أحمد', 'فاطمة محمد', 'سارة علي', 'نور الدين', 'ليلى حسن',
            'مريم خالد', 'علي عبدالله', 'حسن يوسف', 'زينب عمر', 'أسماء محمود',
            'خديجة طارق', 'عائشة سامي', 'محمد راشد', 'أحمد فؤاد', 'عبدالله خليل',
            'يوسف حسام', 'عمر محمود', 'طارق أحمد', 'سامي محمد', 'راشد علي',
            'فؤاد خالد', 'خليل حسن', 'حسام سعيد', 'محمود إبراهيم', 'أحمد يوسف',
            'محمد عمر', 'علي طارق', 'خالد سامي', 'حسن راشد', 'سعيد فؤاد'
        ];

        // Create Sales (30 sales)
        for ($i = 0; $i < 30; $i++) {
            $driver = $faker->randomElement($drivers);
            $customerName = $faker->randomElement($arabicCustomerNames);
            
            // Get driver's available stock
            $driverStocks = DriverStock::where('driver_id', $driver->id)
                ->where('quantity', '>', 0)
                ->with('product')
                ->get();
            
            if ($driverStocks->count() > 0) {
                $numItems = $faker->numberBetween(1, min(5, $driverStocks->count()));
                $selectedStocks = $faker->randomElements($driverStocks->toArray(), $numItems);
                
                $totalAmount = 0;
                $saleItems = [];
                
                foreach ($selectedStocks as $stock) {
                    $quantity = $faker->numberBetween(1, min($stock['quantity'], 20)); // More quantity for bags
                    $product = Product::find($stock['product_id']);
                    
                    if ($product) {
                        $itemTotal = $product->price * $quantity;
                        $totalAmount += $itemTotal;
                        
                        $saleItems[] = [
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'price' => $product->price,
                        ];
                    }
                }
                
                if (count($saleItems) > 0 && $totalAmount > 0) {
                    $invoiceNumber = 'INV-' . strtoupper($faker->bothify('??##??##')) . '-' . now()->format('Ymd');
                    
                    $sale = Sale::create([
                        'driver_id' => $driver->id,
                        'customer_name' => $customerName,
                        'total_amount' => $totalAmount,
                        'invoice_number' => $invoiceNumber,
                        'created_at' => $faker->dateTimeBetween('-30 days', 'now'),
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
                        
                        if ($driverStock) {
                            $driverStock->decrement('quantity', $item['quantity']);
                        }
                    }
                }
            }
        }
        $this->command->info('✅ تم إنشاء 30 عملية بيع');

        $this->command->info('');
        $this->command->info('🎉 تم ملء قاعدة البيانات بنجاح!');
        $this->command->info('');
        $this->command->info('ملخص البيانات:');
        $this->command->info('- 1 مستخدم إداري');
        $this->command->info('- ' . count($categories) . ' فئة');
        $this->command->info('- ' . count($products) . ' منتج (أكياس بلاستيكية)');
        $this->command->info('- ' . count($drivers) . ' سائق');
        $this->command->info('- 30 عملية بيع');
        $this->command->info('');
        $this->command->info('بيانات تسجيل الدخول:');
        $this->command->info('الإداري: admin@inventory.com / password');
        $this->command->info('السائقون: driver1@inventory.com إلى driver10@inventory.com / password');
    }
}
