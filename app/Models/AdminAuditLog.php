<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminAuditLog extends Model
{
    protected $fillable = ['admin_id','route_name','method','path','action','response_status','request_summary','ip_address','user_agent'];
    protected $casts = ['request_summary' => 'array'];
}
