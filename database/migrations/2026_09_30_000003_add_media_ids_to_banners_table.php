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
        Schema::table('banners', function (Blueprint $table) {
            $table->foreignId('desktop_media_id')->nullable()->after('subtitle')->constrained('media')->nullOnDelete();
            $table->foreignId('mobile_media_id')->nullable()->after('desktop_media_id')->constrained('media')->nullOnDelete();
            $table->string('desktop_image')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropForeign(['desktop_media_id']);
            $table->dropForeign(['mobile_media_id']);
            $table->dropColumn(['desktop_media_id', 'mobile_media_id']);
        });
    }
};
