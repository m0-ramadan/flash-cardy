<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\Website\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\User;
use App\Services\Like4AppService;
use App\Services\LikeCardService;
use App\Services\Inventory\InventoryService;
use App\Services\Fulfillment\Like4AppFulfillmentService;
use App\Services\Fulfillment\LocalSerialFulfillmentService;
use App\Services\Orders\OrderLifecycleService;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $like4AppService;
    protected $likeCardService;

    public function __construct(
        Like4AppService $like4AppService, LikeCardService $likeCardService,
        private InventoryService $inventory, private Like4AppFulfillmentService $like4AppFulfillment,
        private LocalSerialFulfillmentService $localSerialFulfillment,
        private OrderLifecycleService $lifecycle
    ) {
        $this->like4AppService = $like4AppService;
        $this->likeCardService = $likeCardService;
    }

    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product'])->latest();

        // البحث
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // فلترة حسب طريقة الدفع
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // فلترة حسب المبلغ
        if ($request->filled('amount_from')) {
            $query->where('total_amount', '>=', $request->get('amount_from'));
        }

        if ($request->filled('amount_to')) {
            $query->where('total_amount', '<=', $request->get('amount_to'));
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $orders = $query->paginate(15)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'paid' => Order::where('status', 'paid')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => PaymentTransaction::whereIn('status', ['paid','finished','confirmed','success'])->sum('original_amount'),
        ];

        return view('Admin.orders.index', compact('orders', 'stats'));
    }


    /**
     * Show the form for creating a new order
     */
    public function create()
    {
        $users = User::orderBy('name')->get();

        // جلب المنتجات النشطة فقط مع الترقيم
        $products = Product::where('status_id', 1)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('Admin.orders.create', compact('users', 'products'));
    }


    public function searchProducts(Request $request)
    {
        $search = $request->get('q');

        $products = Product::with(['discount', 'primaryImage'])
            ->where('status_id', 1)
            ->searched($search)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'items' => ProductResource::collection($products->getCollection())->resolve(),
            'next_page_url' => $products->nextPageUrl(),
            'prev_page_url' => $products->previousPageUrl(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            'per_page' => $products->perPage(),
        ]);
    }
    /**
     * Store a newly created order
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|max:100',
            'status' => 'nullable|in:pending,paid,processing,completed,cancelled',
            'notes' => 'nullable|string|max:5000',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $hasLike4AppProduct = false;
            $hasLocalSerialProduct = false;

            foreach ($request->items as $index => $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->first();

                if (!$product) {
                    throw new \Exception("المنتج غير موجود: {$item['product_id']}");
                }

                $quantity = (int) $item['quantity'];
                if ((int)$product->status_id !== 1) {
                    throw new \Exception("المنتج {$product->name} غير نشط ولا يمكن إضافته لطلب جديد.");
                }
                if ((int)$product->max_quantity > 0 && $quantity > (int)$product->max_quantity) {
                    throw new \Exception("أقصى كمية مسموحة للمنتج {$product->name} هي {$product->max_quantity}.");
                }
                $itemTotal = $quantity * $product->final_price;
                $subtotal += $itemTotal;

                $source = (string)($product->fulfillment_source ?: ($product->external_id ? 'like4app' : 'none'));
                if ($source === 'like4app') {
                    $hasLike4AppProduct = true;
                }
                if ($source === 'local_serials') {
                    $hasLocalSerialProduct = true;
                    if (!$product->allow_serial_backorder && $product->serials()->usable()->count() < $quantity) {
                        throw new \Exception("لا توجد أكواد كافية للمنتج {$product->name}.");
                    }
                }

                if ($product->inventory_type === 'physical' && $product->track_stock && $product->stock !== null) {
                    if ($product->stock < $quantity) {
                        throw new \Exception("الكمية المطلوبة من {$product->name} غير متوفرة. المتاح: {$product->stock}");
                    }
                }
            }

            $discount = $request->discount_amount ?? 0;
            $tax = $request->tax_amount ?? 0;
            $total = $subtotal - $discount + $tax;
            if ($discount > $subtotal) {
                throw new \Exception('قيمة الخصم لا يمكن أن تتجاوز إجمالي المنتجات.');
            }
            $total = max(0, $total);

            $order = Order::create([
                'user_id' => $request->user_id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone ?? null,
                'status' => $request->input('status', ($hasLike4AppProduct || $hasLocalSerialProduct) ? 'processing' : 'completed'),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'payment_method' => $request->payment_method,
                'currency' => config('commerce.base_currency', 'EGP'),
                'order_state' => 'processing',
                'payment_state' => 'pending',
                'status_payment' => 'pending',
                'fulfillment_status' => ($hasLike4AppProduct || $hasLocalSerialProduct) ? 'processing' : 'fulfilled',
                'notes' => $request->notes,
                'provider' => $hasLike4AppProduct ? 'like4app' : 'internal',
            ]);

            $this->logOrderAction($order, 'create_order', 'internal', [
                'request' => $request->except('_token'),
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $quantity = (int) $item['quantity'];
                $source = (string)($product->fulfillment_source ?: ($product->external_id ? 'like4app' : 'none'));
                $mode = (string)($product->fulfillment_mode ?: ($source === 'like4app' ? 'automatic' : 'manual'));
                $snapshot = [
                    'id'=>$product->id,'sku'=>$product->sku,'name'=>$product->getTranslations('name'),
                    'description'=>$product->getTranslations('description'),'subtitle'=>$product->subtitle,'brand'=>$product->brand,'region'=>$product->region,'denomination'=>$product->denomination,'validity_text'=>$product->validity_text,
                    'image_path'=>$product->image_path,'external_id'=>$product->external_id,'inventory_type'=>$product->inventory_type,
                    'type'=>$product->type,'instructions'=>$product->instructions,'terms'=>$product->terms,'url'=>$product->url,
                    'fulfillment_source'=>$source,'fulfillment_mode'=>$mode,
                ];

                // منتجات Like4App: كل وحدة OrderItem مستقل
                if ($source === 'like4app') {
                    for ($i = 0; $i < $quantity; $i++) {
                        $orderItem = OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'price_per_unit' => $product->final_price,
                            'total_price' => $product->final_price,
                            'product_snapshot' => $snapshot,
                            'provider_product_id' => $product->external_id,
                            'fulfillment_source_snapshot' => 'like4app',
                            'fulfillment_mode_snapshot' => $mode,
                            'serial_status' => 'pending',
                            'provider_data' => [
                                'external_id' => $product->external_id,
                                'type' => 'like4app',
                                'request_quantity' => 1,
                                'original_requested_quantity' => $quantity,
                            ],
                        ]);

                    }
                } else {
                    // المنتجات الداخلية تظل كما هي
                    $itemTotal = $quantity * $product->final_price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price_per_unit' => $product->final_price,
                        'total_price' => $itemTotal,
                        'product_snapshot' => $snapshot,
                        'fulfillment_source_snapshot' => $source,
                        'fulfillment_mode_snapshot' => $mode,
                        'serial_status' => $source === 'local_serials' ? 'pending' : 'not_required',
                        'provider_data' => null,
                    ]);

                    // Stock is handled by InventoryService (reservation + commit) for auditability.
                }
            }

            // Preserve historical admin behaviour (manual orders consume physical stock immediately)
            // but route it through the auditable reservation/commit ledger.
            $order->load('items.product');
            $this->localSerialFulfillment->reserveOrder($order);
            $this->inventory->reserveOrder($order);
            $this->inventory->commitOrder($order);

            DB::commit();

            if ($hasLike4AppProduct) {
                try {
                    $this->processLike4AppOrder($order, []);
                } catch (\Exception $e) {
                    Log::error('Like4App processing failed after local order creation', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $order->update([
                        'status' => 'processing',
                        'provider_response' => array_merge(
                            is_array($order->provider_response) ? $order->provider_response : [],
                            ['error' => $e->getMessage()]
                        ),
                    ]);

                    return redirect()->route('admin.orders.show', $order->id)
                        ->with('warning', 'تم إنشاء الطلب محليًا، لكن حدثت مشكلة أثناء تنفيذ طلب لايك كارد: ' . $e->getMessage());
                }
            }

            if ($hasLocalSerialProduct) {
                $localResult = $this->localSerialFulfillment->fulfillOrder($order->fresh(['items.product']), false);
                if (!($localResult['success'] ?? false)) {
                    $order->forceFill(['status' => 'processing', 'fulfillment_status' => 'processing'])->save();
                }
            }

            $this->localSerialFulfillment->syncOrderStatus($order->fresh(['items.product']));

            return redirect()->route('admin.orders.show', $order->id)
                ->with('success', 'تم إنشاء الطلب بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء الطلب: ' . $e->getMessage());
        }
    }
    /**
     * Process Like4App order
     */

    protected function processLike4AppOrder($order, $like4AppItems)
    {
        $result = $this->like4AppFulfillment->fulfillOrder($order, false);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'فشل تنفيذ بعض منتجات Like4App');
        }
        return true;
    }
    /**
     * Display the specified order
     */
    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.product',
            'logs' => function ($q) {
                $q->latest();
            }
        ])->findOrFail($id);

        foreach ($order->items as $item) {
            if (!empty($item->serial_codes) && is_array($item->serial_codes)) {
                $serials = $item->serial_codes;

                foreach ($serials as &$serial) {
                    if (is_array($serial) && !empty($serial['serial_code'])) {
                        try {
                            if (($serial['source'] ?? null) === 'local') {
                                $plain = Crypt::decryptString($serial['serial_code']);
                                $serial['serial_number'] = $serial['serial_number'] ?: $plain;
                                $serial['voucher_code'] = $plain;
                                if (!empty($serial['pin'])) {
                                    $serial['pin_plain'] = Crypt::decryptString($serial['pin']);
                                }
                            } else {
                                $serial['voucher_code'] = $this->likeCardService->decryptSerial($serial['serial_code']);
                            }
                        } catch (\Throwable $e) {
                            $serial['voucher_code'] = null;
                        }
                    }
                }

                $item->serial_codes = $serials;
            }
        }

        return view('Admin.orders.show', compact('order'));
    }

    /**
     * Show the form for editing the specified order
     */
    public function edit($id)
    {
        $order = Order::with('items')->findOrFail($id);
        $users = User::orderBy('name')->get();
        $products = Product::where('status_id', 1)->get();

        return view('Admin.orders.edit', compact('order', 'users', 'products'));
    }


    /**
     * Update the specified order
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'shipping_address' => 'nullable|string|max:5000',
            'status' => 'required|in:pending,paid,processing,completed,cancelled',
            'payment_method' => 'required|string|max:100',
            'notes' => 'nullable|string|max:5000',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.quantity' => 'required_with:items|integer|min:1|max:1000',
            'items.*.price_per_unit' => 'required_with:items|numeric|min:0',
            'items.*.remove' => 'nullable|boolean',
            'new_product_id' => 'nullable|exists:products,id',
            'new_quantity' => 'nullable|required_with:new_product_id|integer|min:1|max:1000',
            'confirm_paid_financial_edit' => 'nullable|accepted',
        ]);

        try {
            $statusBefore = null;
            $order = DB::transaction(function () use ($id, $data, $request, &$statusBefore) {
                $order = Order::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
                $statusBefore = $order->status;
                $before = $order->only([
                    'customer_name','customer_email','customer_phone','shipping_address','payment_method','notes',
                    'subtotal','discount_amount','tax_amount','total_amount','status',
                ]);
                $paid = $order->status_payment === 'paid' || in_array($order->payment_state, ['paid','partially_refunded'], true);

                // Remove unpaid/unfulfilled items safely and release their reservations.
                foreach ($order->items as $item) {
                    $incoming = $data['items'][$item->id] ?? null;
                    if (!$incoming || empty($incoming['remove'])) continue;
                    if ($paid || $item->fulfilled_at || !empty($item->serial_codes)) {
                        throw new \RuntimeException('لا يمكن حذف منتج تم دفعه أو تسليم أكواده من سجل الطلب. استخدم الاسترداد المالي.');
                    }
                    $this->inventory->releaseItem($item, 'admin_order_item_removed');
                    $this->localSerialFulfillment->releaseItem($item, 'admin_order_item_removed');
                    $item->delete();
                }

                // Add a new product while the order is still financially unsettled.
                if (!empty($data['new_product_id'])) {
                    if ($paid) throw new \RuntimeException('لا يمكن إضافة منتج جديد إلى طلب مدفوع. أنشئ طلباً إضافياً للحفاظ على تطابق الدفعة.');
                    $product = Product::whereKey($data['new_product_id'])->where('status_id',1)->lockForUpdate()->firstOrFail();
                    $qty=(int)$data['new_quantity'];
                    if ((int)$product->max_quantity>0 && $qty>(int)$product->max_quantity) throw new \RuntimeException('الكمية الجديدة أكبر من الحد المسموح لهذا المنتج.');
                    $source=(string)($product->fulfillment_source?:($product->external_id?'like4app':'none'));
                    $mode=(string)($product->fulfillment_mode?:($source==='like4app'?'automatic':'manual'));
                    $newItem=OrderItem::create([
                        'order_id'=>$order->id,'product_id'=>$product->id,'quantity'=>$qty,'price_per_unit'=>$product->final_price,
                        'total_price'=>round($qty*(float)$product->final_price,4),
                        'product_snapshot'=>['id'=>$product->id,'sku'=>$product->sku,'name'=>$product->getTranslations('name'),'description'=>$product->getTranslations('description'),
                            'subtitle'=>$product->subtitle,'brand'=>$product->brand,'region'=>$product->region,'denomination'=>$product->denomination,
                            'validity_text'=>$product->validity_text,'image_path'=>$product->image_path,'external_id'=>$product->external_id,
                            'inventory_type'=>$product->inventory_type,'type'=>$product->type,'instructions'=>$product->instructions,'terms'=>$product->terms,
                            'fulfillment_source'=>$source,'fulfillment_mode'=>$mode],
                        'provider_product_id'=>$source==='like4app'?$product->external_id:null,
                        'fulfillment_source_snapshot'=>$source,'fulfillment_mode_snapshot'=>$mode,
                        'serial_status'=>$source==='none'?'not_required':'pending',
                    ]);
                    $this->localSerialFulfillment->reserveItem($newItem->fresh('product'));
                    $this->inventory->reserveItem($newItem->fresh('product'));
                }

                $order->unsetRelation('items');
                $order->load('items');
                if ($order->items->isEmpty()) throw new \RuntimeException('لا يمكن حفظ طلب بدون أي منتجات.');

                $itemSubtotal = 0.0;
                foreach ($order->items as $item) {
                    $incoming = $data['items'][$item->id] ?? null;
                    if (!$incoming) {
                        $itemSubtotal += (float)$item->total_price;
                        continue;
                    }
                    $newQty = (int)$incoming['quantity'];
                    $newPrice = round((float)$incoming['price_per_unit'], 4);
                    $qtyChanged = $newQty !== (int)$item->quantity;
                    $priceChanged = abs($newPrice - (float)$item->price_per_unit) > 0.0001;

                    if ($qtyChanged && ($paid || $item->fulfilled_at || !empty($item->serial_codes))) {
                        throw new \RuntimeException('لا يمكن تغيير كمية منتج تم دفعه أو تسليم أكواده. استخدم الاسترداد/التعديل المالي للحفاظ على سجل التسليم.');
                    }
                    if ($paid && $priceChanged && !$request->boolean('confirm_paid_financial_edit')) {
                        throw new \RuntimeException('تعديل سعر طلب مدفوع يحتاج تأكيد التعديل المالي من نفس الشاشة.');
                    }

                    $oldQty = (int)$item->quantity;
                    $item->forceFill([
                        'quantity' => $newQty,
                        'price_per_unit' => $newPrice,
                        'total_price' => round($newQty * $newPrice, 4),
                    ])->save();
                    if ($qtyChanged) {
                        $this->inventory->resizeReservation($item, $oldQty, auth('admin')->id());
                    }
                    $itemSubtotal += (float)$item->total_price;
                }

                $financialChanged = abs((float)$order->subtotal - $itemSubtotal) > 0.0001
                    || abs((float)$order->discount_amount - (float)($data['discount_amount'] ?? 0)) > 0.0001
                    || abs((float)$order->tax_amount - (float)($data['tax_amount'] ?? 0)) > 0.0001;
                if ($paid && $financialChanged && !$request->boolean('confirm_paid_financial_edit')) {
                    throw new \RuntimeException('الطلب مدفوع. فعّل تأكيد التعديل المالي قبل تغيير الأسعار أو الإجماليات.');
                }

                $discount = round((float)($data['discount_amount'] ?? 0), 4);
                $tax = round((float)($data['tax_amount'] ?? 0), 4);
                $shipping = round((float)($order->shipping_amount ?? 0), 4);
                if ($discount > ($itemSubtotal + $shipping + $tax)) {
                    throw new \RuntimeException('الخصم أكبر من قيمة الطلب.');
                }
                $total = round(max(0, $itemSubtotal + $shipping - $discount + $tax), 4);

                $order->forceFill([
                    'customer_name'=>$data['customer_name'],'customer_email'=>$data['customer_email'] ?? null,
                    'customer_phone'=>$data['customer_phone'] ?? null,'shipping_address'=>$data['shipping_address'] ?? null,
                    'payment_method'=>$data['payment_method'],'notes'=>$data['notes'] ?? null,
                    'subtotal'=>round($itemSubtotal,4),'discount_amount'=>$discount,'tax_amount'=>$tax,'total_amount'=>$total,
                ])->save();

                $this->logOrderAction($order, 'update_order_details', $order->provider, [
                    'before'=>$before,
                    'after'=>$order->fresh()->only(['customer_name','customer_email','customer_phone','shipping_address','payment_method','notes','subtotal','discount_amount','tax_amount','total_amount']),
                    'paid_financial_override'=>$paid && $request->boolean('confirm_paid_financial_edit'),
                ]);
                return $order->fresh(['items.product']);
            }, 3);

            if ($data['status'] !== $statusBefore) {
                $order = $this->lifecycle->transition($order, $data['status'], auth('admin')->id(), $data['notes'] ?? null);
            }

            return redirect()->route('admin.orders.show', $order->id)->with('success', 'تم تحديث بيانات الطلب وتسجيل التعديل في سجل المراجعة.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Log order action
     */
    protected function logOrderAction($order, $action, $provider = null, $data = [])
    {
        OrderLog::create([
            'order_id' => $order->id,
            'action' => $action,
            'provider' => $provider,
            'request_data' => $data,
            'response_data' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_by' => auth('admin')->id(),
        ]);
    }

    /**
     * Get product details for AJAX
     */
    public function getProduct($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'final_price' => $product->final_price,
            'stock' => $product->stock,
            'external_id' => $product->external_id,
            'image' => $product->image ? asset('storage/' . $product->image) : asset('images/default-product.png'),
            'provider' => $product->external_id ? 'like4app' : 'internal'
        ]);
    }

    /**
     * Search products for AJAX
     */
    // public function searchProducts(Request $request)
    // {
    //     $search = $request->get('q');

    //     $products = Product::where('status_id', 1)
    //         ->where(function ($query) use ($search) {
    //             $query->where('name', 'LIKE', "%{$search}%")
    //                 ->orWhere('sku', 'LIKE', "%{$search}%")
    //                 ->orWhere('external_id', 'LIKE', "%{$search}%");
    //         })
    //         ->limit(10)
    //         ->get()
    //         ->map(function ($product) {
    //             return [
    //                 'id' => $product->id,
    //                 'name' => $product->name,
    //                 'price' => $product->final_price,
    //                 'stock' => $product->stock,
    //                 'external_id' => $product->external_id,
    //                 'provider' => $product->external_id ? 'like4app' : 'internal',
    //                 'image' => $product->image ? asset('storage/' . $product->image) : asset('images/default-product.png'),
    //             ];
    //         });

    //     return response()->json($products);
    // }
    public function destroy(Order $order)
    {
        if (in_array($order->status_payment, ['paid','partially_refunded'], true)) {
            return back()->withErrors(['order' => 'لا يمكن حذف طلب مدفوع. استخدم الاسترداد ثم احتفظ بالطلب كسجل مالي.']);
        }
        try {
            $this->inventory->releaseOrder($order, 'order_deleted');
            $this->localSerialFulfillment->releaseOrder($order, 'order_deleted');
            $order->delete();
            return redirect()->route('admin.orders.index')->with('success', 'تم حذف الطلب غير المدفوع بنجاح.');
        } catch (\Throwable $e) { report($e); return back()->withErrors(['order'=>$e->getMessage()]); }
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data=$request->validate(['status'=>'required|string|in:pending,paid,processing,completed,cancelled','notes'=>'nullable|string|max:1000']);
        try {
            $updated=$this->lifecycle->transition($order,$data['status'],auth('admin')->id(),$data['notes']??null);
            return response()->json(['success'=>true,'message'=>'تم تحديث حالة الطلب بنجاح','status'=>$updated->status,'fulfillment_status'=>$updated->fulfillment_status,'status_label'=>$updated->status_label]);
        } catch (\Throwable $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],422); }
    }

    public function print(Order $order)
    {
        $order->load(['user', 'items.product']);

        return view('Admin.orders.print', compact('order'));
    }

    public function export(Request $request)
    {
        // يمكنك إضافة تصدير الطلبات بصيغة Excel أو PDF هنا
        return response()->json(['message' => 'سيتم إضافة التصدير لاحقاً']);
    }

    public function statistics()
    {


        // إحصائيات متقدمة
        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => PaymentTransaction::whereIn('status', ['paid','finished','confirmed','success'])->sum('original_amount'),
            'average_order_value' => PaymentTransaction::whereIn('status',['paid','finished','confirmed','success'])->avg('original_amount') ?? 0,

            'today_orders' => Order::whereDate('created_at', today())->count(),
            'today_revenue' => PaymentTransaction::whereDate('paid_at', today())->whereIn('status',['paid','finished','confirmed','success'])->sum('original_amount'),

            'weekly_orders' => Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'weekly_revenue' => PaymentTransaction::whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()])->whereIn('status',['paid','finished','confirmed','success'])->sum('original_amount'),

            'monthly_orders' => Order::whereMonth('created_at', now()->month)->count(),
            'monthly_revenue' => PaymentTransaction::whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->whereIn('status',['paid','finished','confirmed','success'])->sum('original_amount'),

            'status_counts' => Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),

            'top_products' => DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
                ->groupBy('order_items.product_id', 'products.name')
                ->orderBy('total_quantity', 'desc')
                ->limit(10)
                ->get(),
        ];
        return view('Admin.orders.statistics', compact('stats'));
    }
}
