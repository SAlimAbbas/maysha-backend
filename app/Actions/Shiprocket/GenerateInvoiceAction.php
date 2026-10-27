<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Shipment;
use App\Services\Shiprocket\ShiprocketClient;

class GenerateInvoiceAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Generate and save invoice URL from Shiprocket.
     */
    public function execute(Shipment|int $shipmentOrId): string
    {
        $shipment = $shipmentOrId instanceof Shipment ? $shipmentOrId : Shipment::findOrFail($shipmentOrId);

        if (empty($shipment->sr_order_id)) {
            throw new ShiprocketApiException("Shipment {$shipment->id} has no valid sr_order_id.");
        }

        $response = $this->client->generateInvoice([$shipment->sr_order_id]);
        $url = $response['invoice_url'] ?? ($response['response']['invoice_url'] ?? ($response['is_invoice_created'] ?? null));

        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $shipment->update(['invoice_url' => $url]);
        }

        return (string) ($shipment->invoice_url ?: ($url ?? ''));
    }
}
