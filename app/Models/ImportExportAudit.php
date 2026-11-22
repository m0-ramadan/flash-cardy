<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportExportAudit extends Model
{
    protected $fillable=['admin_id','operation','entity','file_name','processed_rows','success_rows','failed_rows','errors','metadata'];
    protected $casts=['errors'=>'array','metadata'=>'array'];
    public function admin(){ return $this->belongsTo(Admin::class); }
}
