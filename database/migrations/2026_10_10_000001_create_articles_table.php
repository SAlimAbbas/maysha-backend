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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('excerpt');
            $table->longText('content');
            $table->string('hero_image');
            $table->string('category')->index();
            $table->string('reading_time')->default('5 min read');
            $table->string('author')->default('Maysha Botanical Lab');
            $table->string('author_role')->default('Formulation & Research');
            $table->string('author_avatar')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->json('recommended_product_slugs')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
