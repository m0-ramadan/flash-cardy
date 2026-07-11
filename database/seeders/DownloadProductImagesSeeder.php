<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadProductImagesSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->whereNotNull('image_path')
            ->where('image_path', 'like', 'http%')
            ->chunk(50, function ($products) {
                foreach ($products as $product) {
                    try {
                        $localPath = $this->downloadAndStoreImage($product->image_path);

                        if (!empty($localPath)) {
                            $oldImage = $product->image_path;

                            $product->image_path = $localPath;
                            $product->save();

                            $this->command?->info("Downloaded product #{$product->id}: {$oldImage} => {$localPath}");
                        } else {
                            $this->command?->warn("Skipped product #{$product->id}: failed to download image");
                        }
                    } catch (\Throwable $e) {
                        Log::error('Seeder download product image failed', [
                            'product_id' => $product->id,
                            'external_id' => $product->external_id,
                            'image_path' => $product->image_path,
                            'error' => $e->getMessage(),
                        ]);

                        $this->command?->error("Error product #{$product->id}: " . $e->getMessage());
                    }
                }
            });
    }

    private function downloadAndStoreImage(?string $imageUrl): ?string
    {
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout(60)->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = strtolower($extension ?: 'png');

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $extension = 'png';
            }

            $fileName = Str::random(24) . '.' . $extension;
            $relativePath = 'products/' . $fileName;

            Storage::disk('public')->put($relativePath, $response->body());

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error('downloadAndStoreImage failed in seeder', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
