<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckShiprocketWalletCommand extends Command
{
    protected $signature = 'shiprocket:check-wallet';

    protected $description = 'Check live Shiprocket wallet balance against low wallet threshold';

    public function handle(ShiprocketClient $client): int
    {
        if (empty(config('services.shiprocket.email')) || empty(config('services.shiprocket.password'))) {
            $this->warn('Shiprocket credentials are not configured. Skipping wallet check.');
            return 0;
        }

        try {
            $response = $client->getWalletBalance();
            $balance = (float) ($response['data']['balance_amount'] ?? ($response['balance'] ?? 0.0));
            $threshold = (float) (SiteSetting::get('low_wallet_threshold', 500) ?? 500);

            $this->info("Shiprocket Wallet Balance: ₹".number_format($balance, 2));

            if ($balance < $threshold) {
                $msg = "Shiprocket wallet balance is LOW: ₹".number_format($balance, 2)." (Threshold: ₹".number_format($threshold, 2)."). Please recharge your wallet.";
                $this->warn($msg);
                Log::warning($msg, [
                    'balance' => $balance,
                    'threshold' => $threshold,
                ]);
            } else {
                $this->info("Wallet balance is healthy (Above threshold ₹".number_format($threshold, 2).").");
            }
        } catch (\Throwable $e) {
            $this->error("Failed to check Shiprocket wallet balance: {$e->getMessage()}");
            Log::error("Failed to check Shiprocket wallet balance: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
