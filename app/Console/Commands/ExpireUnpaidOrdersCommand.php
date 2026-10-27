<?php

namespace App\Console\Commands;

use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release stock and cancel abandoned pending-payment orders that exceeded reservation TTL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredOrders = Order::where('status', 'pending_payment')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', now())
            ->with(['items.variant'])
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired unpaid orders found.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order) {
                // Restore inventory stock for each reserved item
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    }
                }

                // Release coupon redemption if any so customer can reuse coupon
                CouponRedemption::where('order_id', $order->id)->delete();

                // Mark order as cancelled
                $order->update([
                    'status' => 'cancelled',
                    'reserved_until' => null,
                ]);

                // Record timeline audit event
                OrderEvent::create([
                    'order_id' => $order->id,
                    'status' => 'cancelled',
                    'note' => 'Order automatically cancelled: 30-minute payment reservation expired. Inventory returned to stock.',
                ]);
            });

            $count++;
            Log::info("Expired unpaid order {$order->reference} and restored variant stocks.");
        }

        $this->info("Successfully cancelled {$count} expired unpaid orders and restored inventory.");

        return self::SUCCESS;
    }
}
