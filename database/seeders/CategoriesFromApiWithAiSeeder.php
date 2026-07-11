<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\HasAiTranslation;

class CategoriesFromApiWithAiSeeder extends Seeder
{
    use HasAiTranslation;

    /**
     * تشغيل Seeder لجلب Categories من API
     */
    public function run()
    {
        // جلب البيانات من API مع Cache
        $categories = Cache::remember('like4app_categories_v2', now()->addHours(5), function () {
            $response = Http::asMultipart()->post(
                'https://taxes.like4app.com/online/categories',
                [
                    'deviceId'     => config('services.like4app.device_id'),
                    'email'        => config('services.like4app.email'),
                    'securityCode' => config('services.like4app.security_code'),
                    'langId'       => config('services.like4app.lang_id'),
                ]
            );

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch categories: ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['response']) || $data['response'] != 1) {
                throw new \Exception('API response error');
            }

            return $data['data'] ?? [];
        });

        $this->command->info("Processing " . count($categories) . " categories...");

        foreach ($categories as $category) {
            $this->storeCategory($category);
        }

        $this->command->info("✅ Categories imported successfully with AI translation!");
    }

    /**
     * تخزين Category مع معالجة البيانات
     */
    private function storeCategory(array $category, $parentId = null)
    {
        // التحقق أولاً إذا كان الـ category موجوداً بالفعل
        $existingCategory = Category::where('external_id', $category['id'] ?? null)
            ->first();

        // إذا كان موجوداً بالفعل، تخطي
        if ($existingCategory) {
            $this->command->info("  ⏭️  Category already exists: {$existingCategory->name} (ID: {$existingCategory->id})");
            return $existingCategory;
        }

        // جلب وتخزين الصورة
        $imagePath = $this->downloadImage($category['amazonImage'] ?? null);

        // تحضير البيانات ثنائية اللغة
        $name = $this->cleanUtf8($category['categoryName'] ?? 'Category');

        // استخدام ترجمة أفضل
        $description = $this->generateDescriptionFromName($name);

        // إنشاء البيانات ثنائية اللغة
        $nameArray = $this->makeBiLang($name);
        $descriptionArray = $this->makeBiLang($description);

        // توليد slug فريد
        $baseSlug = Str::slug($nameArray['en'] ?? $nameArray['ar'] ?? 'category');
        $slug = $this->generateUniqueSlug($baseSlug, $parentId);

        $categoryData = [
            'external_id'      => $category['id'] ?? null,
            'name'             => $nameArray,
            'description'      => $descriptionArray,
            'slug'             => $slug,
            'parent_id'        => $parentId,
            'status_id'        => 1,
            'order'            => $category['ordering'] ?? 0,
            'image'            => $imagePath,
            'meta_title'       => $this->makeBiLang($name . ' - Best Products'),
            'meta_description' => $this->makeBiLang('Browse all ' . $name . ' products with best prices and quality'),
            'meta_keywords'    => $this->makeBiLang($name . ', products, services, shopping'),
        ];

        try {
            $cat = Category::create($categoryData);

            // معالجة الـ Children إذا وجدت
            if (!empty($category['childs']) && is_array($category['childs'])) {
                $childCount = 0;
                foreach ($category['childs'] as $child) {
                    if (!empty($child['id'])) {
                        $this->storeCategory($child, $cat->id);
                        $childCount++;
                    }
                }
                if ($childCount > 0) {
                    $this->command->info("  ↳ Added {$childCount} sub-categories");
                }
            }

            $this->command->info("  ✅ Category: {$name} imported (ID: {$cat->id}, Slug: {$cat->slug})");

            return $cat;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // إذا حدث خطأ تكرار رغم التحقق، استمر مع slug جديد
            $this->command->error("  ⚠️ Duplicate slug, generating new one: {$slug}");

            // توليد slug جديد فريد تماماً
            $uniqueSlug = $baseSlug . '-' . uniqid();
            $categoryData['slug'] = $uniqueSlug;

            $cat = Category::create($categoryData);
            $this->command->info("  ✅ Category: {$name} imported with unique slug (ID: {$cat->id}, Slug: {$cat->slug})");

            return $cat;
        } catch (\Exception $e) {
            $this->command->error("  ❌ Failed to import category {$name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * توليد وصف أفضل من الاسم
     */
    private function generateDescriptionFromName(string $name): string
    {
        $descriptions = [
            "category with various products and services",
            "wide range of products and services",
            "collection of premium products",
            "selection of quality services and products",
            "offering diverse products and solutions"
        ];

        $randomDescription = $descriptions[array_rand($descriptions)];
        return "{$name} {$randomDescription}";
    }

    /**
     * توليد slug فريد مع التحقق من عدم التكرار
     */
    private function generateUniqueSlug(string $baseSlug, $parentId = null): string
    {
        // تنظيف الـ slug بشكل أفضل
        $baseSlug = Str::slug($baseSlug);

        // إضافة البادئة إذا كان parent موجوداً
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            // التحقق من الوجود مع parent_id المحدد
            $exists = Category::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->exists();

            if (!$exists) {
                break;
            }

            // إذا كان مكرراً، أضف رقم
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            // لمنع التكرار اللانهائي
            if ($counter > 100) {
                $slug = $baseSlug . '-' . uniqid();
                break;
            }
        }

        return $slug;
    }

    /**
     * تحميل الصورة وتخزينها محلياً
     */
    private function downloadImage(?string $url): ?string
    {
        if (!$url) return null;

        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $extension = pathinfo($url, PATHINFO_EXTENSION);
                $extension = $extension ?: 'jpg';

                // تأكد أن الامتداد صالح
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($extension), $allowedExtensions)) {
                    $extension = 'jpg';
                }

                $filename = 'categories/' . Str::random(20) . '.' . $extension;

                Storage::disk('public')->put($filename, $response->body());

                return $filename;
            }
        } catch (\Exception $e) {
            $this->command->warn("  ⚠️ Failed to download image: " . $e->getMessage());
        }

        return null;
    }
}
