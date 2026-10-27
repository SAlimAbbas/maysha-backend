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
        // 1. Add package weight and dimensions to product_variants
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'weight_grams')) {
                $table->unsignedInteger('weight_grams')->nullable()->after('stock');
            }
            if (! Schema::hasColumn('product_variants', 'length_cm')) {
                $table->decimal('length_cm', 8, 2)->nullable()->after('weight_grams');
            }
            if (! Schema::hasColumn('product_variants', 'breadth_cm')) {
                $table->decimal('breadth_cm', 8, 2)->nullable()->after('length_cm');
            }
            if (! Schema::hasColumn('product_variants', 'height_cm')) {
                $table->decimal('height_cm', 8, 2)->nullable()->after('breadth_cm');
            }
        });

        // 2. Create shipments table
        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('provider', 32)->default('shiprocket');
                $table->unsignedBigInteger('sr_order_id')->nullable();
                $table->unsignedBigInteger('sr_shipment_id')->nullable();
                $table->string('awb_code', 64)->nullable()->index();
                $table->unsignedBigInteger('courier_id')->nullable();
                $table->string('courier_name')->nullable();
                $table->string('status', 32)->default('draft')->index(); // draft, ready_to_ship, awb_assigned, pickup_scheduled, in_transit, out_for_delivery, delivered, cancelled, rto, ndr, exception
                $table->string('sr_status')->nullable();
                $table->integer('sr_status_code')->nullable();
                $table->text('label_url')->nullable();
                $table->text('manifest_url')->nullable();
                $table->text('invoice_url')->nullable();
                $table->text('tracking_url')->nullable();
                $table->timestamp('pickup_scheduled_at')->nullable();
                $table->string('pickup_token')->nullable();
                $table->unsignedInteger('shipping_charge_paise')->default(0);
                $table->unsignedInteger('cod_charge_paise')->default(0);
                $table->unsignedInteger('weight_grams')->nullable();
                $table->decimal('length_cm', 8, 2)->nullable();
                $table->decimal('breadth_cm', 8, 2)->nullable();
                $table->decimal('height_cm', 8, 2)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->boolean('is_return')->default(false);
                $table->unsignedBigInteger('parent_shipment_id')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
                $table->unique(['provider', 'sr_shipment_id']);
            });
        }

        // 3. Create shipment_events table (timeline and webhook updates)
        if (! Schema::hasTable('shipment_events')) {
            Schema::create('shipment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
                $table->string('status', 32);
                $table->string('sr_status')->nullable();
                $table->string('location')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->string('source', 32)->default('webhook'); // webhook, poll, admin
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique(['shipment_id', 'sr_status', 'occurred_at'], 'uniq_shipment_event_idempotent');
                $table->index(['shipment_id', 'occurred_at']);
            });
        }

        // 4. Create shipping_webhook_logs table
        if (! Schema::hasTable('shipping_webhook_logs')) {
            Schema::create('shipping_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 32)->default('shiprocket');
                $table->string('event_type', 64)->nullable();
                $table->string('body_hash', 64)->nullable()->index();
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->boolean('processed')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_logs');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');

        Schema::table('product_variants', function (Blueprint $table) {
            $cols = ['weight_grams', 'length_cm', 'breadth_cm', 'height_cm'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('product_variants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
