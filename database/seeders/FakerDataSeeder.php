<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDimension;
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

        // Base product templates for generating 200 products
        $productTemplates = [
            // Shopping Bags
            ['baseName' => 'كيس تسوق', 'basePrice' => 2.00, 'category' => 0, 'sizes' => ['صغير', 'متوسط', 'كبير', 'قوي', 'قابل لإعادة الاستخدام']],
            // Garbage Bags
            ['baseName' => 'كيس قمامة', 'basePrice' => 4.00, 'category' => 1, 'sizes' => ['10 لتر', '20 لتر', '30 لتر', '50 لتر', '100 لتر', 'معزز', 'معطّر']],
            // Food Bags
            ['baseName' => 'كيس حفظ طعام', 'basePrice' => 1.75, 'category' => 2, 'sizes' => ['صغير', 'متوسط', 'كبير', 'ساندويتش', 'خضار']],
            // Ziploc Bags
            ['baseName' => 'كيس سحاب', 'basePrice' => 4.00, 'category' => 3, 'sizes' => ['صغير', 'متوسط', 'كبير', 'عائلي', 'شفاف']],
            // Freezer Bags
            ['baseName' => 'كيس تجميد', 'basePrice' => 5.00, 'category' => 4, 'sizes' => ['صغير', 'متوسط', 'كبير', 'مقاوم للصقيع']],
            // Medical Waste Bags
            ['baseName' => 'كيس نفايات طبية', 'basePrice' => 7.00, 'category' => 5, 'sizes' => ['صغير', 'متوسط', 'كبير', 'خطرة']],
            // Packaging Bags
            ['baseName' => 'كيس تغليف', 'basePrice' => 2.50, 'category' => 6, 'sizes' => ['شفاف', 'ملون', 'هدايا', 'منتجات']],
            // Agriculture Bags
            ['baseName' => 'كيس زراعة', 'basePrice' => 2.25, 'category' => 7, 'sizes' => ['صغير', 'متوسط', 'كبير', 'شتلات']],
        ];

        // Create 50 Products
        $products = [];
        $imageIndex = 0;
        $targetProducts = 50;
        
        // Size categories for dimensions
        $sizeCategories = [
            // Small bags (shopping bags, small packaging)
            ['width' => 10.0, 'depth' => 8.0, 'height' => 5.0, 'weight' => 0.1],
            // Medium bags (medium shopping, garbage bags)
            ['width' => 15.0, 'depth' => 12.0, 'height' => 8.0, 'weight' => 0.2],
            // Large bags (large shopping, large garbage)
            ['width' => 20.0, 'depth' => 18.0, 'height' => 12.0, 'weight' => 0.4],
            // Extra large (industrial bags)
            ['width' => 28.0, 'depth' => 25.0, 'height' => 18.0, 'weight' => 0.6],
        ];
        
        $productCount = 0;
        while ($productCount < $targetProducts) {
            foreach ($productTemplates as $template) {
                if ($productCount >= $targetProducts) {
                    break;
                }
                
                $category = $categories[$template['category']];
                
                // Generate variations of each template
                foreach ($template['sizes'] as $size) {
                    if ($productCount >= $targetProducts) {
                        break;
                    }
                    
                    // Add variation number if needed
                    $variation = '';
                    if ($productCount > count($template['sizes']) * 2) {
                        $variation = ' ' . ($faker->numberBetween(1, 5));
                    }
                    
                    $productName = $template['baseName'] . ' ' . $size . $variation;
                    $productPrice = $template['basePrice'] + $faker->randomFloat(2, -0.50, 2.00);
                    $productPrice = max(0.50, $productPrice); // Ensure minimum price
                    
                    $product = Product::create([
                        'name' => $productName,
                        'price' => round($productPrice, 2),
                        'category_id' => $category->id,
                        'description' => $faker->sentence(10),
                        'image' => $unsplashImages[$imageIndex % count($unsplashImages)],
                    ]);
                    
                    $products[] = $product;
                    $imageIndex++;
                    $productCount++;
                    
                    // Select size category based on product index
                    $sizeCategory = $sizeCategories[$productCount % count($sizeCategories)];
                    
                    // Add small variations for realism (±1.5cm)
                    $width = $sizeCategory['width'] + $faker->randomFloat(1, -1.5, 1.5);
                    $depth = $sizeCategory['depth'] + $faker->randomFloat(1, -1.5, 1.5);
                    $height = $sizeCategory['height'] + $faker->randomFloat(1, -1.0, 1.0);
                    
                    // Ensure dimensions stay within small range (6-30cm)
                    $width = max(6.0, min(30.0, $width));
                    $depth = max(6.0, min(30.0, $depth));
                    $height = max(4.0, min(22.0, $height));
                    
                    ProductDimension::create([
                        'product_id' => $product->id,
                        'width' => round($width, 1),
                        'depth' => round($depth, 1),
                        'height' => round($height, 1),
                        'weight' => $sizeCategory['weight'] + $faker->randomFloat(2, 0, 0.5),
                        'rotatable' => true,
                        'fragile' => $faker->boolean(20), // 20% chance of being fragile
                    ]);
                    
                    // Don't create stock here - we'll distribute 200 total items later
                }
            }
        }
        
        // Distribute exactly 200 total items across 50 products (4 items per product on average)
        $totalItemsToDistribute = 200;
        $productsCount = count($products);
        
        if ($productsCount > 0) {
            // Calculate base quantity per product (200 items / 50 products = 4 items per product)
            $baseQuantity = (int) floor($totalItemsToDistribute / $productsCount);
            $remainder = $totalItemsToDistribute % $productsCount;
            
            // Shuffle products to randomize which ones get extra items
            shuffle($products);
            
            foreach ($products as $index => $product) {
                // Most products get base quantity (4), first 'remainder' products get +1 (5)
                $quantity = $baseQuantity + ($index < $remainder ? 1 : 0);
                
                WarehouseStock::create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
            }
        }
        
        $this->command->info('✅ تم إنشاء ' . count($products) . ' منتج مع إجمالي ' . $totalItemsToDistribute . ' عنصر في المخزون');

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
