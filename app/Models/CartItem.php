<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;
    protected $table='cart_items';
    protected $fillable=['cart_id','product_id','note','quantity_id','hash_key','quantity','price_per_unit','line_total','is_sample','image_design','design_file_id','customization_snapshot','pricing_snapshot'];
    protected $casts=['cart_id'=>'integer','product_id'=>'integer','quantity'=>'integer','price_per_unit'=>'decimal:4','line_total'=>'decimal:4','is_sample'=>'boolean','customization_snapshot'=>'array','pricing_snapshot'=>'array'];
    public function cart(){return $this->belongsTo(Cart::class);} public function product(){return $this->belongsTo(Product::class);} public function designFile(){return $this->belongsTo(UploadedFile::class,'design_file_id');}
    public function recalculateLineTotal(): void {$this->line_total=round((float)$this->price_per_unit*(int)$this->quantity,4);}
}
