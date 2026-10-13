<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pincode;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    use ApiResponse;

    /**
     * Check delivery serviceability and COD availability for a given pincode.
     */
    public function checkPincode(string $pin): JsonResponse
    {
        $pin = trim($pin);

        if (! preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            return $this->error('Invalid 6-digit Indian PIN code format.', 422);
        }

        $result = CatalogCacheService::remember("shipping:pincode:{$pin}", ['shipping'], 86400, function () use ($pin) {
            $record = Pincode::where('pincode', $pin)->first();

            if (! $record) {
                // Default heuristic for serviceable pin in major zones if not explicitly in table
                return [
                    'pincode' => $pin,
                    'serviceable' => true,
                    'city' => null,
                    'state' => null,
                    'codAvailable' => true,
                    'estimatedDeliveryDays' => 4,
                ];
            }

            return [
                'pincode' => $record->pincode,
                'serviceable' => (bool) $record->serviceable,
                'city' => $record->city,
                'state' => $record->state,
                'codAvailable' => (bool) $record->cod_available,
                'estimatedDeliveryDays' => (int) $record->eta_days,
            ];
        });

        return $this->success($result, 'Pincode delivery details retrieved.');
    }
}
