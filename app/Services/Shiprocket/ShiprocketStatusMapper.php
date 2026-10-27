<?php

namespace App\Services\Shiprocket;

class ShiprocketStatusMapper
{
    /**
     * Map Shiprocket raw status string or status code to internal status.
     */
    public static function map(string|int|null $srStatus): string
    {
        if ($srStatus === null) {
            return 'processing';
        }

        $normalized = strtoupper(trim((string) $srStatus));

        return match ($normalized) {
            // Processing / Initial
            'NEW', 'READY TO SHIP', 'READY_TO_SHIP', '1' => 'processing',
            'AWB ASSIGNED', 'AWB_ASSIGNED', 'PICKUP SCHEDULED', 'PICKUP_SCHEDULED',
            'PICKUP GENERATED', 'PICKUP QUEUED', 'MANIFEST GENERATED' => 'processing',

            // Shipped / In Transit
            'PICKED UP', 'PICKED_UP', 'IN TRANSIT', 'IN_TRANSIT', 'SHIPPED',
            'REACHED DESTINATION HUB', 'REACHED AT DESTINATION', 'OUT FOR PICKUP',
            'REACHED PICKUP HUB' => 'shipped',

            // Out for Delivery
            'OUT FOR DELIVERY', 'OUT_FOR_DELIVERY' => 'out_for_delivery',

            // Delivered
            'DELIVERED', '7' => 'delivered',

            // Undelivered / NDR
            'UNDELIVERED', 'NDR', 'DELIVERY DELAYED', 'FAILED', 'ATTEMPTED' => 'ndr',

            // RTO
            'RTO INITIATED', 'RTO_INITIATED', 'RTO IN TRANSIT', 'RTO_IN_TRANSIT',
            'RTO OFD', 'RTO OUT FOR DELIVERY', 'RTO' => 'rto',

            // RTO Delivered
            'RTO DELIVERED', 'RTO_DELIVERED' => 'rto_delivered',

            // Cancelled
            'CANCELED', 'CANCELLED' => 'cancelled',

            // Exception / Lost
            'LOST', 'DAMAGED', 'DESTROYED', 'DISPOSED' => 'exception',

            default => 'processing',
        };
    }

    /**
     * Map internal shipment status to internal Order status.
     */
    public static function toOrderStatus(string $shipmentStatus): string
    {
        return match ($shipmentStatus) {
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'ndr' => 'ndr',
            'rto' => 'rto',
            'rto_delivered' => 'rto_delivered',
            'cancelled' => 'cancelled',
            'exception' => 'exception',
            'shipped' => 'shipped',
            default => 'processing',
        };
    }

    /**
     * Determine if a status is terminal (no further forward progression).
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, ['delivered', 'rto_delivered', 'cancelled'], true);
    }
}
