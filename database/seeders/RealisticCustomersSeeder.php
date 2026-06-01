<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class RealisticCustomersSeeder extends Seeder
{
    public function run()
    {
        $customers = [
            [
                'name' => 'Saudi Aramco',
                'contact_person' => 'Ahmed Al-Rashid',
                'email' => 'ahmed.r@aramco.com',
                'phone' => '+966-13-872-0115',
                'tax_id' => 'SA-300005463500003',
                'billing_address' => 'Saudi Aramco HQ, Dhahran 31311, Saudi Arabia',
                'shipping_address' => 'Ras Tanura Refinery, Saudi Arabia',
            ],
            [
                'name' => 'Bechtel Corporation',
                'contact_person' => 'James O\'Brien',
                'email' => 'jobrien@bechtel.com',
                'phone' => '+1-415-768-1234',
                'tax_id' => 'US-94-1687095',
                'billing_address' => '50 Beale St, San Francisco, CA 94105, USA',
                'shipping_address' => '12011 Sunset Hills Rd, Reston, VA 20190, USA',
            ],
            [
                'name' => 'Fluor Corporation',
                'contact_person' => 'Diana Marks',
                'email' => 'd.marks@fluor.com',
                'phone' => '+1-469-398-7000',
                'tax_id' => 'US-95-0740960',
                'billing_address' => '6700 Las Colinas Blvd, Irving, TX 75039, USA',
                'shipping_address' => 'Fluor Project Site, Houston, TX, USA',
            ],
            [
                'name' => 'SABIC Global',
                'contact_person' => 'Khalid Al-Otaibi',
                'email' => 'k.otaibi@sabic.com',
                'phone' => '+966-1-225-8000',
                'tax_id' => 'SA-300001164500003',
                'billing_address' => 'SABIC HQ, Riyadh 11422, Saudi Arabia',
                'shipping_address' => 'Al Jubail Industrial City, Saudi Arabia',
            ],
            [
                'name' => 'ADNOC Group',
                'contact_person' => 'Fatima Al-Mazrouei',
                'email' => 'fatima.m@adnoc.ae',
                'phone' => '+971-2-707-0000',
                'tax_id' => 'AE-100345678',
                'billing_address' => 'ADNOC HQ, Abu Dhabi, UAE',
                'shipping_address' => 'Ruwais Industrial Complex, UAE',
            ],
            [
                'name' => 'Chevron Phillips Chem.',
                'contact_person' => 'Robert Kline',
                'email' => 'r.kline@cpchem.com',
                'phone' => '+1-832-813-4100',
                'tax_id' => 'US-76-0451430',
                'billing_address' => '10001 Six Pines Dr, The Woodlands, TX 77380, USA',
                'shipping_address' => 'Cedar Bayou Plant, Baytown, TX, USA',
            ],
            [
                'name' => 'Larsen & Toubro Ltd.',
                'contact_person' => 'Rajesh Sharma',
                'email' => 'r.sharma@larsentoubro.com',
                'phone' => '+91-22-6752-5656',
                'tax_id' => 'IN-17-0610906-C',
                'billing_address' => 'L&T House, N.M. Marg, Mumbai 400001, India',
                'shipping_address' => 'L&T Heavy Engineering, Hazira, Surat, India',
            ],
            [
                'name' => 'TechnipFMC plc',
                'contact_person' => 'Sophie Laurent',
                'email' => 's.laurent@technipfmc.com',
                'phone' => '+1-281-260-3600',
                'tax_id' => 'US-98-1283037',
                'billing_address' => '11740 Katy Fwy, Houston, TX 77079, USA',
                'shipping_address' => 'Various Offshore Project Sites',
            ],
            [
                'name' => 'Worley Parsons',
                'contact_person' => 'Andrew McKinley',
                'email' => 'a.mckinley@worley.com',
                'phone' => '+61-2-8923-6866',
                'tax_id' => 'AU-096-090-158',
                'billing_address' => 'Level 15, 141 Walker St, North Sydney NSW 2060',
                'shipping_address' => 'Perth Project Hub, Australia',
            ],
            [
                'name' => 'ENOC Group (Emirates)',
                'contact_person' => 'Ibrahim Al-Hassan',
                'email' => 'i.hassan@enoc.com',
                'phone' => '+971-4-213-9999',
                'tax_id' => 'AE-100234987',
                'billing_address' => 'ENOC HQ, Rashidiya, Dubai, UAE',
                'shipping_address' => 'Jebel Ali Depot, Dubai, UAE',
            ]
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['email' => $customer['email']],
                $customer
            );
        }
    }
}
