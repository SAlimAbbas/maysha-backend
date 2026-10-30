<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cod_fee_paise')) {
                $table->unsignedInteger('cod_fee_paise')->default(0)->after('shipping_paise');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cod_fee_paise')) {
                $table->dropColumn('cod_fee_paise');
            }
        });
    }
};

