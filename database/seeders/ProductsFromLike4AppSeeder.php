<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Color;
use App\Models\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductsFromLike4AppSeeder extends Seeder
{
    // إعدادات API
    protected $config = [
        'base_url' => 'https://taxes.like4app.com/online/products',
        'device_id' => null, // سيتم تعيينه من config
        'email' => null,
        'security_code' => null,
        'lang_id' => '1', // العربية
        'batch_size' => 50, // عدد المنتجات في كل طلب
    ];

    // إحصائيات
    protected $stats = [
        'imported' => 0,
        'updated' => 0,
        'failed' => 0,
        'skipped' => 0,
        'categories_mapped' => 0,
    ];

    /**
     * تشغيل Seeder لجلب جميع المنتجات
     */
    public function run()
    {
        $this->command->info('🚀 بدء جلب المنتجات من Like4App API...');

        // تهيئة الإعدادات
        $this->initConfig();

        // 1. جلب جميع الفئات أولاً لتعيين التصنيفات
        $this->command->info('📁 جلب الفئات للمطابقة...');
        $categories = Category::pluck('id', 'external_id')->toArray();

        // 2. جلب المنتجات دفعات (من صفحة 1 إلى 12 كما ذكرت)
        $totalPages = 12;

        for ($page = 1; $page <= $totalPages; $page++) {
            $this->processPage($page, $categories);
        }

        // 3. إنشاء بيانات وهمية للمنتجات التي لم تجد فئاتها
        $this->createFallbackProducts($categories);

        // 4. عرض الإحصائيات النهائية
        $this->showStatistics();
    }

    /**
     * معالجة صفحة من المنتجات
     */
    private function processPage($page, $categories)
    {
        $this->command->info("📄 معالجة الصفحة {$page}...");

        try {
            // جلب المنتجات من API (ستحتاج لتعديل هذا بناءً على هيكل API الفعلي)
            $productsData = $this->fetchProductsPage($page);

            if (empty($productsData)) {
                $this->command->warn("⚠️  لا توجد منتجات في الصفحة {$page}");
                return;
            }

            // معالجة كل منتج
            foreach ($productsData as $productData) {
                $this->processProduct($productData, $categories);
            }

            $this->command->info("✅ اكتملت الصفحة {$page} - تم معالجة " . count($productsData) . " منتج");
        } catch (\Exception $e) {
            $this->command->error("❌ خطأ في الصفحة {$page}: " . $e->getMessage());
            $this->stats['failed']++;
        }
    }

    /**
     * جلب صفحة من المنتجات من API
     */
    private function fetchProductsPage($page)
    {
        // استخدام Cache لتجنب طلبات API المتكررة
        $cacheKey = "like4app_products_page_{$page}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($page) {
            try {
                $response = Http::asMultipart()->post($this->config['base_url'], [
                    'deviceId' => $this->config['device_id'],
                    'email' => $this->config['email'],
                    'securityCode' => $this->config['security_code'],
                    'langId' => $this->config['lang_id'],
                    // يمكنك إضافة pagination parameters هنا إذا كان API يدعمها
                    'page' => $page,
                    'limit' => $this->config['batch_size'],
                ]);

                if (!$response->successful()) {
                    throw new \Exception("فشل API: " . $response->status());
                }

                $data = $response->json();

                if (!isset($data['response']) || $data['response'] != 1) {
                    throw new \Exception("استجابة API غير صالحة");
                }

                return $data['data'] ?? [];
            } catch (\Exception $e) {
                // في حالة فشل API، نعود لبيانات وهمية للاستمرار
                $this->command->warn("⚠️  فشل جلب API، استخدام بيانات وهمية للصفحة {$page}");
                return $this->generateMockProducts($page);
            }
        });
    }

    /**
     * معالجة منتج واحد
     */
    private function processProduct($productData, $categories)
    {
        try {
            // البحث عن الفئة المناسبة
            $categoryId = $this->findCategoryId($productData, $categories);

            // التحقق مما إذا كان المنتج موجوداً بالفعل
            $existingProduct = Product::where('external_id', $productData['productId'] ?? null)->first();

            if ($existingProduct) {
                // تحديث المنتج الموجود
                return $this->updateExistingProduct($existingProduct, $productData, $categoryId);
            } else {
                // إنشاء منتج جديد
                return $this->createNewProduct($productData, $categoryId);
            }
        } catch (\Exception $e) {
            $this->command->error("❌ خطأ في المنتج: " . $e->getMessage());
            $this->stats['failed']++;
            return null;
        }
    }

    /**
     * إنشاء منتج جديد
     */
    private function createNewProduct($productData, $categoryId)
    {
        DB::beginTransaction();

        try {
            // تحضير البيانات بشكل صحيح
            $nameText = $productData['productName'] ?? 'منتج بدون اسم';

            // تحضير البيانات كـ JSON strings
            $nameJson = $this->prepareLocalizedData($nameText);
            $descriptionJson = $this->generateProductDescription($nameText);
            $metaTitleJson = $this->prepareLocalizedData($nameText . ' - أفضل سعر');
            $metaDescJson = $this->prepareLocalizedData('اشتري ' . $nameText . ' بأفضل سعر وجودة عالية');
            $metaKeywordsJson = $this->prepareLocalizedData('منتج, شراء, سعر, جودة');

            // استخراج السعر
            $price = $this->extractPrice($productData);

            // تحويل JSON string إلى array لقراءة الاسم العربي
            $nameArray = json_decode($nameJson, true);
            $arabicName = $nameArray['ar'] ?? $nameText;

            // إنشاء المنتج
            $product = Product::create([
                'external_id' => $productData['productId'] ?? null,
                'category_id' => $categoryId,
                'name' => $nameJson,
                'description' => $descriptionJson,
                'price' => $price,
                'sku' => $this->generateSku($productData),
                'status_id' => 1,
                'stock' => $this->calculateStock($productData),
                'image' => $this->downloadProductImage($productData['productImage'] ?? null),
                'price_text' => ($productData['sellPrice'] ?? $price) . ' ' . ($productData['productCurrency'] ?? 'EGP'),
                'has_discount' => $this->hasDiscount($productData),
                'includes_tax' => ($productData['vatPercentage'] ?? 0) > 0,
                'meta_title' => $metaTitleJson,
                'meta_description' => $metaDescJson,
                'meta_keywords' => $metaKeywordsJson,
                'policies' => $this->generatePolicies($productData),
            ]);

            // إضافة تاجات تلقائية
            $this->attachTags($product, $productData, $arabicName);

            // إضافة مواد
            $this->attachMaterials($product);

            // إضافة خيارات المنتج إذا كانت موجودة
            if (isset($productData['productOptionalFields']) && !empty($productData['productOptionalFields'])) {
                $this->createProductOptions($product, $productData['productOptionalFields']);
            }

            DB::commit();

            $this->stats['imported']++;
            $this->command->info("✅ تم إنشاء منتج: {$arabicName}");

            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("خطأ في إنشاء المنتج: " . $e->getMessage());
        }
    }

    /**
     * تحديث منتج موجود
     */
    private function updateExistingProduct($product, $productData, $categoryId)
    {
        try {
            $arabicName = $this->extractArabicName($product->name);

            $updates = [
                'category_id' => $categoryId ?? $product->category_id,
                'price' => $this->extractPrice($productData),
                'price_text' => ($productData['sellPrice'] ?? $product->price) . ' ' . ($productData['productCurrency'] ?? 'EGP'),
                'has_discount' => $this->hasDiscount($productData),
                'includes_tax' => ($productData['vatPercentage'] ?? 0) > 0,
            ];

            // تحديث الاسم إذا كان مختلفاً
            if (isset($productData['productName'])) {
                $newName = $this->prepareLocalizedData($productData['productName']);
                $newArabicName = $this->extractArabicName($newName);

                if ($arabicName !== $newArabicName) {
                    $updates['name'] = $newName;
                    $arabicName = $newArabicName; // تحديث للمتغير المحلي
                }
            }

            $product->update($updates);

            $this->stats['updated']++;
            $this->command->info("🔄 تم تحديث منتج: {$arabicName}");

            return $product;
        } catch (\Exception $e) {
            throw new \Exception("خطأ في تحديث المنتج: " . $e->getMessage());
        }
    }

    /**
     * إنشاء بيانات وهمية للمنتجات غير الموجودة
     */
    private function createFallbackProducts($categories)
    {
        $this->command->info('🎲 إنشاء منتجات وهمية إضافية...');

        $fallbackProducts = [
            [
                'name' => 'بطاقة شحن موبايلي 50 ريال',
                'price' => 50,
                'category_external_id' => 267,
                'description' => 'بطاقة شحن لشبكة موبايلي بقيمة 50 ريال سعودي'
            ],
            [
                'name' => 'بطاقة أيتونز 25 دولار',
                'price' => 25,
                'category_external_id' => 268,
                'description' => 'بطاقة iTunes للشراء من متجر Apple'
            ],
            [
                'name' => 'بطاقة Google Play 30 ريال',
                'price' => 30,
                'category_external_id' => 269,
                'description' => 'بطاقة Google Play للشراء من متجر التطبيقات'
            ],
            [
                'name' => 'Netflix شهر واحد',
                'price' => 45,
                'category_external_id' => 270,
                'description' => 'اشتراك Netflix لمدة شهر واحد'
            ],
            [
                'name' => 'بطاقة Steam 20 دولار',
                'price' => 20,
                'category_external_id' => 271,
                'description' => 'بطاقة Steam للشراء من منصة الألعاب'
            ],
        ];

        foreach ($fallbackProducts as $productData) {
            $categoryId = $categories[$productData['category_external_id']] ?? null;

            if (!$categoryId) {
                $this->stats['skipped']++;
                continue;
            }

            // التحقق من عدم وجود المنتج بالفعل
            $exists = Product::where('name->ar', $productData['name'])->exists();

            if (!$exists) {
                Product::create([
                    'category_id' => $categoryId,
                    'name' => $this->prepareLocalizedData($productData['name']),
                    'description' => $this->prepareLocalizedData($productData['description']),
                    'price' => $productData['price'],
                    'sku' => 'FALLBACK-' . Str::random(6),
                    'status_id' => 1,
                    'stock' => rand(50, 200),
                    'image' => $this->getRandomProductImage(),
                    'price_text' => $productData['price'] . ' ريال',
                    'has_discount' => rand(0, 1),
                    'includes_tax' => true,
                    'meta_title' => $this->prepareLocalizedData($productData['name']),
                    'meta_description' => $this->prepareLocalizedData($productData['description']),
                ]);

                $this->stats['imported']++;
            }
        }
    }

    /**
     * توليد بيانات وهمية عندما يفشل API
     */
    private function generateMockProducts($page)
    {
        $products = [];
        $itemsPerPage = $this->config['batch_size'];

        for ($i = 0; $i < $itemsPerPage; $i++) {
            $productId = (($page - 1) * $itemsPerPage) + $i + 1;

            $products[] = [
                'productId' => $productId,
                'categoryId' => rand(267, 280),
                'productName' => 'منتج تجريبي ' . $productId,
                'productPrice' => rand(10, 200) + (rand(0, 99) / 100),
                'productImage' => 'https://likecard-space.fra1.digitaloceanspaces.com/products/mock-' . rand(1, 50) . '.jpg',
                'productCurrency' => 'SAR',
                'optionalFieldsExist' => rand(0, 1),
                'sellPrice' => rand(10, 200) + (rand(0, 99) / 100),
                'available' => rand(0, 1),
                'vatPercentage' => rand(0, 15),
                'productOptionalFields' => $this->generateMockOptionalFields(),
            ];
        }

        return $products;
    }

    /**
     * توليد حقول اختيارية وهمية
     */
    private function generateMockOptionalFields()
    {
        $fields = ['userid', 'phone', 'email', 'account_number', 'reference'];
        $fieldTypes = ['10', '20', '30']; // نص، رقم، قائمة

        $result = [];
        $count = rand(0, 3);

        for ($i = 0; $i < $count; $i++) {
            $field = $fields[array_rand($fields)];

            $result[] = [
                'id' => rand(100, 999),
                'required' => rand(0, 1),
                'label' => strtoupper(str_replace('_', ' ', $field)),
                'hint' => 'أدخل ' . $field,
                'fieldTypeId' => $fieldTypes[array_rand($fieldTypes)],
                'fieldCode' => $field,
            ];
        }

        return $result;
    }

    /**
     * ========== الدوال المساعدة ==========
     */

    private function initConfig()
    {
        $this->config['device_id'] = config('services.like4app.device_id');
        $this->config['email'] = config('services.like4app.email');
        $this->config['security_code'] = config('services.like4app.security_code');

        if (empty($this->config['device_id'])) {
            throw new \Exception('يرجى تعيين إعدادات Like4App في ملف config/services.php');
        }
    }

    private function findCategoryId($productData, $categories)
    {
        $externalCategoryId = $productData['categoryId'] ?? null;

        if ($externalCategoryId && isset($categories[$externalCategoryId])) {
            $this->stats['categories_mapped']++;
            return $categories[$externalCategoryId];
        }

        // إذا لم توجد الفئة، استخدم فئة افتراضية
        $defaultCategory = Category::where('slug', 'uncategorized')->first();

        if (!$defaultCategory) {
            $defaultCategory = Category::create([
                'name' => ['en' => 'Uncategorized', 'ar' => 'غير مصنف'],
                'slug' => 'uncategorized',
                'status_id' => 1,
            ]);
        }

        return $defaultCategory->id;
    }

    private function downloadProductImage($imageUrl)
    {
        if (!$imageUrl) return null;

        try {
            // لاحظ: الصورة في مثالك تعطي خطأ، لذلك نستخدم صور وهمية
            if (Str::contains($imageUrl, '066ce-x50.jpg')) {
                return $this->getRandomProductImage();
            }

            $response = Http::timeout(30)->get($imageUrl);

            if ($response->successful()) {
                $extension = pathinfo($imageUrl, PATHINFO_EXTENSION) ?: 'jpg';
                $filename = 'products/' . Str::random(20) . '.' . $extension;

                Storage::disk('public')->put($filename, $response->body());
                return $filename;
            }
        } catch (\Exception $e) {
            // في حالة الخطأ، نعود لصورة وهمية
        }

        return $this->getRandomProductImage();
    }

    private function getRandomProductImage()
    {
        $images = [
            'products/sample-1.jpg',
            'products/sample-2.jpg',
            'products/sample-3.jpg',
            'products/sample-4.jpg',
        ];

        // يمكنك وضع صور افتراضية في المسار المحدد
        return $images[array_rand($images)];
    }

    private function prepareLocalizedData($text)
    {
        return json_encode([
            'en' => $this->translateToEnglish($text),
            'ar' => $text,
        ], JSON_UNESCAPED_UNICODE);
    }
    private function translateToEnglish($arabicText)
    {
        // ترجمة بسيطة (يمكنك استخدام خدمة ترجمة فعلية)
        $translations = [
            'بطاقة' => 'Card',
            'شحن' => 'Recharge',
            'موبايلي' => 'Mobily',
            'ريال' => 'SAR',
            'أيتونز' => 'iTunes',
            'دولار' => 'USD',
            'جوجل' => 'Google',
            'بلاي' => 'Play',
            'نتفلكس' => 'Netflix',
            'ستيم' => 'Steam',
            'منتج' => 'Product',
            'تجريبي' => 'Demo',
        ];

        $english = $arabicText;
        foreach ($translations as $ar => $en) {
            $english = str_replace($ar, $en, $english);
        }

        return $english ?: $arabicText;
    }

    private function generateProductDescription($name)
    {
        // $name الآن هي JSON string، نحتاج لتحويلها أولاً
        $nameArray = json_decode($name, true);
        $arabicName = $nameArray['ar'] ?? $name;

        $descriptions = [
            'منتج عالي الجودة مع ضمان الجودة',
            'أفضل سعر في السوق مع خدمة عملاء ممتازة',
            'منتج معتمد من الشركة المصنعة',
            'توصيل سريع وخدمة موثوقة',
            'منتج رقمي مع تفعيل فوري',
        ];

        $description = $descriptions[array_rand($descriptions)];

        return json_encode([
            'en' => 'High quality product with excellent service',
            'ar' => $arabicName . ' - ' . $description,
        ], JSON_UNESCAPED_UNICODE);
    }
    private function hasDiscount($productData)
    {
        if (isset($productData['productPrice'], $productData['sellPrice'])) {
            return $productData['productPrice'] > $productData['sellPrice'];
        }
        return rand(0, 1);
    }

    private function generatePolicies($productData)
    {
        return json_encode([
            'instructions' => [
                'en' => "1. Purchase the product\n2. Receive activation code\n3. Use code immediately\n4. Contact support if needed",
                'ar' => "1. شراء المنتج\n2. استلام كود التفعيل\n3. استخدام الكود فوراً\n4. التواصل مع الدعم إذا لزم الأمر",
            ],
            'terms' => [
                'en' => 'Terms and conditions apply. Product is non-refundable.',
                'ar' => 'تطبق الشروط والأحكام. المنتج غير قابل للاسترداد.',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
    /**
     * استخراج السعر من بيانات المنتج
     */
    private function extractPrice($productData)
    {
        $price = $productData['productPrice'] ?? $productData['sellPrice'] ?? 0;

        // التحويل لرقم صحيح أو عشري
        if (is_string($price)) {
            $price = (float) str_replace(['EGP', 'SAR', 'USD', ',', ' '], '', $price);
        }

        return max(0, $price);
    }

    /**
     * توليد SKU
     */
    private function generateSku($productData)
    {
        $productId = $productData['productId'] ?? Str::random(6);
        $prefix = 'PROD-';

        // إضافة بادئة بناءً على نوع العملة
        $currency = $productData['productCurrency'] ?? '';
        if ($currency === 'EGP') {
            $prefix = 'EG-';
        } elseif ($currency === 'SAR') {
            $prefix = 'SA-';
        } elseif ($currency === 'USD') {
            $prefix = 'US-';
        }

        return $prefix . $productId;
    }

    /**
     * حساب المخزون
     */
    private function calculateStock($productData)
    {
        if (isset($productData['available'])) {
            return $productData['available'] ? rand(10, 200) : 0;
        }

        return rand(50, 100);
    }

    /**
     * تحضير بيانات وهمية للاستخدام
     */
    private function prepareMockData($page)
    {
        // بيانات منتجات مصرية بناءً على العملة EGP
        $egyptianProducts = [
            [
                'productName' => 'شحن فودافون 10 جنيه',
                'productPrice' => 10,
                'productCurrency' => 'EGP',
                'sellPrice' => 10,
            ],
            [
                'productName' => 'شحن اتصالات 20 جنيه',
                'productPrice' => 20,
                'productCurrency' => 'EGP',
                'sellPrice' => 20,
            ],
            [
                'productName' => 'شحن اورانج 50 جنيه',
                'productPrice' => 50,
                'productCurrency' => 'EGP',
                'sellPrice' => 50,
            ],
        ];

        return $egyptianProducts;
    }
    private function attachTags($product, $productData, $arabicName)
    {
        $tags = ['بطاقات', 'رقمية', 'شحن', 'فوري'];

        // تحليل الاسم العربي لإضافة تاجات مناسبة
        if (
            str_contains($arabicName, 'موبايلي') || str_contains($arabicName, 'فودافون') ||
            str_contains($arabicName, 'اتصالات') || str_contains($arabicName, 'اورانج')
        ) {
            $tags[] = 'اتصالات';
            $tags[] = 'شحن رصيد';
        }

        if (
            str_contains($arabicName, 'نتفلكس') || str_contains($arabicName, 'يوتيوب') ||
            str_contains($arabicName, 'سبوتيفاي')
        ) {
            $tags[] = 'ترفيه';
            $tags[] = 'بث';
        }

        if (
            str_contains($arabicName, 'ستيم') || str_contains($arabicName, 'بلايستيشن') ||
            str_contains($arabicName, 'اكس بوكس')
        ) {
            $tags[] = 'ألعاب';
            $tags[] = 'تسلية';
        }

        foreach ($tags as $tagName) {
            $tag = Tag::firstOrCreate(
                ['name' => $tagName],
                ['slug' => Str::slug($tagName)]
            );

            $product->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    // private function attachColors($product)
    // {
    //     $colors = Color::inRandomOrder()->limit(rand(1, 3))->pluck('id');
    //     $product->colors()->sync($colors);
    // }
    /**
     * استخراج الاسم العربي من حقل JSON
     */
    private function extractArabicName($jsonString)
    {
        if (is_array($jsonString)) {
            return $jsonString['ar'] ?? '';
        }

        if (is_string($jsonString)) {
            $decoded = json_decode($jsonString, true);
            return $decoded['ar'] ?? $jsonString;
        }

        return '';
    }

    /**
     * استخراج الاسم الإنجليزي من حقل JSON
     */
    private function extractEnglishName($jsonString)
    {
        if (is_array($jsonString)) {
            return $jsonString['en'] ?? '';
        }

        if (is_string($jsonString)) {
            $decoded = json_decode($jsonString, true);
            return $decoded['en'] ?? '';
        }

        return '';
    }
    private function attachMaterials($product)
    {
        $materials = Material::inRandomOrder()->limit(rand(1, 2))->pluck('id');

        foreach ($materials as $materialId) {
            $product->materials()->attach($materialId, [
                'quantity' => rand(1, 5),
                'unit' => ['piece', 'gram', 'meter'][rand(0, 2)],
            ]);
        }
    }

    private function createProductOptions($product, $optionalFields)
    {
        // يمكنك إنشاء جدول ProductOptions هنا
        // هذا مثال مبسط:

        foreach ($optionalFields as $field) {
            DB::table('product_options')->insert([
                'product_id' => $product->id,
                'field_name' => $field['label'] ?? $field['fieldCode'] ?? 'field',
                'field_type' => $field['fieldTypeId'] ?? 'text',
                'required' => $field['required'] ?? 0,
                'hint' => $field['hint'] ?? '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function showStatistics()
    {
        $this->command->info('=' . str_repeat('=', 50));
        $this->command->info('📊 إحصائيات الاستيراد:');
        $this->command->info('├─ المنتجات الجديدة: ' . $this->stats['imported']);
        $this->command->info('├─ المنتجات المحدثة: ' . $this->stats['updated']);
        $this->command->info('├ـ المنتجات الفاشلة: ' . $this->stats['failed']);
        $this->command->info('├─ المنتجات المتخطاة: ' . $this->stats['skipped']);
        $this->command->info('└─ الفئات المطابقة: ' . $this->stats['categories_mapped']);
        $this->command->info('=' . str_repeat('=', 50));
        $this->command->info('✅ اكتمل استيراد المنتجات بنجاح!');
    }
}
