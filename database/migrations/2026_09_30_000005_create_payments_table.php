<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for payments.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway')->default('razorpay'); // razorpay, cod
            $table->string('gateway_order_id')->nullable()->index(); // Razorpay order_id (e.g. order_EKfwwad3...)
            $table->string('gateway_payment_id')->nullable()->index(); // Razorpay payment_id (e.g. pay_29QQ...)
            $table->string('gateway_signature')->nullable();
            $table->enum('status', ['created', 'authorized', 'captured', 'refunded', 'failed'])->default('created')->index();
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->json('raw_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
