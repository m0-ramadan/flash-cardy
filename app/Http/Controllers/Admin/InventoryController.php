<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockReservation;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $products = Product::query()->withTrashed()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('sku','like',"%{$s}%"));
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->where('track_stock', true)->whereRaw('(stock - COALESCE(reserved_stock,0)) <= 5'))
            ->orderBy('id','desc')->paginate(30)->withQueryString();

        $movements = InventoryMovement::with(['product','order'])->latest()->limit(100)->get();
        $stats = [
            'tracked_products' => Product::where('track_stock', true)->count(),
            'reserved_units' => (int) Product::sum('reserved_stock'),
            'sold_units' => (int) Product::sum('sold_stock'),
            'active_reservations' => StockReservation::where('status', StockReservation::STATUS_RESERVED)->count(),
            'low_stock' => Product::where('track_stock', true)->whereRaw('(stock - COALESCE(reserved_stock,0)) <= 5')->count(),
        ];
        return view('Admin.inventory.index', compact('products','movements','stats'));
    }

    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate(['stock'=>'required|integer|min:0','reason'=>'required|string|max:500']);
        try {
            $movement = $this->inventory->adjust($product, (int) $data['stock'], auth('admin')->id(), $data['reason']);
            return back()->with('success', 'تم تعديل المخزون وتسجيل الحركة #' . $movement->id);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['stock' => $e->getMessage()]);
        }
    }
}
