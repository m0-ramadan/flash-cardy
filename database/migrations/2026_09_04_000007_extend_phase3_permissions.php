<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) return;
        $modules=['inventory','fulfillments','refunds','reviews']; $actions=['view','create','edit','delete','manage','export','import']; $now=now();
        foreach($modules as $module) foreach($actions as $action) DB::table('permissions')->updateOrInsert(
            ['name'=>"{$module}.{$action}",'guard_name'=>'admin'],
            ['display_name'=>"{$module} {$action}",'description'=>"{$action} {$module}",'module'=>$module,'updated_at'=>$now,'created_at'=>$now]
        );
        $roleModules=[
            'product_manager'=>['inventory'],
            'orders_manager'=>['inventory','fulfillments','reviews'],
            'finance'=>['refunds','reports'],
            'support'=>['reviews'],
            'content_manager'=>['reviews'],
        ];
        foreach($roleModules as $role=>$mods){
            $roleId=DB::table('roles')->where('name',$role)->where('guard_name','admin')->value('id'); if(!$roleId) continue;
            foreach(DB::table('permissions')->where('guard_name','admin')->whereIn('module',$mods)->pluck('id') as $pid) DB::table('role_has_permissions')->updateOrInsert(['permission_id'=>$pid,'role_id'=>$roleId]);
        }
        foreach(['super_admin','admin'] as $role){
            $roleId=DB::table('roles')->where('name',$role)->where('guard_name','admin')->value('id'); if(!$roleId) continue;
            foreach(DB::table('permissions')->where('guard_name','admin')->whereIn('module',$modules)->pluck('id') as $pid) DB::table('role_has_permissions')->updateOrInsert(['permission_id'=>$pid,'role_id'=>$roleId]);
        }
        if(class_exists(\Spatie\Permission\PermissionRegistrar::class)) app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void { /* permissions are intentionally preserved to avoid lockout */ }
};
