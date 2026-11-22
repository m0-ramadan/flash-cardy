<?php
namespace App\Services\Orders;
use App\Models\Cart; use App\Models\Coupon; use App\Models\Order; use App\Models\OrderItem; use App\Models\PaymentMethod; use App\Models\PaymentTransaction; use App\Models\Product; use App\Models\UserAddress; use App\Services\Coupons\CouponReservationService; use App\Services\Files\SecureUploadService; use App\Services\Inventory\InventoryService; use App\Services\Fulfillment\LocalSerialFulfillmentService; use App\Services\Pricing\ProductPricingService; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Support\Str;
class OrderCreationService {
 public function __construct(private ProductPricingService $pricing,private InventoryService $inventory,private LocalSerialFulfillmentService $localSerials,private CouponReservationService $coupons,private SecureUploadService $uploads){}
 public function create(Request $request,PaymentMethod $method): array { $user=$request->user(); if(!$user)throw new \RuntimeException('Authentication required.'); return DB::transaction(function()use($request,$method,$user){$cart=Cart::where('user_id',$user->id)->lockForUpdate()->first();if(!$cart)throw new \RuntimeException('السلة فارغة.');$items=$cart->items()->with(['product','designFile'])->lockForUpdate()->get();if($items->isEmpty())throw new \RuntimeException('السلة فارغة.');
  $address=$this->address($request,$user->id); $shippingSnapshot=$address?$this->addressSnapshot($address):null; $subtotal=0.0; $prepared=[];
  foreach($items as $item){$product=Product::whereKey($item->product_id)->lockForUpdate()->first();if(!$product||(int)$product->status_id!==1)throw new \RuntimeException('أحد المنتجات غير متاح حاليًا.');$price=$this->pricing->calculate($product,(array)$item->customization_snapshot,(int)$item->quantity);$subtotal+= (float)$price['line_total'];$prepared[]=[$item,$product,$price];}
  $order=Order::create(['user_id'=>$user->id,'cart_id'=>$cart->id,'session_id'=>null,'order_number'=>$this->number(),'customer_name'=>$request->input('customer_name',$user->name),'customer_phone'=>$request->input('customer_phone',$user->phone),'customer_email'=>$request->input('customer_email',$user->email),'subtotal'=>round($subtotal,4),'shipping_amount'=>0,'discount_amount'=>0,'tax_amount'=>0,'total_amount'=>round($subtotal,4),'payment_method'=>(string)$method->id,'payment_method_id'=>$method->id,'currency'=>config('commerce.base_currency','EGP'),'status'=>'pending','order_state'=>'pending','status_payment'=>'pending','payment_state'=>'pending','fulfillment_status'=>'unfulfilled','notes'=>$request->input('notes'),'address_id'=>$address?->id,'shipping_address'=>$address?->address_details,'shipping_address_snapshot'=>$shippingSnapshot,'billing_address_snapshot'=>$shippingSnapshot]);
  if($request->hasFile('image')){$f=$this->uploads->storeImage($request->file('image'),'order',$user->id);$order->forceFill(['image'=>$f->path])->save();}
  foreach($prepared as [$cartItem,$product,$price]){
   $source=(string)($product->fulfillment_source ?: ($product->external_id ? 'like4app' : 'none'));
   $mode=(string)($product->fulfillment_mode ?: ($source==='like4app'?'automatic':'manual'));
   OrderItem::create([
    'order_id'=>$order->id,'product_id'=>$product->id,'quantity'=>$cartItem->quantity,
    'price_per_unit'=>$price['price_per_unit'],'total_price'=>$price['line_total'],'is_sample'=>$cartItem->is_sample,
    'note'=>$cartItem->note,'quantity_id'=>$cartItem->quantity_id,'design_file_id'=>$cartItem->design_file_id,
    'image_design'=>$cartItem->image_design,
    'product_snapshot'=>[
     'id'=>$product->id,'sku'=>$product->sku,'name'=>$product->getTranslations('name'),
     'description'=>$product->getTranslations('description'),'subtitle'=>$product->subtitle,'brand'=>$product->brand,'region'=>$product->region,'denomination'=>$product->denomination,'validity_text'=>$product->validity_text,'image_path'=>$product->image_path,
     'external_id'=>$product->external_id,'inventory_type'=>$product->inventory_type,'type'=>$product->type,
     'instructions'=>$product->instructions,'terms'=>$product->terms,'url'=>$product->url,'max_quantity'=>$product->max_quantity,
     'fulfillment_source'=>$source,'fulfillment_mode'=>$mode,
    ],
    'customization_snapshot'=>$price['customization'],'pricing_snapshot'=>$price['pricing_snapshot'],
    'provider_product_id'=>$product->external_id,'fulfillment_source_snapshot'=>$source,
    'fulfillment_mode_snapshot'=>$mode,'serial_status'=>$source==='none'?'not_required':'pending',
   ]);
  }
  if($request->filled('coupon_code')){$coupon=Coupon::where('code',strtoupper(trim((string)$request->input('coupon_code'))))->lockForUpdate()->first();if(!$coupon)throw new \RuntimeException('كوبون الخصم غير صالح.');$reservation=$this->coupons->reserve($coupon,$order,$subtotal);$order->forceFill(['coupon_id'=>$coupon->id,'discount_amount'=>$reservation->discount_amount,'total_amount'=>round(max(0,$subtotal-(float)$reservation->discount_amount),4)])->save();}
  $this->localSerials->reserveOrder($order->fresh(['items.product']));
  $this->inventory->reserveOrder($order->fresh(['items.product']));
  $payment=PaymentTransaction::create(['order_id'=>$order->id,'payment_method_id'=>$method->id,'gateway'=>$method->key?:'manual','status'=>'initiating','original_amount'=>$order->total_amount,'original_currency'=>$order->currency?:config('commerce.base_currency','EGP'),'idempotency_key'=>'PAY-'.$order->id.'-1','metadata'=>['order_number'=>$order->order_number,'method_key'=>$method->key]]);
  return [$order->fresh(['items.product','paymentTransactions']),$payment];
 },3); }
 private function address(Request $request,int $userId): ?UserAddress {$id=$request->input('shipping_address_id',$request->input('address_id'));if($id)return UserAddress::where('user_id',$userId)->whereKey($id)->firstOrFail();return UserAddress::where('user_id',$userId)->where('is_default',true)->orderByDesc('id')->first();}
 private function addressSnapshot(UserAddress $a): array {return ['id'=>$a->id,'first_name'=>$a->first_name,'last_name'=>$a->last_name,'phone'=>$a->phone,'city'=>$a->city,'area'=>$a->area,'building'=>$a->building,'floor'=>$a->floor,'apartment_number'=>$a->apartment_number,'address_details'=>$a->address_details,'label'=>$a->label,'latitude'=>$a->latitude,'longitude'=>$a->longitude];}
 private function number(): string {return 'ORD-'.now()->format('Y').'-'.strtoupper(Str::random(12));}
}
