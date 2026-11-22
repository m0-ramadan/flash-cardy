<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Resources\Website\ArticleResource;
use App\Models\Article;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $query = Article::with(['category', 'author'])->active()->published()->latest('published_at');
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->integer('category_id'));
        if ($request->filled('tag')) $query->whereHas('tags', fn ($q) => $q->where('slug', $request->tag));
        if ($request->boolean('featured')) $query->featured();

        $articles = $query->paginate(min(max((int) $request->input('per_page', 12), 1), 100))->withQueryString();
        return $this->success([
            'items' => ArticleResource::collection($articles->getCollection())->resolve(),
            'pagination' => ['current_page'=>$articles->currentPage(),'last_page'=>$articles->lastPage(),'per_page'=>$articles->perPage(),'total'=>$articles->total()],
        ], 'تم جلب المقالات بنجاح');
    }

    public function show($slug)
    {
        $article = Article::with(['category', 'author', 'tags'])
            ->where('slug', $slug)->active()->published()->firstOrFail();
        $article->incrementViews();
        $article->refresh();
        return $this->success(new ArticleResource($article), 'تم جلب المقالة بنجاح');
    }

    public function featured()
    {
        $items = Article::with(['category','author'])->active()->published()->featured()->latest('published_at')->limit(6)->get();
        return $this->success(ArticleResource::collection($items), 'تم جلب المقالات المميزة بنجاح');
    }

    public function popular()
    {
        $items = Article::with(['category','author'])->active()->published()->popular()->limit(6)->get();
        return $this->success(ArticleResource::collection($items), 'تم جلب المقالات الأكثر مشاهدة بنجاح');
    }

    public function related($slug)
    {
        $article = Article::where('slug', $slug)->active()->published()->firstOrFail();
        $items = Article::with(['category','author'])->where('category_id', $article->category_id)
            ->whereKeyNot($article->id)->active()->published()->latest('published_at')->limit(4)->get();
        return $this->success(ArticleResource::collection($items), 'تم جلب المقالات ذات الصلة بنجاح');
    }
}
