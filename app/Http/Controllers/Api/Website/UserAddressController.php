<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\StoreUserAddressRequest;
use App\Http\Requests\Website\UpdateUserAddressRequest;
use App\Http\Resources\Website\UserAddressResource;
use App\Models\UserAddress;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAddressController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $addresses = UserAddress::where('user_id', $request->user()->id)->orderByDesc('is_default')->latest()->get();
        return $this->success(UserAddressResource::collection($addresses), 'تم جلب العناوين بنجاح');
    }

    public function show(Request $request, $id)
    {
        $address = UserAddress::where('user_id', $request->user()->id)->find($id);
        return $address ? $this->success(new UserAddressResource($address), 'تم جلب العنوان بنجاح') : $this->error('العنوان غير موجود', 404);
    }

    public function store(StoreUserAddressRequest $request)
    {
        $validated = $request->validated();
        $validated['is_default'] = $request->boolean('is_default') || !UserAddress::where('user_id', $request->user()->id)->exists();
        $validated['address_role'] = $request->input('address_role', $request->input('type', 'shipping'));

        $address = DB::transaction(function () use ($request, $validated) {
            if ($validated['is_default']) UserAddress::where('user_id', $request->user()->id)->update(['is_default'=>false]);
            return UserAddress::create($validated + ['user_id'=>$request->user()->id]);
        });
        return $this->success(new UserAddressResource($address), 'تم إضافة العنوان بنجاح', 201);
    }

    public function update(UpdateUserAddressRequest $request, $id)
    {
        $address = UserAddress::where('user_id', $request->user()->id)->find($id);
        if (!$address) return $this->error('العنوان غير موجود', 404);
        $validated = $request->validated();
        if ($request->has('is_default')) $validated['is_default'] = $request->boolean('is_default');
        if ($request->has('address_role')) $validated['address_role'] = $request->input('address_role');

        DB::transaction(function () use ($request, $address, $validated) {
            if (($validated['is_default'] ?? false) === true) UserAddress::where('user_id',$request->user()->id)->whereKeyNot($address->id)->update(['is_default'=>false]);
            $address->update($validated);
            if (!$address->is_default && !UserAddress::where('user_id',$request->user()->id)->where('is_default',true)->exists()) {
                UserAddress::where('user_id',$request->user()->id)->orderBy('id')->limit(1)->update(['is_default'=>true]);
            }
        });
        return $this->success(new UserAddressResource($address->fresh()), 'تم تحديث العنوان بنجاح');
    }

    public function destroy(Request $request, $id)
    {
        $address = UserAddress::where('user_id', $request->user()->id)->find($id);
        if (!$address) return $this->error('العنوان غير موجود', 404);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) UserAddress::where('user_id',$request->user()->id)->orderBy('id')->limit(1)->update(['is_default'=>true]);
        return $this->success(null, 'تم حذف العنوان بنجاح');
    }
}
