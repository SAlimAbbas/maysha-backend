<?php

namespace Database\Seeders;

use App\Models\Pincode;
use Illuminate\Database\Seeder;

class PincodeSeeder extends Seeder
{
    public function run(): void
    {
        $pincodes = [
            // Metro Cities (Fast Delivery)
            ['pincode' => '110001', 'city' => 'New Delhi', 'state' => 'Delhi', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '110020', 'city' => 'South Delhi', 'state' => 'Delhi', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '400001', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '400050', 'city' => 'Bandra Mumbai', 'state' => 'Maharashtra', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '560001', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '560038', 'city' => 'Indiranagar Bengaluru', 'state' => 'Karnataka', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '500001', 'city' => 'Hyderabad', 'state' => 'Telangana', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '600001', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 2],
            ['pincode' => '700001', 'city' => 'Kolkata', 'state' => 'West Bengal', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],

            // Tier 2 Hubs
            ['pincode' => '411001', 'city' => 'Pune', 'state' => 'Maharashtra', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '380001', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '302001', 'city' => 'Jaipur', 'state' => 'Rajasthan', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '226001', 'city' => 'Lucknow', 'state' => 'Uttar Pradesh', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '160001', 'city' => 'Chandigarh', 'state' => 'Punjab', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '682001', 'city' => 'Kochi', 'state' => 'Kerala', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '800001', 'city' => 'Patna', 'state' => 'Bihar', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 4],
            ['pincode' => '452001', 'city' => 'Indore', 'state' => 'Madhya Pradesh', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],
            ['pincode' => '395001', 'city' => 'Surat', 'state' => 'Gujarat', 'serviceable' => true, 'cod_available' => true, 'eta_days' => 3],

            // Non-COD Pin (Prepaid Only)
            ['pincode' => '190001', 'city' => 'Srinagar', 'state' => 'Jammu and Kashmir', 'serviceable' => true, 'cod_available' => false, 'eta_days' => 5],

            // Unserviceable Pin (For testing boundary / remote non-serviceable)
            ['pincode' => '799999', 'city' => 'Remote Outpost', 'state' => 'Arunachal Pradesh', 'serviceable' => false, 'cod_available' => false, 'eta_days' => 10],
        ];

        foreach ($pincodes as $data) {
            Pincode::updateOrCreate(
                ['pincode' => $data['pincode']],
                $data
            );
        }
    }
}
