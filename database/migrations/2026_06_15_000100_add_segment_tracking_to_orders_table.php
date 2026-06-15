<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 택배 3구간 분리. 기존 tracking_carrier/tracking_no 는 국내(한국) 구간으로 유지
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_local_carrier', 50)->nullable()->after('status');
            $table->string('tracking_local_no', 64)->nullable()->after('tracking_local_carrier');
            $table->string('tracking_intl_carrier', 50)->nullable()->after('tracking_local_no');
            $table->string('tracking_intl_no', 64)->nullable()->after('tracking_intl_carrier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_local_carrier',
                'tracking_local_no',
                'tracking_intl_carrier',
                'tracking_intl_no',
            ]);
        });
    }
};
