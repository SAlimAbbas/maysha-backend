<?php

namespace App\Console\Commands;

use App\Models\Pincode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportPincodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maysha:import-pincodes {csv : Absolute or relative path to the pincodes CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import or update Indian delivery pincodes from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csvPath = $this->argument('csv');

        if (! File::exists($csvPath)) {
            $this->error("CSV file not found at: {$csvPath}");

            return self::FAILURE;
        }

        $file = fopen($csvPath, 'r');
        if (! $file) {
            $this->error("Could not open file: {$csvPath}");

            return self::FAILURE;
        }

        $header = fgetcsv($file);
        if (! $header) {
            $this->error('CSV file is empty or missing headers.');
            fclose($file);

            return self::FAILURE;
        }

        // Normalize header keys
        $headerMap = array_flip(array_map(fn ($col) => strtolower(trim($col)), $header));

        $imported = 0;
        $this->info("Starting pincode import from {$csvPath}...");

        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || count($row) < 3) {
                continue;
            }

            $pin = trim($row[$headerMap['pincode'] ?? 0] ?? '');
            if (! preg_match('/^[1-9][0-9]{5}$/', $pin)) {
                continue;
            }

            $city = trim($row[$headerMap['city'] ?? 1] ?? '');
            $state = trim($row[$headerMap['state'] ?? 2] ?? '');
            $serviceable = isset($headerMap['serviceable']) ? filter_var($row[$headerMap['serviceable']], FILTER_VALIDATE_BOOLEAN) : true;
            $codAvailable = isset($headerMap['cod_available']) ? filter_var($row[$headerMap['cod_available']], FILTER_VALIDATE_BOOLEAN) : true;
            $etaDays = isset($headerMap['eta_days']) ? (int) $row[$headerMap['eta_days']] : 3;

            Pincode::updateOrCreate(
                ['pincode' => $pin],
                [
                    'city' => $city,
                    'state' => $state,
                    'serviceable' => $serviceable,
                    'cod_available' => $codAvailable,
                    'eta_days' => max(1, $etaDays),
                ]
            );

            $imported++;
        }

        fclose($file);
        $this->info("Successfully imported/updated {$imported} pincodes.");

        return self::SUCCESS;
    }
}
