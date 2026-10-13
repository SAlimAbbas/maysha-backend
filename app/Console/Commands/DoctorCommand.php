<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maysha:doctor {--database= : Specify database connection to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform diagnostic health and configuration checks on the Maysha backend environment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=============================================');
        $this->info('  Maysha Backend System Health & Diagnostics ');
        $this->info('=============================================');

        $hasError = false;

        // 1. Environment & Debug
        $env = app()->environment();
        $debug = config('app.debug') ? 'TRUE' : 'FALSE';
        $this->line("Environment: <comment>{$env}</comment> | Debug: <comment>{$debug}</comment>");

        // 2. Database Connection
        $this->newLine();
        $this->line('<fg=cyan>1. Database Connection & Schema</>');
        $connName = $this->option('database') ?: config('database.default');
        try {
            $connection = DB::connection($connName);
            $connection->getPdo();
            $dbName = $connection->getDatabaseName();
            $driver = $connection->getDriverName();
            $this->info("  ✓ Connected successfully to database: {$dbName} ({$driver})");
        } catch (\Throwable $e) {
            $this->error("  ✗ Database connection failed: {$e->getMessage()}");
            $hasError = true;

            return self::FAILURE;
        }

        // Required Tables Check
        $requiredTables = [
            'users',
            'banners',
            'categories',
            'concerns',
            'media',
            'products',
            'product_variants',
            'product_media',
            'reviews',
            'addresses',
            'coupons',
            'coupon_redemptions',
            'carts',
            'cart_items',
            'orders',
            'order_items',
            'payments',
            'audit_logs',
            'newsletter_subscribers',
            'contact_messages',
            'personal_access_tokens',
            'wishlists',
            'site_settings',
            'order_events',
            'refunds',
            'pincodes',
            'review_helpful_votes',
        ];

        $missingTables = [];
        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if (empty($missingTables)) {
            $this->info('  ✓ All 27 core e-commerce tables exist in the database.');
        } else {
            $this->error('  ✗ Missing database tables: '.implode(', ', $missingTables));
            $this->warn('    Run: php artisan migrate --force');
            $hasError = true;
        }

        // Key Columns Check
        $requiredColumns = [
            'users' => ['role', 'google_id', 'avatar', 'phone', 'last_login_at'],
            'product_variants' => ['low_stock_threshold', 'price_paise', 'mrp_paise', 'stock'],
            'orders' => ['tax_paise', 'invoice_no', 'tracking_no', 'courier', 'placed_at', 'paid_at'],
            'products' => ['hsn_code', 'gst_rate', 'seo_title', 'seo_description'],
            'coupons' => ['first_order_only'],
            'categories' => ['image_media_id'],
            'concerns' => ['image_media_id'],
        ];

        $missingColumns = [];
        foreach ($requiredColumns as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missingColumns[] = "{$table}.{$column}";
                    }
                }
            }
        }

        if (empty($missingColumns)) {
            $this->info('  ✓ All critical e-commerce and auth columns verified.');
        } else {
            $this->error('  ✗ Missing key columns: '.implode(', ', $missingColumns));
            $this->warn('    Run: php artisan migrate --force');
            $hasError = true;
        }

        // 3. Storage Symlink
        $this->newLine();
        $this->line('<fg=cyan>2. Filesystem & Storage Link</>');
        $publicStorage = public_path('storage');
        if (File::exists($publicStorage) || is_link($publicStorage)) {
            $this->info('  ✓ Public storage link exists at public/storage.');
        } else {
            $this->warn('  ! Public storage link missing. Run: php artisan storage:link');
        }

        // 4. Redis Connection (Only if Redis is configured)
        $this->newLine();
        $this->line('<fg=cyan>3. Cache, Session, & Queue Drivers</>');
        $cacheDriver = config('cache.default');
        $sessionDriver = config('session.driver');
        $queueDriver = config('queue.default');
        $this->line("  Cache: <comment>{$cacheDriver}</comment> | Session: <comment>{$sessionDriver}</comment> | Queue: <comment>{$queueDriver}</comment>");

        $usesRedis = in_array('redis', [$cacheDriver, $sessionDriver, $queueDriver]);
        if ($usesRedis) {
            try {
                Redis::ping();
                $this->info('  ✓ Redis connection successful (PONG).');
            } catch (\Throwable $e) {
                $this->error("  ✗ Redis is configured but unreachable: {$e->getMessage()}");
                $this->warn('    Tip for local dev: Use CACHE_STORE=file, SESSION_DRIVER=file, QUEUE_CONNECTION=sync');
                $hasError = true;
            }
        } else {
            $this->info('  ✓ Local drivers active (file/database/sync). No Redis connection required.');
        }

        // 5. External Service Integrations (Booleans only, no secrets leaked)
        $this->newLine();
        $this->line('<fg=cyan>4. Service Credentials Presence (Safe boolean checks)</>');

        $services = [
            'App Key' => ! empty(config('app.key')),
            'Razorpay Key ID' => ! empty(config('services.razorpay.key')),
            'Razorpay Secret' => ! empty(config('services.razorpay.secret')),
            'Cloudflare Account ID' => ! empty(config('services.cloudflare.account_id')),
            'Cloudflare API Token' => ! empty(config('services.cloudflare.api_token')),
            'Mail Host / Mailer' => ! empty(config('mail.mailers.smtp.host')) || config('mail.default') === 'log',
            'Google Client ID' => ! empty(config('services.google.client_id')),
        ];

        foreach ($services as $service => $isConfigured) {
            if ($isConfigured) {
                $this->info("  ✓ {$service}: Configured");
            } else {
                $this->line("  - {$service}: Not set (Optional in local dev)");
            }
        }

        $this->newLine();
        if ($hasError) {
            $this->error('Diagnostics completed with issues. Please resolve the errors above.');

            return self::FAILURE;
        }

        $this->info('✓ All system checks passed successfully! Backend is ready.');

        return self::SUCCESS;
    }
}
