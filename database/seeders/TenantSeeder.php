<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'villa-boutanga'],
            [
                'name' => 'Villa Boutanga',
                'address' => 'Bangoulap, Ouest Cameroun',
                'phone' => '+237 699 000 000',
                'email' => 'contact@villaboutanga.cm',
                'currency' => 'XAF',
                'settings' => [
                    'checkin_time' => '14:00',
                    'checkout_time' => '11:00',
                    'tax_rate' => 19.25,
                ],
                'is_active' => true,
            ]
        );
    }
}
