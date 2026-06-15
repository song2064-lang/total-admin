<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 상태머신 확장에 맞춰 기존 발송(shipped) 주문을 국내배송으로 보정
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'domestic_shipping']);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'domestic_shipping')->update(['status' => 'shipped']);
    }
};
