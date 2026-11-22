<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\StoreArticleCommentRequest;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Traits\ApiResponseTrait;

class ArticleCommentController extends Controller
{
    use ApiResponseTrait;

    public function index($articleId)
    {
        if (!Article::query()->whereKey($articleId)->active()->published()->exists()) {
            return $this->error('المقال غير موجود', 404);
        }
        $comments = ArticleComment::query()->approved()->where('article_id', $articleId)
            ->whereNull('parent_id')->with(['user','replies.user'])->oldest()->paginate(30);
        return $this->success($comments, 'تم جلب التعليقات بنجاح');
    }

    public function store(StoreArticleCommentRequest $request)
    {
        $article = Article::query()->whereKey($request->article_id)->active()->published()->first();
        if (!$article) return $this->error('المقال غير موجود أو غير منشور', 404);

        if ($request->filled('parent_id')) {
            $validParent = ArticleComment::query()->whereKey($request->parent_id)
                ->where('article_id', $article->id)->approved()->exists();
            if (!$validParent) return $this->error('التعليق الأب غير صالح لهذا المقال', 422);
        }

        $user = $request->user();
        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'parent_id' => $request->input('parent_id'),
            'name' => $user?->name ?? trim(strip_tags((string) $request->name)),
            'email' => $user?->email ?? strtolower(trim((string) $request->email)),
            'content' => trim(strip_tags((string) $request->content)),
            'is_approved' => false,
        ]);

        return $this->success($comment, 'تم استلام التعليق وسيظهر بعد المراجعة', 201);
    }
}
