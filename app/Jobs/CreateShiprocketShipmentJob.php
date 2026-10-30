<?php

namespace App\Jobs;

use App\Actions\Shiprocket\AssignCourierAction;
use App\Actions\Shiprocket\CreateShiprocketShipmentAction;
use App\Actions\Shiprocket\GenerateLabelAction;
use App\Actions\Shiprocket\SchedulePickupAction;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateShiprocketShipmentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(public int $orderId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900, 3600];
    }

    public function handle(
        CreateShiprocketShipmentAction $createShipmentAction,
        AssignCourierAction $assignCourierAction,
        SchedulePickupAction $schedulePickupAction,
        GenerateLabelAction $generateLabelAction
    ): void {
        if (app()->environment('testing') && ! (bool) SiteSetting::get('shiprocket_enabled', false)) {
            Log::info("Shiprocket is disabled in testing environment. Skipping shipment for order {$this->orderId}.");
            return;
        }

        $enabled = (bool) SiteSetting::get('shiprocket_enabled', env('SHIPROCKET_ENABLED', true));
        if (! $enabled) {
            Log::info("Shiprocket is disabled in settings. Skipping shipment for order {$this->orderId}.");
            return;
        }

        if (empty(config('services.shiprocket.email')) || empty(config('services.shiprocket.password'))) {
            Log::warning("Shiprocket credentials are not configured. Skipping shipment for order {$this->orderId}.");
            return;
        }

        $order = Order::find($this->orderId);
        if (! $order) {
            Log::error("Order {$this->orderId} not found in CreateShiprocketShipmentJob.");
            return;
        }

        // 1. Create Shipment
        $shipment = $createShipmentAction->execute($order);

        // 2. Check if auto create/fulfill mode is enabled
        $autoCreate = (bool) SiteSetting::get('shiprocket_auto_create', true);
        if (! $autoCreate) {
            Log::info("Shiprocket auto mode is off. Order {$order->reference} ready to ship in manual mode.");
            return;
        }

        // 3. Auto Assign Courier
        if (empty($shipment->awb_code)) {
            $shipment = $assignCourierAction->execute($shipment);
        }

        // 4. Auto Schedule Pickup
        if (empty($shipment->pickup_scheduled_at)) {
            $shipment = $schedulePickupAction->execute($shipment);
        }

        // 5. Auto Generate Label
        if (empty($shipment->label_url)) {
            $generateLabelAction->execute($shipment);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("CreateShiprocketShipmentJob failed permanently for order {$this->orderId}", [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);

        $order = Order::find($this->orderId);
        if ($order) {
            $order->events()->create([
                'status' => $order->status,
                'note' => 'Automatic Shiprocket fulfillment failed: '.$exception->getMessage(),
            ]);
        }
    }
}
