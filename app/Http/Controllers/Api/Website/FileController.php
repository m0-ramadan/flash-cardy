<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\UploadedFile;
use App\Services\Files\SecureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class FileController extends Controller
{
    public function storeDesign(Request $request, SecureUploadService $uploads)
    {
        $request->validate(['file'=>'required|file','cart_item_id'=>'nullable|integer|exists:cart_items,id']);
        $user = $request->user();
        $record = $uploads->storeDesign($request->file('file'), $user->id);
        if ($request->filled('cart_item_id')) {
            $item = CartItem::whereKey($request->integer('cart_item_id'))->whereHas('cart', fn($q)=>$q->where('user_id',$user->id))->firstOrFail();
            $item->forceFill(['design_file_id'=>$record->id])->save();
        }
        return response()->json(['success'=>true,'data'=>[
            'id'=>$record->uuid,'name'=>$record->original_name,'mime_type'=>$record->mime_type,'size'=>$record->size,
            'preview_url'=>URL::temporarySignedRoute('api.v1.files.preview', now()->addMinutes((int) config('uploads.preview_minutes',15)), ['uploadedFile'=>$record->uuid]),
        ]], 201);
    }

    public function download(Request $request, UploadedFile $uploadedFile, SecureUploadService $uploads)
    {
        abort_unless($uploadedFile->user_id === $request->user()->id, 403);
        return $uploads->response($uploadedFile, true);
    }

    public function preview(Request $request, UploadedFile $uploadedFile, SecureUploadService $uploads)
    {
        abort_unless($request->hasValidSignature(), 403);
        return $uploads->response($uploadedFile, false);
    }
}
