<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Concern;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchemaAndMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_additive_ecommerce_tables_and_columns_exist(): void
    {
        // 1. Tables exist
        $this->assertTrue(Schema::hasTable('wishlists'));
        $this->assertTrue(Schema::hasTable('site_settings'));
        $this->assertTrue(Schema::hasTable('order_events'));
        $this->assertTrue(Schema::hasTable('refunds'));
        $this->assertTrue(Schema::hasTable('pincodes'));
        $this->assertTrue(Schema::hasTable('review_helpful_votes'));

        // 2. User auth columns exist
        $this->assertTrue(Schema::hasColumns('users', [
            'google_id',
            'avatar',
            'role',
            'phone',
            'last_login_at',
        ]));

        // 3. Variant column exists
        $this->assertTrue(Schema::hasColumn('product_variants', 'low_stock_threshold'));

        // 4. Order columns exist
        $this->assertTrue(Schema::hasColumns('orders', [
            'tax_paise',
            'invoice_no',
            'tracking_no',
            'courier',
            'placed_at',
            'paid_at',
            'shipped_at',
            'delivered_at',
        ]));

        // 5. Product columns exist
        $this->assertTrue(Schema::hasColumns('products', [
            'hsn_code',
            'gst_rate',
            'seo_title',
            'seo_description',
        ]));

        // 6. Coupon columns exist
        $this->assertTrue(Schema::hasColumn('coupons', 'first_order_only'));

        // 7. Category and Concern columns exist
        $this->assertTrue(Schema::hasColumns('categories', [
            'image_media_id',
            'seo_title',
            'seo_description',
        ]));
        $this->assertTrue(Schema::hasColumn('concerns', 'image_media_id'));
    }

    public function test_new_models_and_relationships(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $category = Category::create([
            'name' => 'Serums',
            'slug' => 'serums',
            'seo_title' => 'Face Serums',
            'seo_description' => 'Best facial serums',
        ]);
        $concern = Concern::create([
            'name' => 'Acne',
            'slug' => 'acne',
        ]);

        $product = Product::create([
            'name' => 'Salicylic Acid 2%',
            'slug' => 'salicylic-acid-2',
            'category_id' => $category->id,
            'concern_id' => $concern->id,
            'hsn_code' => '33049910',
            'gst_rate' => 18,
            'seo_title' => 'Salicylic Acid 2% Serum',
            'seo_description' => 'Gentle exfoliant for acne',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-SA-30',
            'size_label' => '30ml',
            'price_paise' => 49900,
            'mrp_paise' => 59900,
            'stock' => 15,
            'low_stock_threshold' => 5,
        ]);

        // Wishlist relation
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $this->assertEquals($product->id, $user->wishlist()->first()->product_id);
        $this->assertEquals($user->id, $product->wishlists()->first()->user_id);

        // Site Setting get / set
        SiteSetting::set('free_shipping_threshold_paise', 99900);
        $this->assertEquals(99900, SiteSetting::get('free_shipping_threshold_paise'));

        // Order with new fields and events
        $order = Order::create([
            'reference' => 'MAY-TEST01',
            'user_id' => $user->id,
            'status' => 'paid',
            'subtotal_paise' => 49900,
            'discount_paise' => 0,
            'shipping_paise' => 0,
            'tax_paise' => 7611,
            'total_paise' => 49900,
            'invoice_no' => 'INV-2026-0001',
            'tracking_no' => 'TRK123456',
            'courier' => 'BlueDart',
            'placed_at' => now(),
            'paid_at' => now(),
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'shipping_address' => [
                'name' => 'Test User',
                'line1' => '123 Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
            ],
        ]);

        $event = OrderEvent::create([
            'order_id' => $order->id,
            'status' => 'paid',
            'note' => 'Payment received via Razorpay',
        ]);
        $this->assertCount(1, $order->events);

        // Refund model
        $refund = Refund::create([
            'order_id' => $order->id,
            'amount_paise' => 49900,
            'reason' => 'Customer requested',
            'status' => 'processed',
        ]);
        $this->assertCount(1, $order->refunds);
        $this->assertEquals(499, $refund->amount_rupees);

        // Pincode model
        $pincode = Pincode::create([
            'pincode' => '400001',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'serviceable' => true,
            'cod_available' => true,
            'eta_days' => 2,
        ]);
        $this->assertTrue($pincode->serviceable);

        // Review helpful vote
        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'author_name' => 'Tester',
            'rating' => 5,
            'comment' => 'Great product!',
            'is_approved' => true,
        ]);

        $vote = ReviewHelpfulVote::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
        ]);
        $this->assertCount(1, $review->helpfulVotes);

        // Spatie roles on User
        Role::create(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole('customer');
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_coupon_first_order_only_logic(): void
    {
        $user = User::factory()->create();

        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => 10,
            'min_subtotal_paise' => 10000,
            'first_order_only' => true,
            'is_active' => true,
        ]);

        // User with no paid/completed orders can use it
        $this->assertTrue($coupon->isValidFor(20000, $user));

        // Create an existing completed order for user
        Order::create([
            'reference' => 'MAY-EXIST01',
            'user_id' => $user->id,
            'status' => 'paid',
            'subtotal_paise' => 20000,
            'total_paise' => 20000,
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'shipping_address' => ['city' => 'Delhi'],
        ]);

        // Now first_order_only fails for this user
        $this->assertFalse($coupon->isValidFor(20000, $user));
    }

    public function test_legacy_users_table_upgrade_scenario(): void
    {
        // Create an old-style legacy users table
        Schema::dropIfExists('legacy_users');
        Schema::create('legacy_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Verify missing auth columns
        $this->assertFalse(Schema::hasColumn('legacy_users', 'google_id'));
        $this->assertFalse(Schema::hasColumn('legacy_users', 'role'));
        $this->assertFalse(Schema::hasColumn('legacy_users', 'avatar'));

        // Apply additive logic to legacy table
        Schema::table('legacy_users', function (Blueprint $table) {
            if (! Schema::hasColumn('legacy_users', 'google_id')) {
                $table->string('google_id')->nullable()->index()->after('email');
            }
            if (! Schema::hasColumn('legacy_users', 'avatar')) {
                $table->string('avatar')->nullable()->after('google_id');
            }
            if (! Schema::hasColumn('legacy_users', 'role')) {
                $table->string('role')->default('customer')->index()->after('avatar');
            }
            if (! Schema::hasColumn('legacy_users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('role');
            }
            if (! Schema::hasColumn('legacy_users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('phone');
            }
        });

        // Verify columns are now present
        $this->assertTrue(Schema::hasColumns('legacy_users', [
            'google_id',
            'avatar',
            'role',
            'phone',
            'last_login_at',
        ]));

        Schema::dropIfExists('legacy_users');
    }
}
