<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Shipment;
use App\Services\Shiprocket\ShiprocketClient;

class GenerateLabelAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Generate and save shipping label URL.
     */
    public function execute(Shipment|int $shipmentOrId): string
    {
        $shipment = $shipmentOrId instanceof Shipment ? $shipmentOrId : Shipment::findOrFail($shipmentOrId);

        if (empty($shipment->sr_shipment_id)) {
            throw new ShiprocketApiException("Shipment {$shipment->id} has no valid sr_shipment_id.");
        }

        $response = $this->client->generateLabel([$shipment->sr_shipment_id]);
        $url = $response['label_url'] ?? ($response['response']['label_url'] ?? null);

        if ($url) {
            $shipment->update(['label_url' => $url]);
        }

        return (string) ($url ?: $shipment->label_url);
    }
}
