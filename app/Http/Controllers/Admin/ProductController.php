<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use App\Models\Category;
use App\Models\Image;
use App\Models\Language;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductTextAd;
use App\Services\Fulfillment\LocalSerialFulfillmentService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ProductController extends Controller
{
    public function __construct(private LocalSerialFulfillmentService $localSerials) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): Factory|View
    {
        // Get statistics
        $totalProducts = Product::count();
        $activeProducts = Product::where('status_id', 1)->count();
        $inactiveProducts = Product::where('status_id', 2)->count();
        $lowStockProducts = Product::where('stock', '<', 10)->where('stock', '>', 0)->count();

        // Query products with filters
        $query = Product::with(['category', 'discount', 'primaryImage'])->withCount('reviews')
            ->sorted($request)
            ->filtered($request);

        // Apply search
        // if ($request->filled('search')) {
        //     $searchTerm = $request->get('search');
        //     $query->where(function ($q) use ($searchTerm) {
        //         $q->where('name', 'LIKE', "%{$searchTerm}%")
        //             //   ->orWhere('description', 'LIKE', "%{$searchTerm}%")
        //             // ->orWhere('sku', 'LIKE', "%{$searchTerm}%")
        //             // ->orWhereHas('category', function ($q) use ($searchTerm) {
        //             //     $q->where('name', 'LIKE', "%{$searchTerm}%");
        //             // })
        //         ;
        //     });
        // }
        if ($request->filled('search')) {
            $searchTerm = trim($request->get('search'));

            // اللغات المدعومة عندك
            $locales = config('translatable.locales', ['en', 'ar']); // عدّل حسب مشروعك

            $detected = $this->detectLocaleFromText($searchTerm, $locales);

            $query->where(function ($q) use ($searchTerm, $locales, $detected) {

                // لو عرفنا لغة (مثلا عربي/ياباني..) نبحث فيها فقط
                if ($detected) {
                    $q->where("name->$detected", 'LIKE', "%{$searchTerm}%");
                    return;
                }

                // لو ما عرفناش (خصوصًا اللاتيني) => نبحث في كل اللغات
                foreach ($locales as $loc) {
                    $q->orWhere("name->$loc", 'LIKE', "%{$searchTerm}%");
                }
            });
        }
        // Apply additional filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('price_from') && $request->filled('price_to')) {
            $query->whereBetween('price', [$request->price_from, $request->price_to]);
        } elseif ($request->filled('price_from')) {
            $query->where('price', '>=', $request->price_from);
        } elseif ($request->filled('price_to')) {
            $query->where('price', '<=', $request->price_to);
        }

        if ($request->filled('stock_from') && $request->filled('stock_to')) {
            $query->whereBetween('stock', [$request->stock_from, $request->stock_to]);
        } elseif ($request->filled('stock_from')) {
            $query->where('stock', '>=', $request->stock_from);
        } elseif ($request->filled('stock_to')) {
            $query->where('stock', '<=', $request->stock_to);
        }

        if ($request->filled('offer_id')) {
            $query->whereHas('offers', function ($q) use ($request) {
                $q->whereIn('offers.id', (array)$request->offer_id);
            });
        }

        // Get results with Pagination
        $perPage = $request->get('per_page', 40);
        $products = $query->paginate($perPage)->withQueryString();

        // Calculate average rating for each product
        foreach ($products as $product) {
            $product->average_rating = $product->reviews()->avg('rating') ?? 0;
            $product->final_price = $product->has_discount && $product->discount ?
                ($product->discount->discount_type === 'percentage' ?
                    $product->price - ($product->price * $product->discount->discount_value / 100) :
                    $product->price - $product->discount->discount_value) :
                $product->price;
        }

        // Get filter options
        $categories = Category::where('status_id', 1)->get();
        $offers = Offer::all();

        return view('Admin.product.index', compact(
            'products',
            'totalProducts',
            'activeProducts',
            'inactiveProducts',
            'lowStockProducts',
            'categories',
            'offers'
        ));
    }
    private function detectLocaleFromText(string $text, array $supportedLocales): ?string
    {
        $text = trim($text);
        if ($text === '') return null;

        // Arabic
        if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $text)) {
            return in_array('ar', $supportedLocales, true) ? 'ar' : null;
        }

        // Cyrillic (ru/uk/bg/...)
        if (preg_match('/[\x{0400}-\x{04FF}\x{0500}-\x{052F}]/u', $text)) {
            // اختار لغة افتراضية للسيريلك لو عندك
            foreach (['ru', 'uk', 'bg', 'sr'] as $loc) {
                if (in_array($loc, $supportedLocales, true)) return $loc;
            }
            return null;
        }

        // Chinese (Han)
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text)) {
            foreach (['zh', 'zh_CN', 'zh_TW'] as $loc) {
                if (in_array($loc, $supportedLocales, true)) return $loc;
            }
            return null;
        }

        // Japanese (Hiragana/Katakana)
        if (preg_match('/[\x{3040}-\x{30FF}]/u', $text)) {
            return in_array('ja', $supportedLocales, true) ? 'ja' : null;
        }

        // Korean (Hangul)
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) {
            return in_array('ko', $supportedLocales, true) ? 'ko' : null;
        }

        // Devanagari (hi)
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return in_array('hi', $supportedLocales, true) ? 'hi' : null;
        }

        // Latin: صعب نحدد لغة بدقة => null (يعني نبحث في كل اللغات)
        return null;
    }
    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $categories = Category::where('status_id', 1)->get();
        $offers = Offer::all();
        $languages = Language::where('is_active', true)->get();
        $autoSku = Product::generateUniqueSku();

        return view('Admin.product.create', compact(
            'categories',
            'offers',
            'languages',
            'autoSku'
        ));
    }

    /**
     * Store a newly created product with multi-language support.
     */
    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            // Get all active languages
            $languages = Language::where('is_active', true)->get();

            // =============================================================
            // 1. معالجة البيانات متعددة اللغات - باستخدام المصفوفات
            // =============================================================

            // تهيئة المصفوفات للبيانات متعددة اللغات
            $nameData = [];
            $descriptionData = [];
            $priceTextData = [];
            $metaTitleData = [];
            $metaDescriptionData = [];
            $metaKeywordsData = [];

            // جمع بيانات الاسم لكافة اللغات المرسلة
            if ($request->has('name') && is_array($request->name)) {
                foreach ($request->name as $locale => $val) {
                    if (!empty($val)) {
                        $nameData[$locale] = $val;
                    }
                }
            }

            // جمع بيانات الوصف
            if ($request->has('description') && is_array($request->description)) {
                foreach ($request->description as $locale => $val) {
                    if (!empty($val)) {
                        $descriptionData[$locale] = $val;
                    }
                }
            }

            // جمع بيانات نص السعر
            if ($request->has('price_text') && is_array($request->price_text)) {
                foreach ($request->price_text as $locale => $val) {
                    if (!empty($val)) {
                        $priceTextData[$locale] = $val;
                    }
                }
            }

            // إذا لم يتوفر نص السعر بالعربية، نضع قيمة افتراضية مناسبة
            if (empty($priceTextData['ar']) && $request->filled('price')) {
                $priceTextData['ar'] = $request->price . ' ' . ($request->sign_currency ?? 'ر.س');
            }

            // جمع بيانات SEO
            if ($request->has('meta_title') && is_array($request->meta_title)) {
                foreach ($request->meta_title as $locale => $val) {
                    if (!empty($val)) {
                        $metaTitleData[$locale] = $val;
                    }
                }
            }

            if ($request->has('meta_description') && is_array($request->meta_description)) {
                foreach ($request->meta_description as $locale => $val) {
                    if (!empty($val)) {
                        $metaDescriptionData[$locale] = $val;
                    }
                }
            }

            if ($request->has('meta_keywords') && is_array($request->meta_keywords)) {
                foreach ($request->meta_keywords as $locale => $val) {
                    if (!empty($val)) {
                        $metaKeywordsData[$locale] = $val;
                    }
                }
            }

            // ترجمة تلقائية للغات النشطة الأخرى إذا كانت فارغة ومطلوب ترجمتها
            if ($request->input('auto_translate', true) && !empty($nameData['ar'])) {
                foreach ($languages as $language) {
                    if ($language->code === 'ar' || isset($nameData[$language->code])) {
                        continue;
                    }

                    // ترجمة الاسم
                    $translatedName = $this->translateText($nameData['ar'], $language->code);
                    if ($translatedName) {
                        $nameData[$language->code] = $translatedName;
                    }

                    // ترجمة الوصف
                    if (!empty($descriptionData['ar'])) {
                        $translatedDescription = $this->translateText($descriptionData['ar'], $language->code);
                        if ($translatedDescription) {
                            $descriptionData[$language->code] = $translatedDescription;
                        }
                    }

                    // ترجمة نص السعر
                    if (!empty($priceTextData['ar'])) {
                        $translatedPriceText = $this->translateText($priceTextData['ar'], $language->code);
                        if ($translatedPriceText) {
                            $priceTextData[$language->code] = $translatedPriceText;
                        }
                    }

                    // ترجمة SEO
                    if (!empty($metaTitleData['ar'])) {
                        $translatedMetaTitle = $this->translateText($metaTitleData['ar'], $language->code);
                        if ($translatedMetaTitle) {
                            $metaTitleData[$language->code] = $translatedMetaTitle;
                        }
                    }

                    if (!empty($metaDescriptionData['ar'])) {
                        $translatedMetaDescription = $this->translateText($metaDescriptionData['ar'], $language->code);
                        if ($translatedMetaDescription) {
                            $metaDescriptionData[$language->code] = $translatedMetaDescription;
                        }
                    }

                    if (!empty($metaKeywordsData['ar'])) {
                        $translatedMetaKeywords = $this->translateText($metaKeywordsData['ar'], $language->code);
                        if ($translatedMetaKeywords) {
                            $metaKeywordsData[$language->code] = $translatedMetaKeywords;
                        }
                    }
                }
            }

            // =============================================================
            // 2. إنشاء المنتج
            // =============================================================

            $product = new Product();

            // البيانات متعددة اللغات
            $product->name = $nameData;
            $product->description = $descriptionData;
            $product->price_text = $priceTextData;
            $product->meta_title = $metaTitleData;
            $product->meta_description = $metaDescriptionData;
            $product->meta_keywords = $metaKeywordsData;

            // البيانات الأساسية
            $product->category_id = $request->category_id;
            $product->price = $request->price;
            $product->stock = $request->stock ?? 0;
            $product->status_id = $request->status_id;
            $product->has_discount = $request->boolean('has_discount');
            $product->includes_tax = $request->boolean('includes_tax');
            $product->includes_shipping = $request->boolean('includes_shipping');
            $product->sku = filled($request->sku) ? trim((string)$request->sku) : Product::generateUniqueSku();
            $this->applyOperationalFields($product, $request);
            if ($request->filled('sign_currency')) {
                $product->sign_currency = $request->sign_currency;
            }

            // إضافة created_by إذا كان موجوداً
            if (auth('admin')->check()) {
                $product->created_by = auth('admin')->id();
            }

            $product->save();

            // =============================================================
            // 3. معالجة الصورة الرئيسية
            // =============================================================

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $product->update(['image_path' => $path]);

                $product->images()->create([
                    'path' => $path,
                    'type' => 'main',
                    'is_primary' => 1,
                    'order' => 1,
                    // 'alt'=>
                    'is_active' => 1,
                ]);
            }

            // =============================================================
            // 4. معالجة الخصم
            // =============================================================

            if ($request->boolean('has_discount') && $request->filled('discount_value')) {
                $product->discount()->create([
                    'discount_value' => $request->discount_value,
                    'discount_type' => $request->input('discount_type', 'percentage'),
                    'is_active' => 1,
                ]);
            }

            // =============================================================
            // 5. معالجة العروض
            // =============================================================

            if ($request->filled('offers')) {
                $product->offers()->sync($request->offers);
            }

            // =============================================================
            // 6. معالجة النصوص الإعلانية - مع دعم متعدد اللغات
            // =============================================================

            if ($request->filled('text_ads')) {
                foreach ($request->text_ads as $index => $ad) {
                    // التأكد من وجود النص العربي على الأقل
                    if (isset($ad['name']) && !empty($ad['name'])) {
                        $textAdData = ['ar' => $ad['name']];

                        // إضافة الترجمات الأخرى
                        foreach ($languages as $language) {
                            if ($language->code === 'ar') {
                                continue;
                            }

                            // البحث عن النص المترجم في المصفوفة
                            if (isset($ad[$language->code]) && !empty($ad[$language->code])) {
                                $textAdData[$language->code] = $ad[$language->code];
                            } elseif ($request->input('auto_translate', true)) {
                                // ترجمة تلقائية إذا لم يكن النص موجوداً
                                $translatedAd = $this->translateText($ad['name'], $language->code);
                                if ($translatedAd) {
                                    $textAdData[$language->code] = $translatedAd;
                                }
                            }
                        }

                        $product->adsText()->create([
                            'name' => $textAdData,
                        ]);
                    }
                }
            }

            // =============================================================
            // 7. معالجة الصور الإضافية
            // =============================================================

            if ($request->hasFile('additional_images')) {
                $order = 1; // الصور الإضافية تبدأ من 1

                foreach ($request->file('additional_images') as $image) {
                    $path = $image->store('products/additional', 'public');

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => false,
                        'type' => 'additional',
                        'order' => $order++,
                    ]);
                }
            }

            // Initial local voucher inventory can be entered while the product is created.
            if ($product->fulfillment_source === 'local_serials' && !empty($request->input('serials', []))) {
                $this->localSerials->addCodes(
                    $product->id,
                    (array) $request->input('serials', []),
                    auth('admin')->id(),
                    'product_form'
                );
            }

            DB::commit();

            // =============================================================
            // 8. تسجيل النشاط
            // =============================================================

            if (function_exists('activity') && auth()->check()) {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($product)
                    ->withProperties([
                        'attributes' => $product->toArray(),
                        'ip' => request()->ip()
                    ])
                    ->log('تم إنشاء منتج جديد');
            }

            // رسالة نجاح مع عدد اللغات المضافة
            $languagesCount = count(array_filter($nameData, function ($key) {
                return $key !== 'ar';
            }, ARRAY_FILTER_USE_KEY)) + 1; // +1 للعربية

            return redirect()
                ->route('admin.products.show', $product->id)
                ->with('success', "تم إنشاء المنتج بنجاح بـ {$languagesCount} لغات");
        } catch (\Exception $e) {
            DB::rollBack();

            // تسجيل الخطأ
            Log::error('خطأ في إنشاء المنتج', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token', '_method', 'image', 'additional_images'])
            ]);

            // حذف الصور المرفوعة إذا فشلت العملية
            if (isset($product) && $product->exists) {
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                foreach ($product->images as $image) {
                    if (Storage::disk('public')->exists($image->path)) {
                        Storage::disk('public')->delete($image->path);
                    }
                }
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء المنتج: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة للترجمة
     */
    private function translateText($text, $targetLang)
    {
        if (empty($text)) {
            return null;
        }

        return $text;
    }

    /**
     * دالة للتحقق من صحة البيانات قبل الحفظ
     */
    private function validateProductData(Request $request)
    {
        $rules = [
            'name' => 'required|array',
            'name.ar' => 'required|string|max:255',
            'name.*' => 'nullable|string|max:255',

            'description' => 'nullable|array',
            'description.*' => 'nullable|string',

            'price_text' => 'required|array',
            'price_text.ar' => 'required|string|max:255',
            'price_text.*' => 'nullable|string|max:255',

            'meta_title' => 'nullable|array',
            'meta_title.*' => 'nullable|string|max:255',

            'meta_description' => 'nullable|array',
            'meta_description.*' => 'nullable|string|max:500',

            'meta_keywords' => 'nullable|array',
            'meta_keywords.*' => 'nullable|string|max:255',

            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'status_id' => 'required|in:1,2,3',
            'sku' => 'nullable|string|max:100|unique:products,sku',

            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',

            'has_discount' => 'nullable|boolean',
            'discount_type' => 'nullable|required_if:has_discount,1|in:percentage,fixed',
            'discount_value' => 'nullable|required_if:has_discount,1|numeric|min:0',

            'includes_tax' => 'nullable|boolean',

            'offers' => 'nullable|array',
            'offers.*' => 'exists:offers,id',

            'text_ads' => 'nullable|array',
            'text_ads.*.name' => 'nullable|string|max:500',
        ];

        // إضافة قواعد للغات الأخرى في النصوص الإعلانية
        $languages = Language::where('is_active', true)->where('code', '!=', 'ar')->get();
        foreach ($languages as $language) {
            $rules["text_ads.*.{$language->code}"] = 'nullable|string|max:500';
        }

        $rules['auto_translate'] = 'nullable|boolean';

        return $request->validate($rules);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, $id)
    {
        $product = Product::with(['category', 'images', 'reviews.user', 'offers', 'adsText'])->findOrFail($id);
        $languages = Language::where('is_active', true)->get();

        // تحديث حالات الأكواد المنتهية تلقائياً
        if (isset($this->localSerials)) {
            $this->localSerials->refreshExpiredStatuses($product->id);
        }

        // استعلام الأكواد التسلسلية مع التصفية والبحث والـ Pagination
        $serialsQuery = $product->serials()->with(['order', 'orderItem', 'creator'])->latest();

        if ($request->filled('serial_status')) {
            $serialsQuery->where('status', $request->serial_status);
        }

        if ($request->filled('serial_expires')) {
            if ($request->serial_expires === 'expired') {
                $serialsQuery->whereNotNull('expires_at')->where('expires_at', '<=', now());
            } elseif ($request->serial_expires === 'soon') {
                $serialsQuery->whereBetween('expires_at', [now(), now()->addDays(30)]);
            }
        }

        if ($request->filled('serial_search')) {
            $search = trim($request->serial_search);
            $searchHash = ProductSerial::hashCode($search);
            $serialsQuery->where(function ($q) use ($search, $searchHash) {
                $q->where('id', $search)
                    ->orWhere('code_hash', $searchHash)
                    ->orWhere('batch_reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        $serials = $serialsQuery->paginate(20, ['*'], 'serials_page')->withQueryString();

        $serialStats = [
            'total' => $product->serials()->count(),
            'available' => $product->serials()->usable()->count(),
            'assigned' => $product->serials()->whereIn('status', [ProductSerial::ASSIGNED, ProductSerial::DELIVERED])->count(),
            'expired' => $product->serials()->where('status', ProductSerial::EXPIRED)->count(),
            'disabled' => $product->serials()->where('status', ProductSerial::DISABLED)->count(),
        ];

        return view('Admin.product.show', compact('product', 'languages', 'serials', 'serialStats'));
    }

    /**
     * Show the form for editing the product.
     */
    public function edit($id)
    {
        $product = Product::with(['category', 'offers', 'images', 'discount', 'adsText'])->findOrFail($id);
        $categories = Category::where('status_id', 1)->get();
        $offers = Offer::all();
        $languages = Language::where('is_active', true)->get();

        return view('Admin.product.edit', compact(
            'product',
            'categories',
            'offers',
            'languages'
        ));
    }

    /**
     * Update the specified product.
     */
    /**
     * Update the specified product.
     */
    public function update(StoreProductRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::with([
                'offers',
                'discount',
                'images',
                'adsText'
            ])->findOrFail($id);

            // Get all active languages
            $languages = Language::where('is_active', true)->get();

            // =============================================================
            // 1. معالجة البيانات متعددة اللغات - باستخدام المصفوفات
            // =============================================================

            // الحصول على البيانات الحالية (للاحتفاظ بالترجمات غير المرسلة)
            $currentName = json_decode($product->getRawOriginal('name'), true) ?? [];
            $currentDescription = json_decode($product->getRawOriginal('description'), true) ?? [];
            $currentPriceText = json_decode($product->getRawOriginal('price_text'), true) ?? [];
            $currentMetaTitle = json_decode($product->getRawOriginal('meta_title'), true) ?? [];
            $currentMetaDescription = json_decode($product->getRawOriginal('meta_description'), true) ?? [];
            $currentMetaKeywords = json_decode($product->getRawOriginal('meta_keywords'), true) ?? [];

            // تهيئة المصفوفات الجديدة
            $nameData = [];
            $descriptionData = [];
            $priceTextData = [];
            $metaTitleData = [];
            $metaDescriptionData = [];
            $metaKeywordsData = [];

            // معالجة اللغة العربية (إلزامية)
            if ($request->has('name') && isset($request->name['ar'])) {
                $nameData['ar'] = $request->name['ar'];
            } elseif (isset($currentName['ar'])) {
                $nameData['ar'] = $currentName['ar'];
            }

            if ($request->has('description') && isset($request->description['ar'])) {
                $descriptionData['ar'] = $request->description['ar'];
            } elseif (isset($currentDescription['ar'])) {
                $descriptionData['ar'] = $currentDescription['ar'];
            }

            if ($request->has('price_text') && isset($request->price_text['ar'])) {
                $priceTextData['ar'] = $request->price_text['ar'];
            } elseif (isset($currentPriceText['ar'])) {
                $priceTextData['ar'] = $currentPriceText['ar'];
            }

            if ($request->has('meta_title') && isset($request->meta_title['ar'])) {
                $metaTitleData['ar'] = $request->meta_title['ar'];
            } elseif (isset($currentMetaTitle['ar'])) {
                $metaTitleData['ar'] = $currentMetaTitle['ar'];
            }

            if ($request->has('meta_description') && isset($request->meta_description['ar'])) {
                $metaDescriptionData['ar'] = $request->meta_description['ar'];
            } elseif (isset($currentMetaDescription['ar'])) {
                $metaDescriptionData['ar'] = $currentMetaDescription['ar'];
            }

            if ($request->has('meta_keywords') && isset($request->meta_keywords['ar'])) {
                $metaKeywordsData['ar'] = $request->meta_keywords['ar'];
            } elseif (isset($currentMetaKeywords['ar'])) {
                $metaKeywordsData['ar'] = $currentMetaKeywords['ar'];
            }

            // معالجة باقي اللغات
            foreach ($languages as $language) {
                if ($language->code === 'ar') {
                    continue;
                }

                // الاسم
                if ($request->has('name') && isset($request->name[$language->code]) && !empty($request->name[$language->code])) {
                    $nameData[$language->code] = $request->name[$language->code];
                } elseif (isset($currentName[$language->code]) && !empty($currentName[$language->code])) {
                    $nameData[$language->code] = $currentName[$language->code];
                }

                // الوصف
                if ($request->has('description') && isset($request->description[$language->code]) && !empty($request->description[$language->code])) {
                    $descriptionData[$language->code] = $request->description[$language->code];
                } elseif (isset($currentDescription[$language->code]) && !empty($currentDescription[$language->code])) {
                    $descriptionData[$language->code] = $currentDescription[$language->code];
                }

                // نص السعر
                if ($request->has('price_text') && isset($request->price_text[$language->code]) && !empty($request->price_text[$language->code])) {
                    $priceTextData[$language->code] = $request->price_text[$language->code];
                } elseif (isset($currentPriceText[$language->code]) && !empty($currentPriceText[$language->code])) {
                    $priceTextData[$language->code] = $currentPriceText[$language->code];
                }

                // meta title
                if ($request->has('meta_title') && isset($request->meta_title[$language->code]) && !empty($request->meta_title[$language->code])) {
                    $metaTitleData[$language->code] = $request->meta_title[$language->code];
                } elseif (isset($currentMetaTitle[$language->code]) && !empty($currentMetaTitle[$language->code])) {
                    $metaTitleData[$language->code] = $currentMetaTitle[$language->code];
                }

                // meta description
                if ($request->has('meta_description') && isset($request->meta_description[$language->code]) && !empty($request->meta_description[$language->code])) {
                    $metaDescriptionData[$language->code] = $request->meta_description[$language->code];
                } elseif (isset($currentMetaDescription[$language->code]) && !empty($currentMetaDescription[$language->code])) {
                    $metaDescriptionData[$language->code] = $currentMetaDescription[$language->code];
                }

                // meta keywords
                if ($request->has('meta_keywords') && isset($request->meta_keywords[$language->code]) && !empty($request->meta_keywords[$language->code])) {
                    $metaKeywordsData[$language->code] = $request->meta_keywords[$language->code];
                } elseif (isset($currentMetaKeywords[$language->code]) && !empty($currentMetaKeywords[$language->code])) {
                    $metaKeywordsData[$language->code] = $currentMetaKeywords[$language->code];
                }
            }

            // =============================================================
            // 2. تحديث بيانات المنتج الأساسية
            // =============================================================

            $product->update([
                'name' => $nameData,
                'description' => $descriptionData,
                'price_text' => $priceTextData,
                'meta_title' => $metaTitleData,
                'meta_description' => $metaDescriptionData,
                'meta_keywords' => $metaKeywordsData,

                'category_id' => $request->category_id,
                'price' => $request->price,
                'stock' => $request->stock ?? 0,
                'status_id' => $request->status_id,
                'has_discount' => $request->boolean('has_discount'),
                'includes_tax' => $request->boolean('includes_tax'),
                'includes_shipping' => $request->boolean('includes_shipping'),
                'sku' => filled($request->sku) ? trim((string)$request->sku) : ($product->sku ?: Product::generateUniqueSku()),
                'sign_currency' => $request->input('sign_currency', $product->sign_currency ?? 'EGP'),
            ]);

            $this->applyOperationalFields($product, $request);
            $product->save();

            if (auth('admin')->check()) {
                $product->updated_by = auth('admin')->id();
                $product->save();
            }

            // =============================================================
            // 3. معالجة الصورة الرئيسية
            // =============================================================

            // حذف الصورة إذا طلب ذلك
            if ($request->has('delete_image') && $request->delete_image) {
                // Keep historical media rows/files because paid order snapshots may reference them.
                $product->images()->where('type', 'main')->update(['is_primary' => 0, 'is_active' => 0]);
                $product->update(['image_path' => null]);
            }

            // رفع صورة جديدة
            if ($request->hasFile('image')) {
                $product->images()->where('type', 'main')->update(['is_primary' => 0]);

                $path = $request->file('image')->store('products', 'public');
                $product->update(['image_path' => $path]);

                $product->images()->create([
                    'path' => $path,
                    'type' => 'main',
                    'is_primary' => 1,
                    'order' => 1,
                    'is_active' => 1,
                ]);
            }

            // =============================================================
            // 4. معالجة الخصم
            // =============================================================

            if ($request->boolean('has_discount') && $request->filled('discount_value')) {
                $product->discount()->updateOrCreate(
                    [],
                    [
                        'discount_value' => $request->discount_value,
                        'discount_type' => $request->input('discount_type', 'percentage'),
                        'is_active' => 1,
                    ]
                );
            } else {
                $product->discount()?->delete();
            }

            // =============================================================
            // 5. معالجة العروض
            // =============================================================

            if ($request->filled('offers')) {
                $product->offers()->sync($request->offers);
            } else {
                $product->offers()->detach();
            }

            // =============================================================
            // 6. معالجة النصوص الإعلانية - مع دعم متعدد اللغات
            // =============================================================

            // حذف النصوص الإعلانية القديمة
            $product->adsText()->delete();

            if ($request->filled('text_ads')) {
                foreach ($request->text_ads as $index => $ad) {
                    // التأكد من وجود النص العربي على الأقل
                    if (isset($ad['name']) && !empty($ad['name'])) {
                        $textAdData = ['ar' => $ad['name']];

                        // إضافة الترجمات الأخرى
                        foreach ($languages as $language) {
                            if ($language->code === 'ar') {
                                continue;
                            }

                            // البحث عن النص المترجم في المصفوفة
                            if (isset($ad[$language->code]) && !empty($ad[$language->code])) {
                                $textAdData[$language->code] = $ad[$language->code];
                            } elseif ($request->input('auto_translate', true)) {
                                $translatedAd = $this->translateText($ad['name'], $language->code);
                                if ($translatedAd) {
                                    $textAdData[$language->code] = $translatedAd;
                                }
                            }
                        }

                        $product->adsText()->create([
                            'name' => $textAdData
                        ]);
                    }
                }
            }

            // =============================================================
            // 7. معالجة الصور الإضافية
            // =============================================================

            // إضافة صور جديدة
            if ($request->hasFile('additional_images')) {
                $order = $product->images()->where('type', 'additional')->max('order') ?? 0;

                foreach ($request->file('additional_images') as $image) {
                    $path = $image->store('products/additional', 'public');

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => false,
                        'type' => 'additional',
                        'order' => ++$order,
                    ]);
                }
            }

            // إزالة الصور المحددة للحذف
            if ($request->filled('removed_images')) {
                $removedIds = explode(',', $request->removed_images);
                foreach ($removedIds as $imageId) {
                    if (!empty($imageId)) {
                        $image = \App\Models\Image::find($imageId);
                        if ($image && $image->imageable_id === $product->id && $image->imageable_type === Product::class) {
                            Storage::disk('public')->delete($image->path);
                            $image->delete();
                        }
                    }
                }
            }

            if ($product->fulfillment_source === 'local_serials' && !empty($request->input('serials', []))) {
                $this->localSerials->addCodes(
                    $product->id,
                    (array) $request->input('serials', []),
                    auth('admin')->id(),
                    'product_form'
                );
            }

            DB::commit();

            // تسجيل النشاط
            if (function_exists('activity') && auth()->check()) {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($product)
                    ->withProperties([
                        'changes' => $product->getChanges(),
                        'ip' => request()->ip()
                    ])
                    ->log('تم تحديث المنتج');
            }

            // رسالة نجاح مع عدد اللغات المحدثة
            $updatedLanguages = count(array_filter([
                !empty($nameData) ? 'ar' : null,
                ...array_keys(array_filter($nameData, fn($key) => $key !== 'ar'))
            ]));

            return redirect()
                ->route('admin.products.show', $product->id)
                ->with('success', "تم تحديث المنتج بنجاح في {$updatedLanguages} لغات");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في تحديث المنتج', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_id' => $id,
                'request_data' => $request->except(['_token', '_method'])
            ]);

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            // Product removal is intentionally soft. Media and historical snapshots must
            // remain available for old paid orders and issued voucher records.
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنتج بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المنتج'
            ], 500);
        }
    }

    /**
     * Bulk delete products.
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids || !is_array($ids)) {
            return back()->with('error', 'لم يتم تحديد أي منتجات');
        }

        try {
            $products = Product::whereIn('id', $ids)->get();

            foreach ($products as $product) {
                $product->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنتجات المحددة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk deleting products: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المنتجات'
            ], 500);
        }
    }

    /**
     * Quick add functionality
     */
    public function quickAdd(Request $request, $type)
    {
        try {
            switch ($type) {
                case 'offer':
                    $request->validate([
                        'name' => 'required|string|max:255'
                    ]);

                    $offer = Offer::create([
                        'name' => $request->name
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'تم إضافة العرض بنجاح',
                        'data' => $offer
                    ]);

                case 'category':
                    $request->validate([
                        'name' => 'required|string|max:255',
                        'parent_id' => 'nullable|exists:categories,id'
                    ]);

                    $category = Category::create([
                        'name' => $request->name,
                        'parent_id' => $request->parent_id,
                        'status_id' => 1
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'تم إضافة القسم بنجاح',
                        'data' => $category
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'النوع غير معروف'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete image
     */
    public function deleteImage($productId, $imageId)
    {
        try {
            $image = \App\Models\Image::where('imageable_id', $productId)
                ->where('imageable_type', Product::class)
                ->where('id', $imageId)
                ->firstOrFail();

            Storage::disk('public')->delete($image->path);
            $image->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error deleting image: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الصورة'
            ], 500);
        }
    }

    /**
     * Update product image
     */
    public function updateImage(Request $request, Product $product)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            // Keep old media records/files for historical order snapshots. The previous
            // primary image is simply demoted and the new one becomes primary.
            $product->images()->update(['is_primary' => 0]);

            // Save new image
            $imagePath = $request->file('image')->store('products', 'public');

            // Create new image record
            $image = new \App\Models\Image();
            $image->imageable_id = $product->id;
            $image->imageable_type = Product::class;
            $image->path = $imagePath;
            $image->is_primary = 1;
            $image->type = 'main';
            $image->save();

            // Update product image reference
            $product->update(['image_path' => $imagePath]);

            $imageUrl = asset('storage/' . $imagePath);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث صورة المنتج بنجاح',
                'image_url' => $imageUrl,
                'image_id' => $image->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update product image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث الصورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk product actions used by the dashboard. Every action keeps historical order/media data intact.
     */
    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:activate,deactivate,draft,delete,restore',
        ]);

        $query = Product::withTrashed()->whereIn('id', $data['ids']);
        $count = 0;
        DB::transaction(function () use ($query, $data, &$count) {
            $products = $query->lockForUpdate()->get();
            foreach ($products as $product) {
                match ($data['action']) {
                    'activate' => $product->forceFill(['status_id' => 1])->save(),
                    'deactivate' => $product->forceFill(['status_id' => 2])->save(),
                    'draft' => $product->forceFill(['status_id' => 3])->save(),
                    'delete' => $product->trashed() ?: $product->delete(),
                    'restore' => $product->trashed() ? $product->restore() : true,
                };
                $count++;
            }
        }, 3);

        return response()->json(['success' => true, 'message' => "تم تنفيذ الإجراء على {$count} منتج."]);
    }

    public function toggleStatus($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        abort_if($product->trashed(), 422, 'استرجع المنتج أولاً قبل تغيير حالته.');
        $product->forceFill(['status_id' => (int) $product->status_id === 1 ? 2 : 1, 'updated_by' => auth('admin')->id()])->save();

        return response()->json(['success' => true, 'status_id' => $product->status_id]);
    }

    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        if ($product->trashed()) $product->restore();
        return back()->with('success', 'تم استرجاع المنتج بدون فقد أي بيانات أو أكواد تاريخية.');
    }

    public function duplicate(Product $product)
    {
        $copy = DB::transaction(function () use ($product) {
            $copy = $product->replicate(['external_id', 'reserved_stock', 'sold_stock']);
            $baseSku = trim((string) $product->sku) ?: 'PRODUCT-' . $product->id;
            $copy->sku = Str::limit($baseSku . '-COPY-' . strtoupper(Str::random(5)), 100, '');
            $copy->status_id = 3;
            $copy->stock = 0;
            $copy->reserved_stock = 0;
            $copy->sold_stock = 0;
            $copy->external_id = null;
            $copy->fulfillment_source = $product->fulfillment_source === 'like4app' ? 'none' : $product->fulfillment_source;
            $copy->created_by = auth('admin')->id();
            $copy->updated_by = null;
            $copy->save();

            $copy->offers()->sync($product->offers()->pluck('offers.id')->all());
            if ($product->discount) {
                $copy->discount()->create($product->discount->only(['discount_value', 'discount_type', 'is_active']));
            }
            foreach ($product->images as $image) {
                $copy->images()->create($image->only(['path', 'type', 'is_primary', 'order', 'is_active']));
            }
            // Serial codes are intentionally NEVER duplicated.
            return $copy;
        }, 3);

        return redirect()->route('admin.products.edit', $copy->id)
            ->with('success', 'تم نسخ المنتج كمسودة. لم يتم نسخ الأكواد التسلسلية أو ربط Like4App لتجنب التكرار.');
    }

    public function export()
    {
        $filename = 'products-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'SKU', 'Name', 'Category', 'Price', 'Currency', 'Status', 'Type', 'Fulfillment', 'Mode', 'Available Serials']);
            Product::withTrashed()->with('category')->withCount(['serials as available_serials_count' => fn($q) => $q->usable()])
                ->orderBy('id')->chunk(500, function ($products) use ($out) {
                    foreach ($products as $product) {
                        $safe = fn($value) => preg_match('/^[=+\-@]/', (string)$value) ? "'" . $value : $value;
                        fputcsv($out, [
                            $product->id,
                            $safe($product->sku),
                            $safe($product->getTranslation('name', 'ar', false)),
                            $safe($product->category?->getTranslation('name', 'ar', false) ?? ''),
                            $product->price,
                            $product->sign_currency,
                            $product->status_id,
                            $product->type,
                            $product->fulfillment_source,
                            $product->fulfillment_mode,
                            $product->available_serials_count,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function applyOperationalFields(Product $product, StoreProductRequest $request): void
    {
        $source = (string) $request->input('fulfillment_source', $product->fulfillment_source ?: 'none');
        $mode = (string) $request->input('fulfillment_mode', $product->fulfillment_mode ?: 'manual');

        // Backward compatibility: an existing Like4App external id still means remote fulfilment
        // unless the administrator explicitly selected another source.
        if (!$request->has('fulfillment_source') && $request->filled('external_id')) {
            $source = 'like4app';
            $mode = 'automatic';
        }

        $digital = in_array($source, ['local_serials', 'like4app'], true);
        $product->fill([
            'subtitle' => $request->input('subtitle'),
            'brand' => $request->input('brand'),
            'region' => $request->input('region'),
            'denomination' => $request->input('denomination'),
            'validity_text' => $request->input('validity_text'),
            'is_featured' => $request->boolean('is_featured'),
            'is_best_seller' => $request->boolean('is_best_seller'),
            'type' => $request->input('type', $digital ? 'digital_code' : ($product->type ?: 'product')),
            'inventory_type' => $digital ? 'digital' : $request->input('inventory_type', $product->inventory_type ?: 'digital'),
            'track_stock' => $digital ? false : $request->boolean('track_stock', true),
            'max_quantity' => (int) $request->input('max_quantity', $product->max_quantity ?: 0),
            'instructions' => $request->input('instructions'),
            'terms' => $request->input('terms'),
            'url' => $request->input('url'),
            'external_id' => $request->input('external_id'),
            'fulfillment_source' => $source,
            'fulfillment_mode' => $mode,
            'allow_serial_backorder' => $request->boolean('allow_serial_backorder', true),
        ]);
        if ($digital) {
            $product->track_stock = false;
            $product->reserved_stock = 0;
        }
    }
    // ProductController.php - إضافة هذه الطرق
    /**
     * تحسين النص باستخدام الذكاء الاصطناعي
     */
    public function enhanceWithAI(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'type' => 'required|in:title,description,price_text,meta_title,meta_description,meta_keywords,text_ad',
            'action' => 'required|in:enhance,complete,seo,add_features,add_benefits,add_call_to_action',
            'tone' => 'nullable|in:neutral,friendly,professional,enthusiastic,persuasive',
            'style' => 'nullable|in:formal,simplified,seo,creative',
            'product_id' => 'nullable|exists:products,id'
        ]);

        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'مفتاح API غير متوفر'
            ], 500);
        }

        try {
            $prompt = $this->buildEnhancementPrompt(
                $request->text,
                $request->type,
                $request->action,
                $request->tone,
                $request->style,
                $request->product_id
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مساعد ذكي متخصص في تحسين نصوص المنتجات العربية. قم بتحسين النص مع الحفاظ على معناه الأصلي.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $enhancedText = $result['choices'][0]['message']['content'] ?? null;

                if ($enhancedText) {
                    return response()->json([
                        'success' => true,
                        'enhanced_text' => trim($enhancedText)
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في تحسين النص'
            ], 500);
        } catch (\Exception $e) {
            Log::error('AI enhancement error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الاتصال بالذكاء الاصطناعي'
            ], 500);
        }
    }

    /**
     * إنشاء SEO بالذكاء الاصطناعي
     */
    public function generateSEOWithAI(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'tone' => 'nullable|in:neutral,friendly,professional,enthusiastic,persuasive',
            'style' => 'nullable|in:formal,simplified,seo,creative'
        ]);

        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'مفتاح API غير متوفر'
            ], 500);
        }

        try {
            $prompt = $this->buildSEOPrompt(
                $request->title,
                $request->description,
                $request->category,
                $request->tone,
                $request->style
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت متخصص في تحسين محركات البحث (SEO) للمنتجات العربية. قم بإنشاء محتوى SEO محسن.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $seoContent = $result['choices'][0]['message']['content'] ?? null;

                if ($seoContent) {
                    $parsedSEO = $this->parseSEOContent($seoContent);

                    // الحصول على الترجمات
                    $languages = Language::where('is_active', true)->where('code', '!=', 'ar')->get();
                    $translations = [
                        'meta_title' => ['ar' => $parsedSEO['meta_title']],
                        'meta_description' => ['ar' => $parsedSEO['meta_description']],
                        'meta_keywords' => ['ar' => $parsedSEO['meta_keywords']]
                    ];

                    foreach ($languages as $language) {
                        $translatedSEO = $this->translateSEO($parsedSEO, $language->code, $request->style, $request->tone);
                        if ($translatedSEO) {
                            $translations['meta_title'][$language->code] = $translatedSEO['meta_title'] ?? '';
                            $translations['meta_description'][$language->code] = $translatedSEO['meta_description'] ?? '';
                            $translations['meta_keywords'][$language->code] = $translatedSEO['meta_keywords'] ?? '';
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $parsedSEO,
                        'translations' => $translations
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء SEO'
            ], 500);
        } catch (\Exception $e) {
            Log::error('AI SEO generation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء SEO'
            ], 500);
        }
    }

    /**
     * إنشاء نصوص إعلانية بالذكاء الاصطناعي
     */
    public function generateTextAdsWithAI(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'price_text' => 'nullable|string',
            'tone' => 'nullable|in:neutral,friendly,professional,enthusiastic,persuasive',
            'style' => 'nullable|in:formal,simplified,seo,creative'
        ]);

        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'مفتاح API غير متوفر'
            ], 500);
        }

        try {
            $prompt = $this->buildTextAdsPrompt(
                $request->title,
                $request->description,
                $request->price,
                $request->price_text,
                $request->tone,
                $request->style
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت كاتب إعلانات محترف متخصص في كتابة نصوص إعلانية جذابة للمنتجات العربية.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.8,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $textAdsContent = $result['choices'][0]['message']['content'] ?? null;

                if ($textAdsContent) {
                    $parsedTextAds = $this->parseTextAdsContent($textAdsContent);

                    return response()->json([
                        'success' => true,
                        'text_ads' => $parsedTextAds,
                        'count' => count($parsedTextAds)
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء النصوص الإعلانية'
            ], 500);
        } catch (\Exception $e) {
            Log::error('AI text ads generation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء النصوص الإعلانية'
            ], 500);
        }
    }

    /**
     * الترجمة بالذكاء الاصطناعي
     */
    public function translateWithAI(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_text' => 'nullable|string',
            'target_lang' => 'required|string',
            'style' => 'nullable|in:formal,simplified,seo,creative',
            'tone' => 'nullable|in:neutral,friendly,professional,enthusiastic,persuasive'
        ]);

        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'مفتاح API غير متوفر'
            ], 500);
        }

        try {
            $prompt = $this->buildTranslationPrompt(
                $request->title,
                $request->description,
                $request->price_text,
                $request->target_lang,
                $request->style,
                $request->tone
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مترجم محترف متخصص في ترجمة نصوص المنتجات.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 3000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $translatedContent = $result['choices'][0]['message']['content'] ?? null;

                if ($translatedContent) {
                    $parsedTranslation = $this->parseTranslationContent($translatedContent);

                    return response()->json([
                        'success' => true,
                        'translated' => $parsedTranslation
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في الترجمة'
            ], 500);
        } catch (\Exception $e) {
            Log::error('AI translation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الترجمة'
            ], 500);
        }
    }

    /**
     * تحسين المنتج كاملاً بالذكاء الاصطناعي
     */
    public function enhanceFullProductWithAI(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'current_description' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'tone' => 'nullable|in:neutral,friendly,professional,enthusiastic,persuasive',
            'style' => 'nullable|in:formal,simplified,seo,creative',
            'generate_all' => 'nullable|boolean'
        ]);

        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'مفتاح API غير متوفر'
            ], 500);
        }

        try {
            $category = Category::find($request->category_id);
            $product = $request->product_id ? Product::find($request->product_id) : null;

            $prompt = $this->buildFullEnhancementPrompt(
                $request->title,
                $category->name,
                $request->current_description,
                $product,
                $request->tone,
                $request->style
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت متخصص في تحسين المنتجات وتسويقها. قم بتحسين جميع جوانب المنتج ليكون أكثر جاذبية وفعالية.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 4000,
                'temperature' => 0.8,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $enhancedContent = $result['choices'][0]['message']['content'] ?? null;

                if ($enhancedContent) {
                    $parsedProduct = $this->parseEnhancedProduct($enhancedContent);

                    // الحصول على الترجمات إذا طلب ذلك
                    $translations = null;
                    if ($request->boolean('generate_all')) {
                        $languages = Language::where('is_active', true)->where('code', '!=', 'ar')->get();
                        $translations = [
                            'title' => ['ar' => $parsedProduct['title']],
                            'description' => ['ar' => $parsedProduct['description']],
                            'price_text' => ['ar' => $parsedProduct['price_text']]
                        ];

                        foreach ($languages as $language) {
                            $translatedData = $this->translateProductData($parsedProduct, $language->code, $request->style, $request->tone);
                            if ($translatedData) {
                                $translations['title'][$language->code] = $translatedData['title'] ?? '';
                                $translations['description'][$language->code] = $translatedData['description'] ?? '';
                                $translations['price_text'][$language->code] = $translatedData['price_text'] ?? '';
                            }
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $parsedProduct,
                        'translations' => $translations
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في تحسين المنتج'
            ], 500);
        } catch (\Exception $e) {
            Log::error('AI full enhancement error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحسين المنتج'
            ], 500);
        }
    }

// =================================================================
// دوال مساعدة للـ Prompts
// =================================================================

    /**
     * بناء Prompt للتحسين
     */
    private function buildEnhancementPrompt($text, $type, $action, $tone, $style, $productId = null)
    {
        $typeMap = [
            'title' => 'العنوان',
            'description' => 'الوصف',
            'price_text' => 'نص السعر',
            'meta_title' => 'عنوان SEO',
            'meta_description' => 'وصف SEO',
            'meta_keywords' => 'الكلمات المفتاحية',
            'text_ad' => 'النص الإعلاني'
        ];

        $actionMap = [
            'enhance' => 'تحسين',
            'complete' => 'إكمال',
            'seo' => 'تحسين SEO',
            'add_features' => 'إضافة مميزات',
            'add_benefits' => 'إضافة فوائد',
            'add_call_to_action' => 'إضافة دعوة للعمل'
        ];

        $toneMap = [
            'neutral' => 'محايدة',
            'friendly' => 'ودودة',
            'professional' => 'مهنية',
            'enthusiastic' => 'متحمسة',
            'persuasive' => 'مقنعة'
        ];

        $styleMap = [
            'formal' => 'رسمية',
            'simplified' => 'مبسطة',
            'seo' => 'محسنة لمحركات البحث',
            'creative' => 'إبداعية'
        ];

        $prompt = "النص الحالي ({$typeMap[$type]}):\n{$text}\n\n";

        if ($action === 'enhance') {
            $prompt .= "الرجاء تحسين هذا النص لجعله أكثر احترافية وجاذبية.";
        } elseif ($action === 'complete') {
            $prompt .= "الرجاء إكمال هذا النص ليكون أكثر شمولاً وتفصيلاً.";
        } elseif ($action === 'seo') {
            $prompt .= "الرجاء تحسين هذا النص لمحركات البحث (SEO)";
        } elseif ($action === 'add_features') {
            $prompt .= "الرجاء إضافة قائمة بالمميزات الرئيسية للمنتج";
        } elseif ($action === 'add_benefits') {
            $prompt .= "الرجاء إضافة قائمة بالفوائد التي يحصل عليها العميل";
        } elseif ($action === 'add_call_to_action') {
            $prompt .= "الرجاء إضافة دعوة واضحة للعمل (Call to Action)";
        }

        if ($tone) {
            $prompt .= " مع نبرة {$toneMap[$tone]}.";
        }

        if ($style) {
            $prompt .= " وأسلوب {$styleMap[$style]}.";
        }

        // إضافة معلومات المنتج إذا كانت متاحة
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $prompt .= "\n\nمعلومات المنتج الإضافية:\n";
                $prompt .= "السعر: {$product->price}\n";
                $prompt .= "القسم: " . ($product->category->name ?? 'غير محدد') . "\n";
            }
        }

        $prompt .= "\n\nيرجى إرجاع النص المحسن فقط دون أي إضافات.";

        return $prompt;
    }

    /**
     * بناء Prompt لـ SEO
     */
    private function buildSEOPrompt($title, $description, $category, $tone, $style)
    {
        $toneMap = [
            'neutral' => 'محايدة',
            'friendly' => 'ودودة',
            'professional' => 'مهنية',
            'enthusiastic' => 'متحمسة',
            'persuasive' => 'مقنعة'
        ];

        $styleMap = [
            'formal' => 'رسمية',
            'simplified' => 'مبسطة',
            'seo' => 'محسنة لمحركات البحث',
            'creative' => 'إبداعية'
        ];

        $prompt = "عنوان المنتج: {$title}\n";

        if ($description) {
            $prompt .= "وصف المنتج: {$description}\n";
        }

        if ($category) {
            $prompt .= "تصنيف المنتج: {$category}\n";
        }

        $prompt .= "نبرة النص: " . ($toneMap[$tone] ?? 'محايدة') . "\n";
        $prompt .= "أسلوب الكتابة: " . ($styleMap[$style] ?? 'محسنة لمحركات البحث') . "\n\n";

        $prompt .= "الرجاء إنشاء محتوى SEO يتضمن:\n";
        $prompt .= "1. عنوان SEO مناسب (60-70 حرف)\n";
        $prompt .= "2. وصف SEO جذاب (150-160 حرف)\n";
        $prompt .= "3. كلمات مفتاحية مناسبة (5-10 كلمات)\n\n";

        $prompt .= "يرجى إرجاع النتيجة بالتنسيق التالي:\n";
        $prompt .= "META_TITLE: [عنوان SEO]\n";
        $prompt .= "META_DESCRIPTION: [وصف SEO]\n";
        $prompt .= "META_KEYWORDS: [الكلمات المفتاحية]\n";

        return $prompt;
    }

    /**
     * بناء Prompt للنصوص الإعلانية
     */
    private function buildTextAdsPrompt($title, $description, $price, $priceText, $tone, $style)
    {
        $prompt = "عنوان المنتج: {$title}\n";

        if ($description) {
            $prompt .= "وصف المنتج: {$description}\n";
        }

        if ($price) {
            $prompt .= "سعر المنتج: {$price}\n";
        }

        if ($priceText) {
            $prompt .= "نص السعر: {$priceText}\n";
        }

        $prompt .= "الرجاء إنشاء 3-5 نصوص إعلانية جذابة للمنتج مع مراعاة:\n";
        $prompt .= "1. التركيز على فوائد المنتج\n";
        $prompt .= "2. استخدام لغة إقناعية\n";
        $prompt .= "3. إضافة دعوة للعمل\n";
        $prompt .= "4. التنوع في الأسلوب (قصيرة، متوسطة، طويلة)\n\n";

        $prompt .= "يرجى إرجاع النتائج كل نص في سطر منفصل.";

        return $prompt;
    }

    /**
     * بناء Prompt للترجمة
     */
    private function buildTranslationPrompt($title, $description, $priceText, $targetLang, $style, $tone)
    {
        $prompt = "الرجاء ترجمة محتوى المنتج التالي إلى اللغة {$targetLang}:\n\n";

        $prompt .= "العنوان: {$title}\n\n";

        if ($description) {
            $prompt .= "الوصف: {$description}\n\n";
        }

        if ($priceText) {
            $prompt .= "نص السعر: {$priceText}\n\n";
        }

        $prompt .= "يرجى إرجاع الترجمة بالتنسيق التالي:\n";
        $prompt .= "TITLE: [العنوان المترجم]\n";

        if ($description) {
            $prompt .= "DESCRIPTION: [الوصف المترجم]\n";
        }

        if ($priceText) {
            $prompt .= "PRICE_TEXT: [نص السعر المترجم]\n";
        }

        return $prompt;
    }

    /**
     * بناء Prompt للتحسين الكامل
     */
    private function buildFullEnhancementPrompt($title, $category, $currentDescription, $product, $tone, $style)
    {
        $prompt = "عنوان المنتج: {$title}\n";
        $prompt .= "تصنيف المنتج: {$category}\n";

        if ($currentDescription) {
            $prompt .= "الوصف الحالي: {$currentDescription}\n";
        }

        if ($product) {
            $prompt .= "السعر: {$product->price}\n";
            $prompt .= "نص السعر الحالي: {$product->price_text}\n";
        }

        $prompt .= "\nالرجاء تحسين المنتج بالكامل من خلال:\n";
        $prompt .= "1. تحسين العنوان لجعله أكثر جاذبية\n";
        $prompt .= "2. كتابة وصف شامل ومقنع للمنتج\n";
        $prompt .= "3. كتابة نص سعر جذاب\n";
        $prompt .= "4. إنشاء محتوى SEO (عنوان، وصف، كلمات مفتاحية)\n";
        $prompt .= "5. إنشاء 3 نصوص إعلانية مختلفة\n\n";

        $prompt .= "يرجى إرجاع النتيجة بالتنسيق التالي:\n";
        $prompt .= "TITLE: [العنوان المحسن]\n";
        $prompt .= "DESCRIPTION: [الوصف المحسن]\n";
        $prompt .= "PRICE_TEXT: [نص السعر المحسن]\n";
        $prompt .= "SEO_TITLE: [عنوان SEO]\n";
        $prompt .= "SEO_DESCRIPTION: [وصف SEO]\n";
        $prompt .= "SEO_KEYWORDS: [الكلمات المفتاحية]\n";
        $prompt .= "TEXT_AD_1: [النص الإعلاني الأول]\n";
        $prompt .= "TEXT_AD_2: [النص الإعلاني الثاني]\n";
        $prompt .= "TEXT_AD_3: [النص الإعلاني الثالث]\n";

        return $prompt;
    }

// =================================================================
// دوال مساعدة للتحليل
// =================================================================

    /**
     * تحليل محتوى SEO
     */
    private function parseSEOContent($content)
    {
        $result = [
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => ''
        ];

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'META_TITLE:')) {
                $result['meta_title'] = trim(str_replace('META_TITLE:', '', $line));
            } elseif (str_starts_with($line, 'META_DESCRIPTION:')) {
                $result['meta_description'] = trim(str_replace('META_DESCRIPTION:', '', $line));
            } elseif (str_starts_with($line, 'META_KEYWORDS:')) {
                $result['meta_keywords'] = trim(str_replace('META_KEYWORDS:', '', $line));
            }
        }

        return $result;
    }

    /**
     * تحليل النصوص الإعلانية
     */
    private function parseTextAdsContent($content)
    {
        $textAds = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_starts_with($line, 'TEXT_AD_')) {
                $textAds[] = $line;
            }
        }

        return array_slice($textAds, 0, 5); // الحد الأقصى 5 نصوص إعلانية
    }

    /**
     * تحليل الترجمة
     */
    private function parseTranslationContent($content)
    {
        $result = [
            'title' => '',
            'description' => '',
            'price_text' => ''
        ];

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'TITLE:')) {
                $result['title'] = trim(str_replace('TITLE:', '', $line));
            } elseif (str_starts_with($line, 'DESCRIPTION:')) {
                $result['description'] = trim(str_replace('DESCRIPTION:', '', $line));
            } elseif (str_starts_with($line, 'PRICE_TEXT:')) {
                $result['price_text'] = trim(str_replace('PRICE_TEXT:', '', $line));
            }
        }

        return $result;
    }

    /**
     * تحليل المنتج المحسن
     */
    private function parseEnhancedProduct($content)
    {
        $result = [
            'title' => '',
            'description' => '',
            'price_text' => '',
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
                'meta_keywords' => ''
            ],
            'text_ads' => []
        ];

        $lines = explode("\n", $content);
        $currentSection = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'TITLE:')) {
                $result['title'] = trim(str_replace('TITLE:', '', $line));
            } elseif (str_starts_with($line, 'DESCRIPTION:')) {
                $currentSection = 'description';
                $result['description'] = trim(str_replace('DESCRIPTION:', '', $line));
            } elseif (str_starts_with($line, 'PRICE_TEXT:')) {
                $result['price_text'] = trim(str_replace('PRICE_TEXT:', '', $line));
            } elseif (str_starts_with($line, 'SEO_TITLE:')) {
                $result['seo']['meta_title'] = trim(str_replace('SEO_TITLE:', '', $line));
            } elseif (str_starts_with($line, 'SEO_DESCRIPTION:')) {
                $result['seo']['meta_description'] = trim(str_replace('SEO_DESCRIPTION:', '', $line));
            } elseif (str_starts_with($line, 'SEO_KEYWORDS:')) {
                $result['seo']['meta_keywords'] = trim(str_replace('SEO_KEYWORDS:', '', $line));
            } elseif (str_starts_with($line, 'TEXT_AD_')) {
                $result['text_ads'][] = trim(str_replace(['TEXT_AD_1:', 'TEXT_AD_2:', 'TEXT_AD_3:'], '', $line));
            } elseif ($currentSection === 'description' && !empty($line) && !str_starts_with($line, 'PRICE_TEXT:')) {
                $result['description'] .= "\n" . $line;
            }
        }

        // تنظيف النتائج
        foreach ($result as $key => $value) {
            if (is_string($value)) {
                $result[$key] = trim($value);
            }
        }

        return $result;
    }

    /**
     * ترجمة بيانات المنتج
     */
    private function translateProductData($data, $targetLang, $style, $tone)
    {
        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $prompt = "الرجاء ترجمة بيانات المنتج التالية إلى اللغة {$targetLang}:\n\n";
            $prompt .= "العنوان: {$data['title']}\n";
            $prompt .= "الوصف: {$data['description']}\n";
            $prompt .= "نص السعر: {$data['price_text']}\n\n";

            $prompt .= "يرجى إرجاع النتيجة بالتنسيق التالي:\n";
            $prompt .= "TITLE: [العنوان المترجم]\n";
            $prompt .= "DESCRIPTION: [الوصف المترجم]\n";
            $prompt .= "PRICE_TEXT: [نص السعر المترجم]\n";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مترجم محترف.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $translatedContent = $result['choices'][0]['message']['content'] ?? null;

                if ($translatedContent) {
                    return $this->parseTranslationContent($translatedContent);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Product data translation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ترجمة محتوى SEO
     */
    private function translateSEO($seoData, $targetLang, $style, $tone)
    {
        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $prompt = "الرجاء ترجمة محتوى SEO التالي إلى اللغة {$targetLang}:\n\n";
            $prompt .= "عنوان SEO: {$seoData['meta_title']}\n";
            $prompt .= "وصف SEO: {$seoData['meta_description']}\n";
            $prompt .= "الكلمات المفتاحية: {$seoData['meta_keywords']}\n\n";

            $prompt .= "يرجى إرجاع النتيجة بالتنسيق التالي:\n";
            $prompt .= "META_TITLE: [عنوان SEO المترجم]\n";
            $prompt .= "META_DESCRIPTION: [وصف SEO المترجم]\n";
            $prompt .= "META_KEYWORDS: [الكلمات المفتاحية المترجمة]\n";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مترجم محترف متخصص في ترجمة محتوى SEO.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $translatedContent = $result['choices'][0]['message']['content'] ?? null;

                if ($translatedContent) {
                    return $this->parseSEOContent($translatedContent);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('SEO translation error', ['error' => $e->getMessage()]);
            return null;
        }
    }
// ProductController.php - إضافة هذه الطرق

    /**
     * عرض نموذج إنشاء منتج جديد بالذكاء الاصطناعي
     */
    public function createWithAI()
    {
        $languages = Language::where('is_active', true)->get();
        $categories = Category::where('status', true)->get();
        $offers = Offer::where('is_active', true)->get();

        return view('Admin.products.create-with-ai', compact('languages', 'categories', 'offers'));
    }

    /**
     * حفظ المنتج الجديد مع الترجمات
     */
    public function storeWithAI(Request $request)
    {
        // التحقق من الصلاحيات
        // if (!auth()->user()->can('create_products')) {
        //     return redirect()->back()->with('error', 'ليس لديك صلاحية إنشاء منتجات');
        // }

        // التحقق من صحة البيانات
        $request->validate([
            'name_ar' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'price_text_ar' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'status_id' => 'required|in:1,2,3',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'meta_title_ar' => 'nullable|string|max:255',
            'meta_description_ar' => 'nullable|string|max:500',
            'meta_keywords_ar' => 'nullable|string|max:255',
            'has_discount' => 'nullable|boolean',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'includes_tax' => 'nullable|boolean',
            'text_ads' => 'nullable|array',
            'text_ads.*.name' => 'nullable|string|max:500',
            'offers' => 'nullable|array',
            'offers.*' => 'exists:offers,id'
        ]);

        DB::beginTransaction();

        try {
            // إنشاء المنتج
            $product = new Product();
            $product->category_id = $request->category_id;
            $product->price = $request->price;
            $product->stock = $request->stock;
            $product->status_id = $request->status_id;
            $product->includes_tax = $request->boolean('includes_tax');
            $product->includes_shipping = false;
            $product->created_by = auth()->id();

            // حفظ الصورة الرئيسية
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
                $product->image = $imagePath;
            }

            $product->save();

            // حفظ الترجمات للعربية
            $translationData = [
                'name' => [
                    'ar' => $request->name_ar
                ],
                'description' => [
                    'ar' => $request->description_ar
                ],
                'price_text' => [
                    'ar' => $request->price_text_ar
                ],
                'meta_title' => [
                    'ar' => $request->meta_title_ar
                ],
                'meta_description' => [
                    'ar' => $request->meta_description_ar
                ],
                'meta_keywords' => [
                    'ar' => $request->meta_keywords_ar
                ]
            ];

            // الترجمة التلقائية للغات الأخرى
            $languages = Language::where('is_active', true)->where('code', '!=', 'ar')->get();

            if ($languages->count() > 0) {
                // ترجمة تلقائية باستخدام الذكاء الاصطناعي
                foreach ($languages as $language) {
                    $translated = $this->translateWithAIHelper(
                        $request->name_ar,
                        $request->description_ar,
                        $request->price_text_ar,
                        $language->code,
                        $request->translation_style ?? 'formal',
                        $request->tone ?? 'neutral'
                    );

                    if ($translated) {
                        $translationData['name'][$language->code] = $translated['title'];
                        $translationData['description'][$language->code] = $translated['description'];
                        $translationData['price_text'][$language->code] = $translated['price_text'];
                    }
                }
            }

            // حفظ الترجمات
            foreach ($translationData as $field => $translations) {
                foreach ($translations as $langCode => $value) {
                    if ($value) {
                        $product->saveTranslation($field, $value, $langCode);
                    }
                }
            }



            // حفظ الصور الإضافية
            if ($request->hasFile('additional_images')) {
                foreach ($request->file('additional_images') as $image) {
                    $imagePath = $image->store('products/additional', 'public');

                    $productImage = new Image();
                    $productImage->product_id = $product->id;
                    $productImage->path = $imagePath;
                    $productImage->type = 'additional';
                    $productImage->save();
                }
            }

            // حفظ النصوص الإعلانية
            if ($request->text_ads) {
                foreach ($request->text_ads as $textAd) {
                    if (!empty($textAd['name'])) {
                        $productAd = new ProductAdsText();
                        $productAd->product_id = $product->id;
                        $productAd->name = json_encode(['ar' => $textAd['name']], JSON_UNESCAPED_UNICODE);
                        $productAd->save();
                    }
                }
            }

            // حفظ العروض
            if ($request->offers) {
                $product->offers()->sync($request->offers);
            }

            // تسجيل النشاط
            if (function_exists('activity') && auth()->check()) {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($product)
                    ->log('أنشأ منتجاً جديداً بالذكاء الاصطناعي');
            }

            DB::commit();

            return redirect()
                ->route('admin.products.show', $product->id)
                ->with('success', 'تم إنشاء المنتج بنجاح مع الترجمات التلقائية');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating product with AI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء المنتج: ' . $e->getMessage());
        }
    }

    /**
     * مساعد ترجمة مع الذكاء الاصطناعي
     */
    private function translateWithAIHelper($title, $description, $priceText, $targetLang, $style, $tone)
    {
        $apiKey = config('services.deepseek.api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $prompt = "الرجاء ترجمة محتوى المنتج التالي إلى اللغة {$targetLang}:\n\n";
            $prompt .= "العنوان: {$title}\n\n";

            if ($description) {
                $prompt .= "الوصف: {$description}\n\n";
            }

            if ($priceText) {
                $prompt .= "نص السعر: {$priceText}\n\n";
            }

            $prompt .= "يرجى إرجاع الترجمة بالتنسيق التالي:\n";
            $prompt .= "TITLE: [العنوان المترجم]\n";

            if ($description) {
                $prompt .= "DESCRIPTION: [الوصف المترجم]\n";
            }

            if ($priceText) {
                $prompt .= "PRICE_TEXT: [نص السعر المترجم]\n";
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مترجم محترف متخصص في ترجمة نصوص المنتجات.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 3000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $translatedContent = $result['choices'][0]['message']['content'] ?? null;

                if ($translatedContent) {
                    return $this->parseTranslationContent($translatedContent);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('AI translation helper error', ['error' => $e->getMessage()]);
            return null;
        }
    }



    /**
     * مزامنة جميع المنتجات من Like4App
     * - يبدأ من page=1 لحد آخر صفحة
     * - لو external_id موجود: يحدث السعر والكمية وبعض البيانات
     * - لو غير موجود: ينشئ منتج جديد
     */
    private function mapLike4AppProduct(array $item): array
    {
        $externalId = isset($item['productId']) ? (int) $item['productId'] : null;

        if (!$externalId) {
            throw new \Exception('المنتج لا يحتوي على external id');
        }

        $rawName = trim($item['productName'] ?? 'منتج بدون اسم');
        $name = mb_substr($rawName, 0, 60);

        $price = isset($item['sellPrice']) ? (float) $item['sellPrice'] : 0;
        $currency = trim($item['productCurrency'] ?? 'EGP');
        $priceText = $price . ' ' . $currency;

        $available = (bool) ($item['available'] ?? false);
        $stock = $available ? 100 : 0;

        $imageUrl = $item['productImageWhiteLabel']
            ?? $item['productImage']
            ?? null;

        return [
            'external_id' => (string) $externalId,
            'sku' => 'PROD-' . $externalId,
            'name' => $name,
            'description' => $rawName,
            'price' => $price,
            'price_text' => $priceText,
            'currency' => $currency,
            'stock' => $stock,
            'image_url' => $imageUrl,
        ];
    }
    public function syncFromLd(Request $request, \App\Services\Like4AppService $like4AppService)
    {
        DB::beginTransaction();

        try {
            $languages = Language::where('is_active', true)->get();
            $defaultCategoryId = $request->input('default_category_id', 1);

            $page = 1;
            $newCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;
            $failedCount = 0;
            $failedProducts = [];

            while (true) {
                $response = $like4AppService->getProductsDirect($page);

                if (!is_array($response) || !($response['success'] ?? false)) {
                    throw new \Exception('فشل في جلب المنتجات من Like4App في الصفحة رقم ' . $page);
                }

                $responseFlag = (int) ($response['response'] ?? 0);
                $products = $response['data'] ?? [];

                // نهاية الصفحات (دي مش error)
                if ($responseFlag === 0) {
                    break;
                }

                if (!is_array($products) || empty($products)) {
                    break;
                }

                foreach ($products as $item) {
                    try {
                        $mapped = $this->mapLike4AppProduct($item);

                        $externalId = (string) $mapped['external_id'];
                        $name = $mapped['name'];
                        $description = $mapped['description'];
                        $price = (float) $mapped['price'];
                        $priceTextAr = $mapped['price_text'];
                        $stock = (int) $mapped['stock'];
                        $sku = $mapped['sku'];
                        $signCurrency = $mapped['currency'];
                        $imageUrl = $mapped['image_url'] ?? null;

                        $product = Product::where('external_id', $externalId)->first();

                        if ($product) {
                            $changed = false;

                            // price
                            if ((float) $product->price !== $price) {
                                $product->price = $price;
                                $changed = true;
                            }

                            // stock
                            if ((int) $product->stock !== $stock) {
                                $product->stock = $stock;
                                $changed = true;
                            }

                            // currency
                            if (($product->sign_currency ?? null) !== $signCurrency) {
                                $product->sign_currency = $signCurrency;
                                $changed = true;
                            }

                            // sku
                            if (!empty($sku) && $product->sku !== $sku) {
                                $product->sku = $sku;
                                $changed = true;
                            }

                            /**
                             * 🔥 أهم تعديل هنا:
                             * - نحمل الصورة لو:
                             *   1. مفيش صورة
                             *   2. أو الصورة الحالية URL (قديمة)
                             */
                            if (!empty($imageUrl)) {
                                $isExternal = str_starts_with((string) $product->image_path, 'http');

                                if (empty($product->image_path) || $isExternal) {
                                    $downloadedImage = $this->downloadAndStoreImage($imageUrl);

                                    if (!empty($downloadedImage)) {
                                        $product->image_path = $downloadedImage;
                                        $changed = true;
                                    }
                                }
                            }

                            // name
                            $nameData = is_array($product->name)
                                ? $product->name
                                : (json_decode($product->getRawOriginal('name') ?: '{}', true) ?: []);

                            if (($nameData['ar'] ?? '') !== $name) {
                                $nameData['ar'] = $name;

                                foreach ($languages as $language) {
                                    if ($language->code === 'ar') continue;

                                    if (empty($nameData[$language->code])) {
                                        $translated = $this->translateText($name, $language->code);
                                        if (!empty($translated)) {
                                            $nameData[$language->code] = $translated;
                                        }
                                    }
                                }

                                $product->name = $nameData;
                                $changed = true;
                            }

                            // description
                            $descriptionData = is_array($product->description)
                                ? $product->description
                                : (json_decode($product->getRawOriginal('description') ?: '{}', true) ?: []);

                            if (($descriptionData['ar'] ?? '') !== $description) {
                                $descriptionData['ar'] = $description;

                                foreach ($languages as $language) {
                                    if ($language->code === 'ar') continue;

                                    if (empty($descriptionData[$language->code])) {
                                        $translated = $this->translateText($description, $language->code);
                                        if (!empty($translated)) {
                                            $descriptionData[$language->code] = $translated;
                                        }
                                    }
                                }

                                $product->description = $descriptionData;
                                $changed = true;
                            }

                            // price_text
                            $priceTextData = is_array($product->price_text)
                                ? $product->price_text
                                : (json_decode($product->getRawOriginal('price_text') ?: '{}', true) ?: []);

                            if (($priceTextData['ar'] ?? '') !== $priceTextAr) {
                                $priceTextData['ar'] = $priceTextAr;

                                foreach ($languages as $language) {
                                    if ($language->code === 'ar') continue;

                                    if (empty($priceTextData[$language->code])) {
                                        $translated = $this->translateText($priceTextAr, $language->code);
                                        if (!empty($translated)) {
                                            $priceTextData[$language->code] = $translated;
                                        }
                                    }
                                }

                                $product->price_text = $priceTextData;
                                $changed = true;
                            }

                            if ($changed) {
                                $product->save();
                                $updatedCount++;
                            } else {
                                $unchangedCount++;
                            }
                        } else {
                            // ===== إنشاء =====

                            $nameData = ['ar' => $name];
                            $descriptionData = ['ar' => $description];
                            $priceTextData = ['ar' => $priceTextAr];

                            foreach ($languages as $language) {
                                if ($language->code === 'ar') continue;

                                $nameData[$language->code] = $this->translateText($name, $language->code);
                                $descriptionData[$language->code] = $this->translateText($description, $language->code);
                                $priceTextData[$language->code] = $this->translateText($priceTextAr, $language->code);
                            }

                            $imagePath = null;
                            if (!empty($imageUrl)) {
                                $imagePath = $this->downloadAndStoreImage($imageUrl);
                            }

                            $product = new Product();
                            $product->external_id = $externalId;
                            $product->sku = $sku;
                            $product->category_id = $defaultCategoryId;
                            $product->name = $nameData;
                            $product->description = $descriptionData;
                            $product->price = $price;
                            $product->price_text = $priceTextData;
                            $product->stock = $stock;
                            $product->status_id = 1;
                            $product->type = 'product';
                            $product->has_discount = 0;
                            $product->includes_tax = 0;
                            $product->includes_shipping = 0;
                            $product->image_path = $imagePath;
                            $product->sign_currency = $signCurrency;

                            if (auth()->check()) {
                                $product->created_by = auth()->id();
                            }

                            $product->save();
                            $newCount++;
                        }
                    } catch (\Throwable $e) {
                        $failedCount++;

                        $failedProducts[] = [
                            'external_id' => $item['productId'] ?? null,
                            'name' => $item['productName'] ?? 'غير معروف',
                            'error' => $e->getMessage(),
                        ];

                        Log::error('Like4App sync product failed', [
                            'page' => $page,
                            'product' => $item,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $page++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تمت مزامنة منتجات Like4App بنجاح',
                'pages_processed' => $page - 1,
                'new_count' => $newCount,
                'updated_count' => $updatedCount,
                'unchanged_count' => $unchangedCount,
                'failed_count' => $failedCount,
                'failed_products' => $failedProducts,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Like4App sync all products error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مزامنة منتجات Like4App: ' . $e->getMessage(),
            ], 500);
        }
    }
    private function downloadAndStoreImage(?string $imageUrl): ?string
    {
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg';

            $fileName = Str::random(20) . '.' . strtolower($extension);
            $relativePath = 'products/' . $fileName;

            Storage::disk('public')->put($relativePath, $response->body());

            return $relativePath; // مثال: products/abc123.jpg
        } catch (\Throwable $e) {
            Log::error('Image download failed', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
    /**
     * مزامنة منتج واحد من Like4App
     * - يبحث في Like4App من أول صفحة لحد ما يلاقي external_id الخاص بالمنتج
     * - لو لقى المنتج يحدث السعر والكمية والبيانات
     */
    public function syncSingleProductFromLd($id, \App\Services\Like4AppService $like4AppService)
    {
        try {
            $product = Product::findOrFail($id);
            $languages = Language::where('is_active', true)->get();

            if (empty($product->external_id)) {
                throw new \Exception('هذا المنتج لا يحتوي على external_id');
            }

            $targetExternalId = (int) $product->external_id;

            $page = 1;
            $remoteProduct = null;

            while (true) {
                $response = $like4AppService->getProducts($page);

                if (!is_array($response) || (int) ($response['response'] ?? 0) !== 1) {
                    throw new \Exception('فشل في جلب المنتجات من Like4App أثناء البحث عن المنتج');
                }

                $products = $response['data'] ?? [];

                if (!is_array($products) || empty($products)) {
                    break;
                }

                foreach ($products as $item) {
                    $itemExternalId = isset($item['productId']) ? (int) $item['productId'] : 0;

                    if ($itemExternalId === $targetExternalId) {
                        $remoteProduct = $item;
                        break 2;
                    }
                }

                $page++;
            }

            if (!$remoteProduct) {
                throw new \Exception('المنتج غير موجود في Like4App');
            }

            $changed = false;

            $name = trim($remoteProduct['productName'] ?? data_get($product->name, 'ar', 'منتج بدون اسم'));
            $price = isset($remoteProduct['sellPrice']) ? (float) $remoteProduct['sellPrice'] : (float) $product->price;
            $currency = trim($remoteProduct['productCurrency'] ?? ($product->sign_currency ?? 'EGP'));
            $priceTextAr = $price . ' ' . $currency;

            $available = (bool) ($remoteProduct['available'] ?? false);
            $stock = $available ? 100 : 0;

            $imagePath = $remoteProduct['productImageWhiteLabel']
                ?? $remoteProduct['productImage']
                ?? $product->image_path;

            $sku = 'PROD-' . $targetExternalId;
            $description = $name;
            $signCurrency = $currency;

            if ((float) $product->price !== (float) $price) {
                $product->price = $price;
                $changed = true;
            }

            if ((int) $product->stock !== (int) $stock) {
                $product->stock = $stock;
                $changed = true;
            }

            if (($product->sign_currency ?? null) !== $signCurrency) {
                $product->sign_currency = $signCurrency;
                $changed = true;
            }

            if (!empty($imagePath) && $product->image_path !== $imagePath) {
                $product->image_path = $imagePath;
                $changed = true;
            }

            if (!empty($sku) && $product->sku !== $sku) {
                $product->sku = $sku;
                $changed = true;
            }

            $nameData = is_array($product->name)
                ? $product->name
                : (json_decode($product->getRawOriginal('name') ?: '{}', true) ?: []);

            if (($nameData['ar'] ?? '') !== $name) {
                $nameData['ar'] = $name;

                foreach ($languages as $language) {
                    if ($language->code === 'ar') {
                        continue;
                    }

                    if (empty($nameData[$language->code])) {
                        $translated = $this->translateText($name, $language->code);
                        if (!empty($translated)) {
                            $nameData[$language->code] = $translated;
                        }
                    }
                }

                $product->name = $nameData;
                $changed = true;
            }

            $descriptionData = is_array($product->description)
                ? $product->description
                : (json_decode($product->getRawOriginal('description') ?: '{}', true) ?: []);

            if (($descriptionData['ar'] ?? '') !== $description) {
                $descriptionData['ar'] = $description;

                foreach ($languages as $language) {
                    if ($language->code === 'ar') {
                        continue;
                    }

                    if (empty($descriptionData[$language->code])) {
                        $translated = $this->translateText($description, $language->code);
                        if (!empty($translated)) {
                            $descriptionData[$language->code] = $translated;
                        }
                    }
                }

                $product->description = $descriptionData;
                $changed = true;
            }

            $priceTextData = is_array($product->price_text)
                ? $product->price_text
                : (json_decode($product->getRawOriginal('price_text') ?: '{}', true) ?: []);

            if (($priceTextData['ar'] ?? '') !== $priceTextAr) {
                $priceTextData['ar'] = $priceTextAr;

                foreach ($languages as $language) {
                    if ($language->code === 'ar') {
                        continue;
                    }

                    if (empty($priceTextData[$language->code])) {
                        $translated = $this->translateText($priceTextAr, $language->code);
                        if (!empty($translated)) {
                            $priceTextData[$language->code] = $translated;
                        }
                    }
                }

                $product->price_text = $priceTextData;
                $changed = true;
            }

            if ($changed) {
                $product->save();
            }

            return response()->json([
                'success' => true,
                'message' => $changed ? 'تم تحديث المنتج من Like4App بنجاح' : 'المنتج مطابق بالفعل',
                'was_updated' => $changed,
                'product' => [
                    'id' => $product->id,
                    'external_id' => $product->external_id,
                    'name' => data_get($product->name, 'ar'),
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'price_text' => data_get($product->price_text, 'ar'),
                    'sign_currency' => $product->sign_currency,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Like4App sync single product error', [
                'product_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مزامنة المنتج: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function syncFromLdBatch(Request $request, \App\Services\Like4AppService $like4AppService)
    {
        DB::beginTransaction();

        try {
            $languages = Language::where('is_active', true)->get();
            $defaultCategoryId = $request->input('default_category_id', 1);
            $page = (int) $request->input('page', 1);

            $response = $like4AppService->getProductsDirect($page);

            if (!is_array($response) || !($response['success'] ?? false)) {
                throw new \Exception('فشل في جلب المنتجات من Like4App في الصفحة رقم ' . $page);
            }

            $responseFlag = (int) ($response['response'] ?? 0);
            $products = $response['data'] ?? [];

            if ($responseFlag === 0 || !is_array($products) || empty($products)) {
                DB::commit();

                return response()->json([
                    'success' => true,
                    'finished' => true,
                    'next_page' => $page,
                    'new_count' => 0,
                    'updated_count' => 0,
                    'unchanged_count' => 0,
                    'failed_count' => 0,
                    'failed_products' => [],
                ]);
            }

            $newCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;
            $failedCount = 0;
            $failedProducts = [];

            foreach ($products as $item) {
                try {
                    $mapped = $this->mapLike4AppProduct($item);

                    $externalId = (string) $mapped['external_id'];
                    $name = $mapped['name'];
                    $description = $mapped['description'];
                    $price = (float) $mapped['price'];
                    $priceTextAr = $mapped['price_text'];
                    $stock = (int) $mapped['stock'];
                    $sku = $mapped['sku'];
                    $signCurrency = $mapped['currency'];
                    $imageUrl = $mapped['image_url'] ?? null;

                    $product = Product::where('external_id', $externalId)->first();

                    if ($product) {
                        $changed = false;

                        if ((float) $product->price !== $price) {
                            $product->price = $price;
                            $changed = true;
                        }

                        if ((int) $product->stock !== $stock) {
                            $product->stock = $stock;
                            $changed = true;
                        }

                        if (($product->sign_currency ?? null) !== $signCurrency) {
                            $product->sign_currency = $signCurrency;
                            $changed = true;
                        }

                        if (!empty($sku) && $product->sku !== $sku) {
                            $product->sku = $sku;
                            $changed = true;
                        }

                        // خزن رابط الصورة مؤقتًا فقط في image_path لو كان فارغًا
                        // أو سيبه كما هو لو عندك عمود منفصل للصورة الأصلية
                        if (empty($product->image_path) && !empty($imageUrl)) {
                            $product->image_path = $imageUrl;
                            $changed = true;
                        }

                        $nameData = is_array($product->name)
                            ? $product->name
                            : (json_decode($product->getRawOriginal('name') ?: '{}', true) ?: []);

                        if (($nameData['ar'] ?? '') !== $name) {
                            $nameData['ar'] = $name;

                            foreach ($languages as $language) {
                                if ($language->code === 'ar') {
                                    continue;
                                }

                                if (empty($nameData[$language->code])) {
                                    $translated = $this->translateText($name, $language->code);
                                    if (!empty($translated)) {
                                        $nameData[$language->code] = $translated;
                                    }
                                }
                            }

                            $product->name = $nameData;
                            $changed = true;
                        }

                        $descriptionData = is_array($product->description)
                            ? $product->description
                            : (json_decode($product->getRawOriginal('description') ?: '{}', true) ?: []);

                        if (($descriptionData['ar'] ?? '') !== $description) {
                            $descriptionData['ar'] = $description;

                            foreach ($languages as $language) {
                                if ($language->code === 'ar') {
                                    continue;
                                }

                                if (empty($descriptionData[$language->code])) {
                                    $translated = $this->translateText($description, $language->code);
                                    if (!empty($translated)) {
                                        $descriptionData[$language->code] = $translated;
                                    }
                                }
                            }

                            $product->description = $descriptionData;
                            $changed = true;
                        }

                        $priceTextData = is_array($product->price_text)
                            ? $product->price_text
                            : (json_decode($product->getRawOriginal('price_text') ?: '{}', true) ?: []);

                        if (($priceTextData['ar'] ?? '') !== $priceTextAr) {
                            $priceTextData['ar'] = $priceTextAr;

                            foreach ($languages as $language) {
                                if ($language->code === 'ar') {
                                    continue;
                                }

                                if (empty($priceTextData[$language->code])) {
                                    $translated = $this->translateText($priceTextAr, $language->code);
                                    if (!empty($translated)) {
                                        $priceTextData[$language->code] = $translated;
                                    }
                                }
                            }

                            $product->price_text = $priceTextData;
                            $changed = true;
                        }

                        if ($changed) {
                            $product->save();
                            $updatedCount++;
                        } else {
                            $unchangedCount++;
                        }
                    } else {
                        $nameData = ['ar' => $name];
                        $descriptionData = ['ar' => $description];
                        $priceTextData = ['ar' => $priceTextAr];

                        foreach ($languages as $language) {
                            if ($language->code === 'ar') {
                                continue;
                            }

                            $nameData[$language->code] = $this->translateText($name, $language->code);
                            $descriptionData[$language->code] = $this->translateText($description, $language->code);
                            $priceTextData[$language->code] = $this->translateText($priceTextAr, $language->code);
                        }

                        $product = new Product();
                        $product->external_id = $externalId;
                        $product->sku = $sku;
                        $product->category_id = $defaultCategoryId;
                        $product->name = $nameData;
                        $product->description = $descriptionData;
                        $product->price = $price;
                        $product->price_text = $priceTextData;
                        $product->stock = $stock;
                        $product->status_id = 1;
                        $product->type = 'product';
                        $product->has_discount = 0;
                        $product->includes_tax = 0;
                        $product->includes_shipping = 0;
                        $product->image_path = $imageUrl; // مؤقتًا
                        $product->sign_currency = $signCurrency;

                        if (auth()->check()) {
                            $product->created_by = auth()->id();
                        }

                        $product->save();
                        $newCount++;
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    $failedProducts[] = [
                        'external_id' => $item['productId'] ?? null,
                        'name' => $item['productName'] ?? 'غير معروف',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'finished' => false,
                'next_page' => $page + 1,
                'new_count' => $newCount,
                'updated_count' => $updatedCount,
                'unchanged_count' => $unchangedCount,
                'failed_count' => $failedCount,
                'failed_products' => $failedProducts,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مزامنة منتجات Like4App: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadProductImagesBatch(Request $request)
    {
        DB::beginTransaction();

        try {
            $limit = (int) $request->input('limit', 20);

            $products = Product::query()
                ->whereNotNull('image_path')
                ->where('image_path', 'like', 'http%')
                ->limit($limit)
                ->get();

            if ($products->isEmpty()) {
                DB::commit();

                return response()->json([
                    'success' => true,
                    'finished' => true,
                    'processed' => 0,
                ]);
            }

            $processed = 0;
            $failed = [];

            foreach ($products as $product) {
                try {
                    $localPath = $this->downloadAndStoreImage($product->image_path);

                    if (!empty($localPath)) {
                        $product->image_path = $localPath;
                        $product->save();
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => $product->id,
                        'external_id' => $product->external_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'finished' => false,
                'processed' => $processed,
                'failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
