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
        // 1. Wishlists
        if (! Schema::hasTable('wishlists')) {
            Schema::create('wishlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'product_id']);
                $table->index('user_id');
                $table->index('product_id');
            });
        }

        // 2. Site Settings
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        // 3. Order Events (Timeline)
        if (! Schema::hasTable('order_events')) {
            Schema::create('order_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('status');
                $table->text('note')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('order_id');
                $table->index('actor_id');
            });
        }

        // 4. Refunds
        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->unsignedInteger('amount_paise');
                $table->string('reason')->nullable();
                $table->string('status')->default('pending'); // pending, processed, failed
                $table->string('gateway_refund_id')->nullable()->index();
                $table->timestamps();

                $table->index('payment_id');
                $table->index('order_id');
            });
        }

        // 5. Pincodes
        if (! Schema::hasTable('pincodes')) {
            Schema::create('pincodes', function (Blueprint $table) {
                $table->id();
                $table->string('pincode', 10)->unique();
                $table->string('city');
                $table->string('state');
                $table->boolean('serviceable')->default(true);
                $table->boolean('cod_available')->default(true);
                $table->unsignedInteger('eta_days')->default(3);
                $table->timestamps();

                $table->index(['pincode', 'serviceable']);
            });
        }

        // 6. Review Helpful Votes
        if (! Schema::hasTable('review_helpful_votes')) {
            Schema::create('review_helpful_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->unique(['review_id', 'user_id']);
                $table->index(['review_id', 'ip']);
                $table->index('review_id');
                $table->index('user_id');
            });
        }

        // 7. Add low_stock_threshold to product_variants
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                if (! Schema::hasColumn('product_variants', 'low_stock_threshold')) {
                    $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock');
                }
            });
        }

        // 8. Add order status tracking & GST fields to orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'tax_paise')) {
                    $table->unsignedInteger('tax_paise')->default(0)->after('shipping_paise');
                }
                if (! Schema::hasColumn('orders', 'invoice_no')) {
                    $table->string('invoice_no')->nullable()->unique()->after('idempotency_key');
                }
                if (! Schema::hasColumn('orders', 'tracking_no')) {
                    $table->string('tracking_no')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('orders', 'courier')) {
                    $table->string('courier')->nullable()->after('tracking_no');
                }
                if (! Schema::hasColumn('orders', 'placed_at')) {
                    $table->timestamp('placed_at')->nullable()->after('courier');
                }
                if (! Schema::hasColumn('orders', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('placed_at');
                }
                if (! Schema::hasColumn('orders', 'shipped_at')) {
                    $table->timestamp('shipped_at')->nullable()->after('paid_at');
                }
                if (! Schema::hasColumn('orders', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('shipped_at');
                }
            });
        }

        // 9. Add GST and SEO fields to products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'hsn_code')) {
                    $table->string('hsn_code')->nullable()->after('skin_types');
                }
                if (! Schema::hasColumn('products', 'gst_rate')) {
                    $table->unsignedTinyInteger('gst_rate')->default(18)->after('hsn_code');
                }
                if (! Schema::hasColumn('products', 'seo_title')) {
                    $table->string('seo_title')->nullable()->after('review_count');
                }
                if (! Schema::hasColumn('products', 'seo_description')) {
                    $table->text('seo_description')->nullable()->after('seo_title');
                }
            });
        }

        // 10. Add first_order_only to coupons
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (! Schema::hasColumn('coupons', 'first_order_only')) {
                    $table->boolean('first_order_only')->default(false)->after('per_user_limit');
                }
            });
        }

        // 11. Add image_media_id and SEO to categories
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'image_media_id')) {
                    $table->foreignId('image_media_id')->nullable()->after('image')->constrained('media')->nullOnDelete();
                }
                if (! Schema::hasColumn('categories', 'seo_title')) {
                    $table->string('seo_title')->nullable()->after('is_active');
                }
                if (! Schema::hasColumn('categories', 'seo_description')) {
                    $table->text('seo_description')->nullable()->after('seo_title');
                }
            });
        }

        // 12. Add image_media_id to concerns
        if (Schema::hasTable('concerns')) {
            Schema::table('concerns', function (Blueprint $table) {
                if (! Schema::hasColumn('concerns', 'image_media_id')) {
                    $table->foreignId('image_media_id')->nullable()->after('image')->constrained('media')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('concerns') && Schema::hasColumn('concerns', 'image_media_id')) {
            Schema::table('concerns', function (Blueprint $table) {
                $table->dropForeign(['image_media_id']);
                $table->dropColumn('image_media_id');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'image_media_id')) {
                    $table->dropForeign(['image_media_id']);
                    $table->dropColumn('image_media_id');
                }
                if (Schema::hasColumn('categories', 'seo_title')) {
                    $table->dropColumn('seo_title');
                }
                if (Schema::hasColumn('categories', 'seo_description')) {
                    $table->dropColumn('seo_description');
                }
            });
        }

        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'first_order_only')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('first_order_only');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['hsn_code', 'gst_rate', 'seo_title', 'seo_description']);
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn([
                    'tax_paise',
                    'invoice_no',
                    'tracking_no',
                    'courier',
                    'placed_at',
                    'paid_at',
                    'shipped_at',
                    'delivered_at',
                ]);
            });
        }

        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'low_stock_threshold')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('low_stock_threshold');
            });
        }

        Schema::dropIfExists('review_helpful_votes');
        Schema::dropIfExists('pincodes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('wishlists');
    }
};
