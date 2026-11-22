<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ImportExportAudit;
use App\Models\Product;
use App\Support\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportExportController extends Controller
{
    public function form(){ return view('Admin.products.import'); }

    public function import(Request $request)
    {
        $data=$request->validate(['file'=>'required|file|mimes:csv,txt|max:5120','update_existing'=>'sometimes|boolean']);
        $handle=fopen($request->file('file')->getRealPath(),'rb');
        if(!$handle) return back()->withErrors(['file'=>'تعذر قراءة الملف.']);
        $headers=fgetcsv($handle);
        $headers=array_map(fn($h)=>Str::snake(trim((string)$h)),$headers?:[]);
        $required=['sku','name','price'];
        foreach($required as $h) if(!in_array($h,$headers,true)){ fclose($handle); return back()->withErrors(['file'=>"العمود {$h} مطلوب."]); }

        $total=$ok=$failed=0; $errors=[];
        while(($row=fgetcsv($handle))!==false){
            $total++; if(count($row)!==count($headers)){ $failed++; $errors[]="صف {$total}: عدد الأعمدة غير مطابق"; continue; }
            $r=array_combine($headers,$row);
            try{
                DB::transaction(function()use($r,$request){
                    $sku=trim((string)$r['sku']); $name=trim((string)$r['name']); $price=(float)$r['price'];
                    if($sku===''||$name===''||$price<0) throw new \InvalidArgumentException('SKU/name/price غير صالح');
                    $existing=Product::withTrashed()->where('sku',$sku)->first();
                    if($existing&&!$request->boolean('update_existing')) throw new \RuntimeException('SKU مكرر');
                    $categoryId=!empty($r['category_id'])?(int)$r['category_id']:null;
                    if($categoryId&&!Category::whereKey($categoryId)->exists()) throw new \RuntimeException('Category غير موجود');
                    $imagePath=trim((string)($r['image_path']??''));
                    if($imagePath!==''&&(str_contains($imagePath,'..')||str_contains($imagePath,chr(0))||preg_match('#^(?:[a-z]+:)?//#i',$imagePath)||!preg_match('#^(products|uploads)/[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp)$#i',$imagePath))) throw new \RuntimeException('image_path يجب أن يكون مسار صورة محلي آمن داخل products/ أو uploads/');
                    $p=$existing?:new Product();
                    if(method_exists($p,'restore')&&$p->trashed())$p->restore();
                    $p->sku=$sku; $p->setTranslation('name','ar',$name);
                    if(array_key_exists('description',$r))$p->setTranslation('description','ar',trim((string)$r['description']));
                    $p->price=$price; $p->stock=isset($r['stock'])&&$r['stock']!==''?max(0,(int)$r['stock']):$p->stock;
                    $p->category_id=$categoryId?:$p->category_id; $p->status_id=isset($r['status_id'])?(int)$r['status_id']:($p->status_id?:1);
                    if($imagePath!=='')$p->image_path=$imagePath;
                    if(isset($r['external_id']))$p->external_id=trim((string)$r['external_id'])?:null;
                    $p->save();
                }); $ok++;
            }catch(\Throwable $e){$failed++; $errors[]="صف {$total}: ".$e->getMessage(); if(count($errors)>=100)$errors=array_slice($errors,0,100);}
        }
        fclose($handle);
        ImportExportAudit::create(['admin_id'=>auth('admin')->id(),'operation'=>'import','entity'=>'products','file_name'=>basename($request->file('file')->getClientOriginalName()),'processed_rows'=>$total,'success_rows'=>$ok,'failed_rows'=>$failed,'errors'=>$errors]);
        return back()->with('success',"تمت معالجة {$total} صف: نجح {$ok} وفشل {$failed}.")->with('import_errors',$errors);
    }

    public function export(Request $request)
    {
        $filename='products-'.now()->format('Ymd-His').'.csv';
        $query=Product::withTrashed()->with('category')->orderBy('id');
        $response=response()->streamDownload(function()use($query){
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['id','sku','name','price','stock','reserved_stock','sold_stock','category_id','status_id','external_id','image_path','deleted_at']);
            $query->chunkById(500,function($products)use($out){foreach($products as $p){fputcsv($out,array_map([CsvSanitizer::class,'cell'],[$p->id,$p->sku,$p->name,$p->price,$p->stock,$p->reserved_stock,$p->sold_stock,$p->category_id,$p->status_id,$p->external_id,$p->image_path,$p->deleted_at]));}});
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
        ImportExportAudit::create(['admin_id'=>auth('admin')->id(),'operation'=>'export','entity'=>'products','file_name'=>$filename,'processed_rows'=>Product::withTrashed()->count(),'success_rows'=>Product::withTrashed()->count(),'failed_rows'=>0]);
        return $response;
    }

    public function exportExcel(Request $request)
    {
        $filename='products-'.now()->format('Ymd-His').'.xls';
        $count=Product::withTrashed()->count();
        $response=response()->streamDownload(function(){
            echo "<html><head><meta charset=\"UTF-8\"></head><body><table border=\"1\"><thead><tr>";
            foreach(['ID','SKU','Name','Price','Stock','Reserved','Sold','Category','Status','External ID','Image','Deleted At'] as $h) echo '<th>'.htmlspecialchars($h,ENT_QUOTES,'UTF-8').'</th>';
            echo "</tr></thead><tbody>";
            Product::withTrashed()->orderBy('id')->chunkById(500,function($products){
                foreach($products as $p){
                    $cells=[$p->id,$p->sku,$p->name,$p->price,$p->stock,$p->reserved_stock,$p->sold_stock,$p->category_id,$p->status_id,$p->external_id,$p->image_path,$p->deleted_at];
                    echo '<tr>';
                    foreach($cells as $cell){
                        $safe=CsvSanitizer::cell($cell);
                        echo '<td>'.htmlspecialchars((string)$safe,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td>';
                    }
                    echo '</tr>';
                }
            });
            echo "</tbody></table></body></html>";
        },$filename,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','X-Content-Type-Options'=>'nosniff']);
        ImportExportAudit::create(['admin_id'=>auth('admin')->id(),'operation'=>'export','entity'=>'products','file_name'=>$filename,'processed_rows'=>$count,'success_rows'=>$count,'failed_rows'=>0,'metadata'=>['format'=>'excel-compatible-html']]);
        return $response;
    }
}
