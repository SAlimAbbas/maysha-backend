<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Shipment;
use App\Services\Shiprocket\ShiprocketClient;

class GenerateManifestAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Generate and save pickup manifest URL.
     */
    public function execute(Shipment|int $shipmentOrId): string
    {
        $shipment = $shipmentOrId instanceof Shipment ? $shipmentOrId : Shipment::findOrFail($shipmentOrId);

        if (empty($shipment->sr_shipment_id)) {
            throw new ShiprocketApiException("Shipment {$shipment->id} has no valid sr_shipment_id.");
        }

        $response = $this->client->generateManifest([$shipment->sr_shipment_id]);
        $url = $response['manifest_url'] ?? ($response['response']['manifest_url'] ?? null);

        if ($url) {
            $shipment->update(['manifest_url' => $url]);
        }

        return (string) ($url ?: $shipment->manifest_url);
    }
}
