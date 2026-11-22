<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::updateOrCreate(
            ['code' => 'ar'],
            [
                'name' => 'العربية',
                'image_path' => 'languages/flags/ar.png',
                'is_active' => true,
                'direction' => 'rtl',
                'sort_order' => 1,
                'is_default' => true,
            ]
        );
    }
}
