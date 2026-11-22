<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Resources\Website\StaticPageResource;
use App\Models\StaticPage;
use App\Traits\ApiResponseTrait;

class StaticPagesController extends Controller
{
    use ApiResponseTrait;
    public function index($slug)
    {
        $page = StaticPage::query()->where('slug', $slug)->where('status', 'active')->first();
        return $page
            ? $this->success(new StaticPageResource($page), 'تم جلب بيانات الصفحة بنجاح')
            : $this->error('الصفحة غير موجودة أو غير مفعلة', 404);
    }
}
