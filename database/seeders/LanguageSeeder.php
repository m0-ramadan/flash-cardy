<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'image_path' => 'images/en.png', 'is_active' => true]);
        Language::updateOrCreate(['code' => 'ar'], ['name' => 'العربية', 'image_path' => 'images/ar.png', 'is_active' => true]);
        Language::updateOrCreate(['code' => 'fr'], ['name' => 'Français', 'image_path' => 'images/fr.png', 'is_active' => true]);
    }
}
