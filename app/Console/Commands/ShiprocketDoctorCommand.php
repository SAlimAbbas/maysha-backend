<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Console\Command;

class ShiprocketDoctorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shiprocket:doctor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Shiprocket credentials, pickup location, webhook token, and catalog dimensions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('        MAYSHA SHIPROCKET INTEGRATION AUDIT         ');
        $this->info('====================================================');

        $hasError = false;
        $rows = [];

        // 1. Environment Credentials Check
        $email = config('services.shiprocket.email');
        $password = config('services.shiprocket.password');
        $webhookToken = config('services.shiprocket.webhook_token');
        $baseUrl = config('services.shiprocket.base_url');

        $rows[] = [
            'API Email',
            $email ? 'CONFIGURED ('.substr($email, 0, 3).'***)' : 'NOT CONFIGURED (Required for live shipments)',
            $email ? 'OK' : 'WARN',
        ];

        $rows[] = [
            'API Password',
            $password ? 'CONFIGURED ('.strlen($password).' chars)' : 'NOT CONFIGURED (Required for live shipments)',
            $password ? 'OK' : 'WARN',
        ];

        $rows[] = [
            'Webhook Secret Token',
            $webhookToken ? 'CONFIGURED' : 'MISSING',
            $webhookToken ? 'OK' : 'WARN',
        ];

        // 2. Settings Check
        $pickupLoc = SiteSetting::get('shiprocket_pickup_location', 'Primary');
        $rows[] = [
            'Configured Pickup Location',
            (string) $pickupLoc,
            $pickupLoc ? 'OK' : 'WARN',
        ];

        $courierStrategy = SiteSetting::get('shiprocket_courier_strategy', 'cheapest');
        $rows[] = [
            'Courier Strategy',
            (string) $courierStrategy,
            'OK',
        ];

        // 3. Live Shiprocket Connection Check (if credentials provided)
        if ($email && $password) {
            try {
                /** @var ShiprocketClient $client */
                $client = app(ShiprocketClient::class);
                $token = $client->getToken();
                $rows[] = [
                    'Gateway Authentication',
                    'Token acquired successfully',
                    'OK',
                ];

                // Check wallet balance
                try {
                    $wallet = $client->getWalletBalance();
                    $balanceRupees = $wallet['data']['balance_amount'] ?? 'N/A';
                    $rows[] = [
                        'Wallet Balance',
                        "₹{$balanceRupees}",
                        (is_numeric($balanceRupees) && $balanceRupees < 500) ? 'WARN' : 'OK',
                    ];
                } catch (\Throwable $e) {
                    $rows[] = ['Wallet Balance', 'Could not query ('.$e->getMessage().')', 'WARN'];
                }
            } catch (\Throwable $e) {
                $rows[] = [
                    'Gateway Authentication',
                    'Failed: '.$e->getMessage(),
                    'FAIL',
                ];
                $hasError = true;
            }
        } else {
            $rows[] = [
                'Gateway Authentication',
                'Skipped (credentials missing in environment)',
                'WARN',
            ];
        }

        // 4. Catalog Package Dimensions Check
        $variantsWithoutDims = ProductVariant::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('weight_grams')
                    ->orWhereNull('length_cm')
                    ->orWhereNull('breadth_cm')
                    ->orWhereNull('height_cm');
            })
            ->count();

        $defaultWeight = SiteSetting::get('default_weight_grams', 250);
        $defaultLength = SiteSetting::get('default_length_cm', 10);
        $defaultBreadth = SiteSetting::get('default_breadth_cm', 10);
        $defaultHeight = SiteSetting::get('default_height_cm', 10);

        if ($variantsWithoutDims > 0) {
            $rows[] = [
                'Variant Dimensions',
                "{$variantsWithoutDims} active variants rely on fallback defaults ({$defaultWeight}g, {$defaultLength}x{$defaultBreadth}x{$defaultHeight}cm)",
                'INFO',
            ];
        } else {
            $rows[] = [
                'Variant Dimensions',
                'All active product variants have explicit package weights and dimensions',
                'OK',
            ];
        }

        $this->table(['Check', 'Details', 'Status'], $rows);

        if ($hasError) {
            $this->error('Shiprocket integration has configuration errors. Please check the details above.');

            return self::FAILURE;
        }

        $this->info('Shiprocket integration doctor checks completed.');

        return self::SUCCESS;
    }
}
