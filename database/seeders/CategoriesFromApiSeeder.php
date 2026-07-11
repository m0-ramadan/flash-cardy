<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoriesFromApiSeeder extends Seeder
{
    public function run()
    {
        $categories = Cache::remember('like4app_categories', now()->addHours(5), function () {

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
                throw new \Exception('Failed to fetch categories');
            }

            return $response->json('data');
        });

        foreach ($categories as $category) {
            $this->storeCategory($category);
        }
    }

    private function storeCategory(array $category, $parentId = null)
    {
        $imagePath = $this->downloadImage($category['amazonImage'] ?? null);

        $cat = Category::updateOrCreate(
            ['slug' => Str::slug($category['categoryName'])],
            [
                'name'        => $category['categoryName'],
                'parent_id'   => $parentId,
                'status_id'   => 1,
                'image'       => $imagePath,
            ]
        );

        if (!empty($category['childs']) && is_array($category['childs'])) {
            foreach ($category['childs'] as $child) {
                if (!empty($child['id'])) {
                    $this->storeCategory($child, $cat->id);
                }
            }
        }
    }

    private function downloadImage(?string $url): ?string
    {
        if (!$url) return null;

        $filename = 'categories/' . Str::random(20) . '.' . pathinfo($url, PATHINFO_EXTENSION);

        try {
            $image = Http::get($url)->body();
            Storage::disk('public')->put($filename, $image);
            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
