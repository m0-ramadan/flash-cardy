<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleComment;
use Illuminate\Http\Request;

class ArticleCommentModerationController extends Controller
{
    public function index(Request $request)
    {
        $query=ArticleComment::with(['article','user','moderator'])->latest();
        if($request->filled('status')) $query->where('is_approved',$request->status==='approved');
        if($request->filled('search')){
            $s=trim((string)$request->search);
            $query->where(fn($q)=>$q->where('content','like',"%{$s}%")->orWhere('name','like',"%{$s}%")->orWhere('email','like',"%{$s}%"));
        }
        $comments=$query->paginate(30)->withQueryString();
        return view('Admin.article_comments.index',compact('comments'));
    }
    public function approve(ArticleComment $comment){ $comment->update(['is_approved'=>true,'moderated_by'=>auth('admin')->id(),'moderated_at'=>now()]); return back()->with('success','تم اعتماد التعليق.'); }
    public function reject(ArticleComment $comment){ $comment->update(['is_approved'=>false,'moderated_by'=>auth('admin')->id(),'moderated_at'=>now()]); return back()->with('success','تم رفض التعليق.'); }
    public function destroy(ArticleComment $comment){ $comment->replies()->delete(); $comment->delete(); return back()->with('success','تم حذف التعليق.'); }
}
