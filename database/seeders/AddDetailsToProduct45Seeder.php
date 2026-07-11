<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{
    Product,
    Color,
    Material,
    PrintingMethod,
    PrintLocation,
    Offer,
    Size,
    Discount,
    DeliveryTime,
    Warranty,
    Feature,
    Image,
    ProductSizeTier,
    ProductOptions,
    PricingTiers,
    Review,
    User
};
use Illuminate\Support\Facades\DB;
use Faker\Factory;

class AddDetailsToProduct45Seeder extends Seeder
{
    protected $faker;

    public function __construct()
    {
        $this->faker = Factory::create('ar_SA');
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // البحث عن المنتج رقم 45
        $product = Product::withTrashed()->find(45);

        if (!$product) {
            $this->command->error('❌ المنتج رقم 45 غير موجود!');
            return;
        }

        $this->command->info('🎯 بدء إضافة تفاصيل للمنتج: ' . $product->name);

        // إضافة الخصم
        $this->addDiscount($product);

   
        // إضافة العروض
        $this->addOffers($product);

        // إضافة الصور
        $this->addImages($product);

        // إضافة التقييمات والمراجعات
        $this->addReviews($product);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // عرض ملخص شامل
        $this->showCompleteSummary($product);
    }

    private function addDiscount(Product $product): void
    {
        $hasDiscount = rand(0, 1); // 50% فرصة للحصول على خصم

        if ($hasDiscount) {
            $discountTypes = ['percentage', 'fixed'];
            $discountType = $discountTypes[array_rand($discountTypes)];

            $discountValue = $discountType === 'percentage'
                ? rand(10, 30)
                : rand(10, 50);

            Discount::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'is_active' => true,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(rand(30, 90)),
                ]
            );

            $this->command->info('✅ تم إضافة خصم ' . $discountValue . ($discountType === 'percentage' ? '%' : ' جنيه'));
        }
    }



    private function addOffers(Product $product): void
    {
        $offers = [
            ['name' => 'عرض الصيف', 'description' => 'خصومات صيفية حصرية'],
            ['name' => 'عرض التخفيضات الكبرى', 'description' => 'تخفيضات على جميع المنتجات'],
            ['name' => 'عرض الشراء الجماعي', 'description' => 'خصم عند الشراء بكميات'],
            ['name' => 'عرض التوصيل المجاني', 'description' => 'توصيل مجاني لجميع الطلبات'],
            ['name' => 'عرض العضوية الذهبية', 'description' => 'خصومات حصرية لأعضاء النادي'],
            ['name' => 'عرض نهاية الموسم', 'description' => 'تخفيضات نهاية الموسم'],
        ];

        $selectedOffers = array_rand($offers, rand(1, 3));

        if (!is_array($selectedOffers)) {
            $selectedOffers = [$selectedOffers];
        }

        foreach ($selectedOffers as $index) {
            $offerData = $offers[$index];
            $offer = Offer::firstOrCreate(
                ['name' => $offerData['name']],
                [
                    'description' => $offerData['description'],
                    'discount_value' => rand(10, 50),
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(rand(30, 180)),
                    'is_active' => true,
                    //  'min_purchase' => rand(0, 1) ? rand(100, 500) : null,
                ]
            );

            $product->offers()->syncWithoutDetaching([$offer->id]);
        }

        $this->command->info('🏷️ تم إضافة ' . count($selectedOffers) . ' عرض');
    }



    private function addImages(Product $product): void
    {
        $images = [
            ['path' => 'https://i.ibb.co/9FB8yLZ/16.png', 'alt' => 'صورة أمامية للمنتج', 'order' => 1, 'is_primary' => true],
            ['path' => 'https://i.ibb.co/nqmT7fgh/12.png', 'alt' => 'صورة خلفية للمنتج', 'order' => 2, 'is_primary' => false],
            ['path' => 'https://i.ibb.co/7x4HY1t1/15.png', 'alt' => 'صورة مقرّبة للتفاصيل', 'order' => 3, 'is_primary' => false]
        ];

        // اختيار 4-6 صور عشوائية
        $selectedImages = array_rand($images, rand(2, 3));

        if (!is_array($selectedImages)) {
            $selectedImages = [$selectedImages];
        }

        $primarySet = false;
        foreach ($selectedImages as $index) {
            $imageData = $images[$index];

            // التأكد من وجود صورة رئيسية واحدة فقط
            if (!$primarySet) {
                $imageData['is_primary'] = true;
                $primarySet = true;
            } else {
                $imageData['is_primary'] = false;
            }

            Image::updateOrCreate(
                [
                    'imageable_id' => $product->id,
                    'imageable_type' => Product::class,
                    'path' => $imageData['path']
                ],
                [
                    'alt' => $imageData['alt'],
                    'type' => 'product',
                    'order' => $imageData['order'],
                    'is_active' => true,
                    'is_primary' => $imageData['is_primary'],
                ]
            );
        }

        $this->command->info('🖼️ تم إضافة ' . count($selectedImages) . ' صورة');
    }

    private function addReviews(Product $product): void
    {
        // الحصول على بعض المستخدمين أو إنشاءهم
        $users = User::take(20)->get();

        if ($users->isEmpty()) {
            // إنشاء بعض المستخدمين إذا لم يكن هناك مستخدمين
            for ($i = 1; $i <= 10; $i++) {
                $users[] = User::create([
                    'name' => $this->faker->name(),
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => bcrypt('password123'),
                ]);
            }
        }

        // إنشاء 15-30 تقييم عشوائي
        $numReviews = rand(15, 30);

        for ($i = 0; $i < $numReviews; $i++) {
            $user = $users->random();

            $rating = rand(3, 5); // معظم التقييمات إيجابية
            $comment = rand(0, 1) ? $this->generateReviewComment($rating) : null;

            Review::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'user_id' => $user->id
                ],
                [
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
                ]
            );
        }

        $this->command->info('⭐ تم إضافة ' . $numReviews . ' تقييم');
    }

    private function generateReviewComment($rating): string
    {
        $positiveComments = [
            'منتج رائع جداً، أنصح الجميع به!',
            'الجودة ممتازة والسعر مناسب',
            'التوصيل كان سريعاً والمنتج أفضل مما توقعت',
            'شكراً لكم على هذا المنتج الرائع',
            'جودة عالية وتنفيذ ممتاز',
            'اشتريته لعدة مرات وسأستمر في الشراء',
            'المنتج يستحق أكثر من سعره',
            'تجربة شراء ممتازة بكل المقاييس',
            'الخدمة والعناية بالعميل ممتازة',
            'شكراً لكم على الاهتمام بالجودة',
        ];

        $neutralComments = [
            'منتج جيد بشكل عام',
            'السعر مناسب للجودة',
            'لا بأس به لكن يمكن تحسينه',
            'متوسط الجودة',
            'يناسب الاستخدام اليومي',
        ];

        $negativeComments = [
            'يمكن تحسين الجودة',
            'التوصيل تأخر قليلاً',
            'المقاس أصغر قليلاً مما توقعت',
            'اللون يختلف قليلاً عن الصورة',
            'يحتاج بعض التحسينات',
        ];

        if ($rating >= 4) {
            return $positiveComments[array_rand($positiveComments)];
        } elseif ($rating == 3) {
            return $neutralComments[array_rand($neutralComments)];
        } else {
            return $negativeComments[array_rand($negativeComments)];
        }
    }

    private function showCompleteSummary(Product $product): void
    {
        // تحميل جميع العلاقات
        $product->load([
            'colors',
            'materials',
            'printingMethods',
            'printLocations',
            'offers',
            'sizes',
            'pricingTiers',
            'deliveryTime',
            'warranty',
            'features',
            'options',
            'images',
            'reviews'
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('🎉 تم إضافة تفاصيل كاملة للمنتج بنجاح!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('📋 ملخص شامل للمنتج رقم ' . $product->id);
        $this->command->info('========================================');
        $this->command->info('📦 اسم المنتج: ' . $product->name);
        $this->command->info('🏷️ السعر: ' . $product->price . ' جنيه');

        if ($product->discount) {
            $this->command->info('💰 الخصم: ' . $product->discount->discount_value .
                ($product->discount->discount_type === 'percentage' ? '%' : ' جنيه'));
            $this->command->info('💳 السعر النهائي: ' . $product->final_price . ' جنيه');
        }

        $this->command->info('📊 المخزون: ' . $product->stock . ' قطعة');
        $this->command->info('🎨 الألوان: ' . $product->colors->count() . ' لون');
        $this->command->info('🧵 المواد: ' . $product->materials->count() . ' مادة');
        $this->command->info('🖨️ طرق الطباعة: ' . $product->printingMethods->count() . ' طريقة');
        $this->command->info('📍 أماكن الطباعة: ' . $product->printLocations->count() . ' مكان');
        $this->command->info('🏷️ العروض: ' . $product->offers->count() . ' عرض');
        $this->command->info('📏 المقاسات: ' . $product->sizes->count() . ' مقاس');
        $this->command->info('💰 مستويات التسعير: ' . $product->pricingTiers->count() . ' مستوى');
        $this->command->info('🚚 وقت التوصيل: ' . ($product->deliveryTime ? $product->deliveryTime->from_days . '-' . $product->deliveryTime->to_days . ' أيام' : 'غير محدد'));
        $this->command->info('🛡️ الضمان: ' . ($product->warranty ? $product->warranty->duration_months . ' شهر' : 'لا يوجد'));
        $this->command->info('📋 الميزات: ' . $product->features->count() . ' ميزة');
        $this->command->info('⚙️ الخيارات: ' . $product->options->count() . ' خيار');
        $this->command->info('🖼️ الصور: ' . $product->images->count() . ' صورة');
        $this->command->info('⭐ التقييمات: ' . $product->reviews->count() . ' تقييم');

        if ($product->reviews->count() > 0) {
            $averageRating = round($product->reviews->avg('rating'), 1);
            $this->command->info('⭐ متوسط التقييم: ' . $averageRating . '/5');
        }

        $this->command->info('');
        $this->command->info('🔗 يمكنك الآن عرض المنتج على الرابط:');
        $this->command->info('📱 الواجهة الأمامية: /product/' . $product->id);
        $this->command->info('⚙️ لوحة التحكم: /admin/products/' . $product->id . '/edit');
        $this->command->info('');
        $this->command->info('✅ تمت العملية بنجاح! المنتج جاهز للعرض والبيع.');
    }
}
