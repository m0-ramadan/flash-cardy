<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductPricingService
{
    public function calculate(Product $product, array $selection, int $quantity): array
    {
        if ((int) $product->status_id !== 1) throw ValidationException::withMessages(['product_id'=>'المنتج غير متاح حاليًا.']);
        $quantity = max(1, $quantity);
        $base = max(0, (float) ($product->final_price ?? $product->price ?? 0));
        $components = ['base'=>$base];
        $unit = $base;

        $tier = $this->bestPricingTier($product->id, $quantity, (bool)($selection['is_sample'] ?? false));
        if ($tier !== null) { $unit = max(0,$tier); $components['quantity_tier']=$unit; }

        if (!empty($selection['size_id']) && Schema::hasTable('product_size_tiers')) {
            $row = DB::table('product_size_tiers')->where('product_id',$product->id)->where('size_id',(int)$selection['size_id'])->where('quantity','<=',$quantity)->orderByDesc('quantity')->first();
            if (!$row) throw ValidationException::withMessages(['size_id'=>'المقاس المحدد غير متاح لهذا المنتج/الكمية.']);
            $unit = max(0,(float)$row->price_per_unit); $components['size_tier']=$unit;
        }

        if (!empty($selection['color_id'])) {
            $this->assertPivot('color_product','color_id',(int)$selection['color_id'],$product->id,'color_id');
            $extra=(float)(DB::table('colors')->where('id',(int)$selection['color_id'])->value('additional_price')??0); $unit += max(0,$extra); $components['color']=max(0,$extra);
        }
        if (!empty($selection['material_id'])) {
            $this->assertPivot('material_product','material_id',(int)$selection['material_id'],$product->id,'material_id');
            $components['material']=0.0;
        }
        if (!empty($selection['printing_method_id'])) {
            $valid = false;
            foreach (['printing_method_product','product_print_methods'] as $pivot) {
                if (Schema::hasTable($pivot) && DB::table($pivot)->where('product_id',$product->id)->where('printing_method_id',(int)$selection['printing_method_id'])->exists()) { $valid=true; break; }
            }
            if (!$valid) throw ValidationException::withMessages(['printing_method_id'=>'طريقة الطباعة غير متاحة لهذا المنتج.']);
            $extra=(float)(DB::table('printing_methods')->where('id',(int)$selection['printing_method_id'])->value('base_price')??0); $unit += max(0,$extra); $components['printing_method']=max(0,$extra);
        }
        foreach (['print_locations'=>['print_locations','additional_price'],'embroider_locations'=>['embroider_locations','additional_price']] as $field=>$meta) {
            $ids = array_values(array_unique(array_map('intval',(array)($selection[$field]??[]))));
            if ($ids) {
                $rows=DB::table($meta[0])->whereIn('id',$ids)->get(['id',$meta[1]]);
                if ($rows->count()!==count($ids)) throw ValidationException::withMessages([$field=>'أحد الخيارات المحددة غير صالح.']);
                $extra=(float)$rows->sum($meta[1]); $unit += max(0,$extra); $components[$field]=max(0,$extra);
            }
        }
        if (!empty($selection['design_service_id']) && Schema::hasTable('design_services')) {
            $row=DB::table('design_services')->where('id',(int)$selection['design_service_id'])->first();
            if(!$row) throw ValidationException::withMessages(['design_service_id'=>'خدمة التصميم غير صالحة.']);
            $extra=max(0,(float)$row->price); $unit += $extra; $components['design_service']=$extra;
        }
        $optionIds=array_values(array_unique(array_map('intval',(array)($selection['selected_options']??[]))));
        if($optionIds && Schema::hasTable('product_options')){
            $rows=DB::table('product_options')->where('product_id',$product->id)->whereIn('id',$optionIds)->get(['id','additional_price','option_name','option_value']);
            if($rows->count()!==count($optionIds)) throw ValidationException::withMessages(['selected_options'=>'أحد الخيارات الإضافية لا يخص هذا المنتج.']);
            $extra=(float)$rows->sum('additional_price'); $unit += max(0,$extra); $components['options']=max(0,$extra);
        }
        if (Schema::hasTable('product_options')) {
            $required=DB::table('product_options')->where('product_id',$product->id)->where('is_required',1)->pluck('id')->map(fn($v)=>(int)$v)->all();
            $missing=array_values(array_diff($required,$optionIds));
            if($missing) throw ValidationException::withMessages(['selected_options'=>'يجب اختيار جميع الخيارات المطلوبة.']);
        }

        $unit=round(max(0,$unit),4); $line=round($unit*$quantity,4);
        $snapshot=['product_id'=>$product->id,'sku'=>$product->sku,'quantity'=>$quantity,'selection'=>$this->normalizeSelection($selection),'components'=>$components,'unit_price'=>$unit,'line_total'=>$line,'currency'=>(string)($product->sign_currency ?: config('commerce.base_currency','EGP'))];
        return ['price_per_unit'=>$unit,'line_total'=>$line,'customization'=>$snapshot['selection'],'pricing_snapshot'=>$snapshot];
    }

    public function hash(Product $product, array $selection): string
    {
        return hash('sha256', json_encode(['product_id'=>$product->id,'selection'=>$this->normalizeSelection($selection)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private function normalizeSelection(array $s): array
    {
        $out=[];
        foreach(['size_id','color_id','material_id','printing_method_id','design_service_id','quantity_id'] as $k) $out[$k]=isset($s[$k])&&$s[$k]!==''?(int)$s[$k]:null;
        foreach(['print_locations','embroider_locations','selected_options'] as $k){$vals=array_values(array_unique(array_map('intval',(array)($s[$k]??[])))); sort($vals); $out[$k]=$vals;}
        $out['is_sample']=(bool)($s['is_sample']??false); return $out;
    }
    private function bestPricingTier(int $productId,int $quantity,bool $sample): ?float
    {
        if(!Schema::hasTable('pricing_tiers')) return null;
        $row=DB::table('pricing_tiers')->where('product_id',$productId)->where('is_sample',$sample?1:0)->where('quantity','<=',$quantity)->orderByDesc('quantity')->first();
        return $row?(float)$row->price_per_unit:null;
    }
    private function assertPivot(string $table,string $key,int $id,int $productId,string $field): void
    {
        if(!Schema::hasTable($table)||!DB::table($table)->where('product_id',$productId)->where($key,$id)->exists()) throw ValidationException::withMessages([$field=>'الخيار المحدد غير متاح لهذا المنتج.']);
    }
}
