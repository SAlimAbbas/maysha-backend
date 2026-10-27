<?php

namespace App\Console\Commands;

use App\Actions\Shiprocket\SyncShipmentTrackingAction;
use App\Models\Shipment;
use Illuminate\Console\Command;

class ReconcileShipmentsCommand extends Command
{
    protected $signature = 'shipments:reconcile {--limit=50 : Maximum shipments to reconcile}';

    protected $description = 'Poll tracking for stale active shipments to heal missed webhooks';

    public function handle(SyncShipmentTrackingAction $syncAction): int
    {
        $limit = (int) $this->option('limit');

        $staleShipments = Shipment::whereNotIn('status', ['delivered', 'cancelled', 'rto_delivered'])
            ->whereNotNull('awb_code')
            ->where(function ($query) {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHours(2));
            })
            ->limit($limit)
            ->get();

        $this->info("Found {$staleShipments->count()} stale active shipments to reconcile.");

        $reconciled = 0;
        foreach ($staleShipments as $shipment) {
            try {
                $this->line("Reconciling shipment #{$shipment->id} (AWB: {$shipment->awb_code})...");
                $syncAction->execute($shipment);
                $reconciled++;
            } catch (\Throwable $e) {
                $this->warn("Failed reconciling shipment #{$shipment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully reconciled {$reconciled} shipments.");

        return 0;
    }
}
