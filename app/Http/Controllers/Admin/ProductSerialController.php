<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\Fulfillment\LocalSerialFulfillmentService;
use Illuminate\Http\Request;

class ProductSerialController extends Controller
{
    public function __construct(private LocalSerialFulfillmentService $serials) {}

    public function index(Product $product, Request $request)
    {
        $this->serials->refreshExpiredStatuses($product->id);
        $query = $product->serials()->with(['order','orderItem'])->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('expires')) {
            if ($request->expires === 'expired') $query->whereNotNull('expires_at')->where('expires_at','<=',now());
            if ($request->expires === 'soon') $query->whereBetween('expires_at',[now(),now()->addDays(30)]);
        }
        $serials = $query->paginate(50)->withQueryString();
        $stats = [
            'total'=>$product->serials()->count(),
            'available'=>$product->serials()->usable()->count(),
            'assigned'=>$product->serials()->whereIn('status',[ProductSerial::ASSIGNED,ProductSerial::DELIVERED])->count(),
            'expired'=>$product->serials()->where('status',ProductSerial::EXPIRED)->count(),
            'disabled'=>$product->serials()->where('status',ProductSerial::DISABLED)->count(),
        ];
        return view('Admin.product_serials.index', compact('product','serials','stats'));
    }

    public function store(Product $product, Request $request)
    {
        $data = $request->validate([
            'serials'=>'required|array|min:1|max:1000',
            'serials.*.code'=>'required|string|max:1000',
            'serials.*.pin'=>'nullable|string|max:500',
            'serials.*.expires_at'=>'nullable|date',
            'serials.*.notes'=>'nullable|string|max:1000',
        ]);
        $result = $this->serials->addCodes($product->id, $data['serials'], auth('admin')->id());
        return back()->with('success', "تمت إضافة {$result['created']} كود؛ المكرر {$result['duplicates']}، غير الصالح {$result['invalid']}.");
    }

    public function bulkText(Product $product, Request $request)
    {
        $data = $request->validate(['serials_text'=>'required|string|max:200000']);
        $rows=[];
        foreach (preg_split('/\r\n|\r|\n/', $data['serials_text']) as $line) {
            $line=trim($line); if($line==='') continue;
            $parts=array_map('trim', str_getcsv($line, '|'));
            $rows[]=['code'=>$parts[0]??null,'expires_at'=>$parts[1]??null,'pin'=>$parts[2]??null,'notes'=>$parts[3]??null];
        }
        $result=$this->serials->addCodes($product->id,$rows,auth('admin')->id(),'bulk_text');
        return back()->with('success', "تمت إضافة {$result['created']} كود؛ المكرر {$result['duplicates']}، غير الصالح {$result['invalid']}.");
    }

    public function update(Product $product, ProductSerial $serial, Request $request)
    {
        abort_unless($serial->product_id === $product->id, 404);
        $data=$request->validate([
            'expires_at'=>'nullable|date', 'notes'=>'nullable|string|max:1000', 'status'=>'required|in:available,disabled,expired',
        ]);
        if (in_array($serial->status,[ProductSerial::ASSIGNED,ProductSerial::DELIVERED],true)) {
            return back()->withErrors(['serial'=>'لا يمكن تعديل كود تم تخصيصه لطلب.']);
        }
        $serial->forceFill([
            'expires_at'=>$data['expires_at']?:null,'notes'=>$data['notes']??null,'status'=>$data['status'],
            'disabled_at'=>$data['status']===ProductSerial::DISABLED?now():null,
        ])->save();
        return back()->with('success','تم تحديث بيانات الكود.');
    }

    public function destroy(Product $product, ProductSerial $serial)
    {
        abort_unless($serial->product_id === $product->id, 404);
        if (in_array($serial->status,[ProductSerial::ASSIGNED,ProductSerial::DELIVERED],true) || $serial->order_item_id) {
            return back()->withErrors(['serial'=>'لا يمكن حذف كود تم استخدامه في طلب؛ يمكن الاحتفاظ به كسجل فقط.']);
        }
        $serial->delete();
        return back()->with('success','تم حذف الكود غير المستخدم.');
    }
}
