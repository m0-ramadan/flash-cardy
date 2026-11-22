<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    public function index(Request $request)
    {
        $query=Review::with(['product','user','moderator'])->latest();
        if($request->filled('status')) $query->where('is_approved',$request->status==='approved');
        if($request->filled('rating')) $query->where('rating',$request->integer('rating'));
        if($request->filled('search')){
            $s=trim((string)$request->search);
            $query->where(fn($q)=>$q->where('comment','like',"%{$s}%")
                ->orWhereHas('product',fn($p)=>$p->where('name','like',"%{$s}%"))
                ->orWhereHas('user',fn($u)=>$u->where('name','like',"%{$s}%")));
        }
        $reviews=$query->paginate(30)->withQueryString();
        return view('Admin.reviews.index',compact('reviews'));
    }
    public function approve(Review $review){ $review->update(['is_approved'=>true,'moderated_by'=>auth('admin')->id(),'moderated_at'=>now()]); return back()->with('success','تم اعتماد التقييم.'); }
    public function reject(Review $review){ $review->update(['is_approved'=>false,'moderated_by'=>auth('admin')->id(),'moderated_at'=>now()]); return back()->with('success','تم إخفاء التقييم.'); }
    public function destroy(Review $review){ $review->delete(); return back()->with('success','تم حذف التقييم.'); }
}
