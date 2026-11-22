<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\Review\StoreReviewRequest;
use App\Http\Requests\Website\Review\UpdateReviewRequest;
use App\Http\Resources\Website\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $query = Review::query()->approved()->with(['product', 'user'])->latest();
        $this->applyFilters($query, $request);
        $reviews = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 100))->withQueryString();

        return $this->success([
            'reviews' => ReviewResource::collection($reviews->getCollection())->resolve(),
            'pagination' => $this->pagination($reviews),
        ], 'تم جلب التقييمات بنجاح');
    }

    public function show($id)
    {
        $review = Review::query()->approved()->with(['product', 'user'])->find($id);
        return $review
            ? $this->success(new ReviewResource($review), 'تم جلب التقييم بنجاح')
            : $this->error('التقييم غير موجود', 404);
    }

    public function store(StoreReviewRequest $request)
    {
        $user = $request->user();
        $product = Product::query()->whereKey($request->product_id)->where('status_id', 1)->first();
        if (!$product) return $this->error('المنتج غير موجود أو غير متاح', 404);

        $verified = Order::query()
            ->where('user_id', $user->id)
            ->where('status_payment', 'paid')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        try {
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'rating' => (int) $request->rating,
                'comment' => $this->cleanText($request->comment),
                'is_approved' => false,
                'is_verified_purchase' => $verified,
            ]);
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return $this->error('لقد قمت بتقييم هذا المنتج من قبل', 422);
            }
            report($e);
            return $this->error('تعذر حفظ التقييم حالياً', 500);
        }

        return $this->success(new ReviewResource($review->load(['product', 'user'])), 'تم استلام التقييم وسيظهر بعد المراجعة', 201);
    }

    public function update(UpdateReviewRequest $request, $id)
    {
        $review = Review::query()->where('user_id', $request->user()->id)->find($id);
        if (!$review) return $this->error('التقييم غير موجود أو لا تملك صلاحية تعديله', 404);

        $review->fill(array_filter([
            'rating' => $request->input('rating'),
            'comment' => $request->has('comment') ? $this->cleanText($request->input('comment')) : null,
        ], fn ($v) => $v !== null));
        $review->is_approved = false;
        $review->moderated_by = null;
        $review->moderated_at = null;
        $review->save();

        return $this->success(new ReviewResource($review->load(['product', 'user'])), 'تم تحديث التقييم وسيعاد مراجعته');
    }

    public function destroy(Request $request, $id)
    {
        $review = Review::query()->where('user_id', $request->user()->id)->find($id);
        if (!$review) return $this->error('التقييم غير موجود أو لا تملك صلاحية حذفه', 404);
        $review->delete();
        return $this->success(null, 'تم حذف التقييم بنجاح');
    }

    public function myReviews(Request $request)
    {
        $query = Review::query()->where('user_id', $request->user()->id)->with(['product', 'user'])->latest();
        $this->applyFilters($query, $request, false);
        $reviews = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 100))->withQueryString();

        return $this->success([
            'reviews' => ReviewResource::collection($reviews->getCollection())->resolve(),
            'pagination' => $this->pagination($reviews),
        ], 'تم جلب تقييماتك بنجاح');
    }

    public function productReviews($productId, Request $request)
    {
        if (!Product::query()->whereKey($productId)->where('status_id', 1)->exists()) {
            return $this->error('المنتج غير موجود أو غير متاح', 404);
        }

        $query = Review::query()->approved()->where('product_id', $productId)->with('user')->latest();
        $this->applyFilters($query, $request, false);
        $reviews = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 100))->withQueryString();

        $statsQuery = Review::query()->approved()->where('product_id', $productId);
        $total = (clone $statsQuery)->count();
        $distribution = [];
        foreach (range(1, 5) as $rating) {
            $count = (clone $statsQuery)->where('rating', $rating)->count();
            $distribution[$rating] = ['count' => $count, 'percentage' => $total ? round($count * 100 / $total, 1) : 0];
        }

        return $this->success([
            'reviews' => ReviewResource::collection($reviews->getCollection())->resolve(),
            'pagination' => $this->pagination($reviews),
            'stats' => [
                'average_rating' => round((float) $statsQuery->avg('rating'), 1),
                'total_reviews' => $total,
                'rating_distribution' => $distribution,
            ],
        ], 'تم جلب تقييمات المنتج بنجاح');
    }

    private function applyFilters($query, Request $request, bool $allowProduct = true): void
    {
        if ($allowProduct && $request->filled('product_id')) $query->where('product_id', $request->integer('product_id'));
        if ($request->filled('rating')) $query->where('rating', min(max($request->integer('rating'), 1), 5));
        if ($request->filled('min_rating')) $query->where('rating', '>=', min(max($request->integer('min_rating'), 1), 5));
        if ($request->filled('max_rating')) $query->where('rating', '<=', min(max($request->integer('max_rating'), 1), 5));
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($q) => $q->where('comment', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")));
        }
    }

    private function cleanText(?string $text): ?string
    {
        if ($text === null) return null;
        return trim(strip_tags($text));
    }

    private function pagination($paginator): array
    {
        return [
            'total' => $paginator->total(), 'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
        ];
    }
}
