<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Resources\Website\ProductResource;
use App\Models\Favorite;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    use ApiResponseTrait;

    public function toggle(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer']]);
        $user = $request->user();

        $product = Product::query()
            ->whereKey($data['product_id'])
            ->where('status_id', 1)
            ->first();

        if (!$product) {
            return $this->error('المنتج غير موجود أو غير متاح.', 404);
        }

        return DB::transaction(function () use ($user, $product) {
            $favorite = Favorite::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($favorite) {
                $favorite->delete();
                return $this->success(['is_favorite' => false], 'تمت إزالة المنتج من المفضلة.');
            }

            Favorite::firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);

            return $this->success(['is_favorite' => true], 'تمت إضافة المنتج إلى المفضلة.');
        });
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product', fn ($q) => $q->where('status_id', 1))
            ->with(['product.discount', 'product.primaryImage', 'product.images'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return $this->success([
            'items' => ProductResource::collection($favorites->getCollection()->pluck('product'))->resolve(),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ],
        ], 'تم جلب المفضلة بنجاح.');
    }

    public function destroy(Request $request, int $productId)
    {
        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->success(['is_favorite' => false], 'تمت إزالة المنتج من المفضلة.');
    }
}
