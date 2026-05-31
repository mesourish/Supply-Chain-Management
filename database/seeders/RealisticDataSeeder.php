<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

/**
 * RealisticDataSeeder
 * ─────────────────────────────────────────────────────────────────────
 * Seeds fully interlinked, real-world ERP data across ALL modules.
 *
 * Data flow:
 *   CRM Lead → Quotation → Sales Order → Invoice → AR
 *                                      → Shipment
 *   Supplier → RFQ → Purchase Order (NOT received) → AP
 *   Customer → Project → Milestones → Material Requests (→ Products)
 *   Warehouse → Bins  (no inventory_transactions — nothing received yet)
 */
class RealisticDataSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        $now  = Carbon::now();
        $user = User::first() ?? User::factory()->create();

        // ──────────────────────────────────────────────────────────────
        // 0. SYSTEM CONSTANTS
        // ──────────────────────────────────────────────────────────────
        DB::table('system_constants')->insert([
            ['type' => 'product_category', 'name' => 'Raw Materials', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Electronics', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Machinery', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Tools & Hardware', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Safety Gear', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'product_category', 'name' => 'Chemicals', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            
            ['type' => 'expense_category', 'name' => 'Miscellaneous', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Shipping & Logistics', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Customs & Duty', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Office Supplies', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Travel', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense_category', 'name' => 'Software/IT', 'value' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            
            ['type' => 'gst_percentage', 'name' => '0% (Exempt)', 'value' => '0', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '5%', 'value' => '5', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '12%', 'value' => '12', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '18%', 'value' => '18', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'gst_percentage', 'name' => '28%', 'value' => '28', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ──────────────────────────────────────────────────────────────
        // 1. SUPPLIERS  (real industrial/tech companies)
        // ──────────────────────────────────────────────────────────────
        $supplierData = [
            ['name' => 'Siemens AG',              'contact' => 'Hans Müller',       'email' => 'procurement@siemens-supply.com',   'phone' => '+49-89-636-00',     'tax_id' => 'DE-812335567',   'address' => 'Werner-von-Siemens-Str. 1, 80333 Munich, Germany'],
            ['name' => 'Honeywell Process',        'contact' => 'Rachel Carter',     'email' => 'orders@honeywell-process.com',     'phone' => '+1-602-365-4810',   'tax_id' => 'US-36-1227822',  'address' => '115 Tabor Rd, Morris Plains, NJ 07950, USA'],
            ['name' => 'ABB Ltd Switzerland',      'contact' => 'Pierre Fontaine',   'email' => 'supply@abb.ch',                    'phone' => '+41-43-317-7111',   'tax_id' => 'CH-020.3.903.187', 'address' => 'Affolternstrasse 44, 8050 Zurich, Switzerland'],
            ['name' => 'Schneider Electric',       'contact' => 'Marie Dupont',      'email' => 'se.orders@schneider-electric.com', 'phone' => '+33-1-41-29-70-00', 'tax_id' => 'FR-73552045-10', 'address' => '35 rue Joseph Monier, 92500 Rueil-Malmaison, France'],
            ['name' => 'Emerson Electric Co.',     'contact' => 'Tom Bradley',       'email' => 'tom.b@emerson-supply.com',         'phone' => '+1-314-553-2000',   'tax_id' => 'US-43-0259330',  'address' => '8000 W. Florissant Ave, St. Louis, MO 63136, USA'],
            ['name' => 'Parker Hannifin Corp.',    'contact' => 'Lisa Chen',         'email' => 'lisa.chen@parker.com',             'phone' => '+1-216-896-3000',   'tax_id' => 'US-34-0451060',  'address' => '6035 Parkland Blvd, Mayfield Heights, OH 44124, USA'],
            ['name' => 'Bosch Rexroth AG',         'contact' => 'Klaus Wagner',      'email' => 'rexroth.orders@bosch.com',         'phone' => '+49-9352-18-0',     'tax_id' => 'DE-143014976',   'address' => 'Maria-Theresien-Str. 23, 97816 Lohr am Main, Germany'],
            ['name' => '3M Industrial Division',   'contact' => 'Sarah Johnson',     'email' => 's.johnson@3m-industrial.com',      'phone' => '+1-651-733-1110',   'tax_id' => 'US-41-0417775',  'address' => '3M Center, St. Paul, MN 55144, USA'],
            ['name' => 'Mitsubishi Electric',      'contact' => 'Yuki Tanaka',       'email' => 'y.tanaka@mitsubishielectric.com',  'phone' => '+81-3-3218-2111',   'tax_id' => 'JP-100-0005-807', 'address' => '2-7-3 Marunouchi, Chiyoda-ku, Tokyo 100-8310, Japan'],
            ['name' => 'Rockwell Automation',      'contact' => 'Michael Torres',    'email' => 'm.torres@rockwellautomation.com',  'phone' => '+1-414-382-2000',   'tax_id' => 'US-39-1441816',  'address' => '1201 S 2nd Street, Milwaukee, WI 53204, USA'],
        ];

        foreach ($supplierData as &$s) {
            $s = ['name' => $s['name'], 'contact_person' => $s['contact'], 'email' => $s['email'],
                  'phone' => $s['phone'], 'tax_id' => $s['tax_id'], 'address' => $s['address'],
                  'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('suppliers')->insert($supplierData);
        $supplierIds = DB::table('suppliers')->pluck('id')->toArray();

        // ──────────────────────────────────────────────────────────────
        // 2. CUSTOMERS  (real enterprises)
        // ──────────────────────────────────────────────────────────────
        $customerData = [
            ['name' => 'Saudi Aramco',            'cp' => 'Ahmed Al-Rashid',   'email' => 'ahmed.r@aramco.com',         'phone' => '+966-13-872-0115',  'tax_id' => 'SA-300005463500003', 'billing' => 'Saudi Aramco HQ, Dhahran 31311, Saudi Arabia',        'shipping' => 'Ras Tanura Refinery, Saudi Arabia'],
            ['name' => 'Bechtel Corporation',     'cp' => 'James O\'Brien',    'email' => 'jobrien@bechtel.com',        'phone' => '+1-415-768-1234',   'tax_id' => 'US-94-1687095',      'billing' => '50 Beale St, San Francisco, CA 94105, USA',          'shipping' => '12011 Sunset Hills Rd, Reston, VA 20190, USA'],
            ['name' => 'Fluor Corporation',       'cp' => 'Diana Marks',       'email' => 'd.marks@fluor.com',          'phone' => '+1-469-398-7000',   'tax_id' => 'US-95-0740960',      'billing' => '6700 Las Colinas Blvd, Irving, TX 75039, USA',       'shipping' => 'Fluor Project Site, Houston, TX, USA'],
            ['name' => 'SABIC Global',            'cp' => 'Khalid Al-Otaibi',  'email' => 'k.otaibi@sabic.com',         'phone' => '+966-1-225-8000',   'tax_id' => 'SA-300001164500003', 'billing' => 'SABIC HQ, Riyadh 11422, Saudi Arabia',               'shipping' => 'Al Jubail Industrial City, Saudi Arabia'],
            ['name' => 'ADNOC Group',             'cp' => 'Fatima Al-Mazrouei','email' => 'fatima.m@adnoc.ae',          'phone' => '+971-2-707-0000',   'tax_id' => 'AE-100345678',       'billing' => 'ADNOC HQ, Abu Dhabi, UAE',                           'shipping' => 'Ruwais Industrial Complex, UAE'],
            ['name' => 'Chevron Phillips Chem.',  'cp' => 'Robert Kline',      'email' => 'r.kline@cpchem.com',         'phone' => '+1-832-813-4100',   'tax_id' => 'US-76-0451430',      'billing' => '10001 Six Pines Dr, The Woodlands, TX 77380, USA',  'shipping' => 'Cedar Bayou Plant, Baytown, TX, USA'],
            ['name' => 'Larsen & Toubro Ltd.',    'cp' => 'Rajesh Sharma',     'email' => 'r.sharma@larsentoubro.com',  'phone' => '+91-22-6752-5656',  'tax_id' => 'IN-17-0610906-C',    'billing' => 'L&T House, N.M. Marg, Mumbai 400001, India',         'shipping' => 'L&T Heavy Engineering, Hazira, Surat, India'],
            ['name' => 'TechnipFMC plc',          'cp' => 'Sophie Laurent',    'email' => 's.laurent@technipfmc.com',   'phone' => '+1-281-260-3600',   'tax_id' => 'US-98-1283037',      'billing' => '11740 Katy Fwy, Houston, TX 77079, USA',            'shipping' => 'Various Offshore Project Sites'],
            ['name' => 'Worley Parsons',          'cp' => 'Andrew McKinley',   'email' => 'a.mckinley@worley.com',      'phone' => '+61-2-8923-6866',   'tax_id' => 'AU-096-090-158',     'billing' => 'Level 15, 141 Walker St, North Sydney NSW 2060',    'shipping' => 'Perth Project Hub, Australia'],
            ['name' => 'ENOC Group (Emirates)',   'cp' => 'Ibrahim Al-Hassan', 'email' => 'i.hassan@enoc.com',          'phone' => '+971-4-213-9999',   'tax_id' => 'AE-100234987',       'billing' => 'ENOC HQ, Rashidiya, Dubai, UAE',                    'shipping' => 'Jebel Ali Depot, Dubai, UAE'],
        ];

        $custInsert = [];
        foreach ($customerData as $c) {
            $custInsert[] = ['name' => $c['name'], 'contact_person' => $c['cp'], 'email' => $c['email'],
                             'phone' => $c['phone'], 'tax_id' => $c['tax_id'],
                             'billing_address' => $c['billing'], 'shipping_address' => $c['shipping'],
                             'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('customers')->insert($custInsert);
        $customerIds = DB::table('customers')->pluck('id')->toArray();

        // ──────────────────────────────────────────────────────────────
        // 3. WAREHOUSES & BINS
        // ──────────────────────────────────────────────────────────────
        $warehouseData = [
            ['name' => 'Dubai Main Warehouse',       'location' => 'Jebel Ali Free Zone, Dubai, UAE'],
            ['name' => 'Riyadh Distribution Centre', 'location' => 'Industrial Area, Riyadh, Saudi Arabia'],
            ['name' => 'Mumbai Logistics Hub',       'location' => 'Nhava Sheva, Navi Mumbai 400707, India'],
            ['name' => 'Houston Supply Depot',       'location' => '4200 Mitchelldale St, Houston, TX 77092, USA'],
            ['name' => 'Rotterdam Freight Centre',   'location' => 'Waalhaven, 3087 Rotterdam, Netherlands'],
        ];

        foreach ($warehouseData as $w) {
            DB::table('warehouses')->insert(['name' => $w['name'], 'location' => $w['location'],
                                             'created_at' => $now, 'updated_at' => $now]);
        }
        $warehouseIds = DB::table('warehouses')->pluck('id')->toArray();

        $binSeq = 1;
        $binIds = [];
        foreach ($warehouseIds as $wId) {
            foreach (['A', 'B', 'C'] as $zone) {
                foreach (['R1', 'R2'] as $rack) {
                    for ($shelf = 1; $shelf <= 4; $shelf++) {
                        for ($aisle = 1; $aisle <= 2; $aisle++) {
                            $code = sprintf('%s-%s-S%d-A%02d-W%d', $zone, $rack, $shelf, $aisle, $wId);
                            DB::table('warehouse_bins')->insert([
                                'warehouse_id'    => $wId,
                                'bin_code'        => $code,
                                'zone'            => $zone,
                                'aisle'           => str_pad($aisle, 2, '0', STR_PAD_LEFT),
                                'rack'            => $rack,
                                'shelf'           => 'S' . $shelf,
                                'bin_type'        => 'standard',
                                'max_weight_kg'   => 750,
                                'bin_sequence_no' => $binSeq++,
                                'is_active'       => true,
                                'bin_status'      => 'ACTIVE',
                                'pick_face_flag'  => ($shelf === 1 && $aisle === 1),
                                'created_at'      => $now,
                                'updated_at'      => $now,
                            ]);
                            $binIds[] = DB::getPdo()->lastInsertId();
                        }
                    }
                }
            }
        }

        // ──────────────────────────────────────────────────────────────
        // 4. PRODUCTS  (real industrial/process equipment products)
        // ──────────────────────────────────────────────────────────────
        $productData = [
            // Control & Instrumentation
            ['sku'=>'SIE-PLC-S7-1500', 'name'=>'Siemens SIMATIC S7-1500 PLC',           'cat'=>'Control Systems',   'brand'=>'Siemens',         'cost'=>4200,  'uom'=>'unit',   'wt'=>3.5,  'reorder'=>5],
            ['sku'=>'HON-DCS-EXPERION','name'=>'Honeywell Experion DCS Controller',      'cat'=>'Control Systems',   'brand'=>'Honeywell',       'cost'=>18500, 'uom'=>'unit',   'wt'=>8.0,  'reorder'=>2],
            ['sku'=>'ABB-800XA-MODULE','name'=>'ABB 800xA Process Module',               'cat'=>'Control Systems',   'brand'=>'ABB',             'cost'=>9700,  'uom'=>'unit',   'wt'=>5.2,  'reorder'=>3],
            ['sku'=>'SCH-MODICON-M580','name'=>'Schneider Modicon M580 PAC',             'cat'=>'Control Systems',   'brand'=>'Schneider',       'cost'=>3800,  'uom'=>'unit',   'wt'=>2.8,  'reorder'=>5],
            // Instrumentation
            ['sku'=>'EMR-FLOW-CORIOLIS','name'=>'Emerson Coriolis Flow Meter 2" DN50',   'cat'=>'Instrumentation',   'brand'=>'Emerson',         'cost'=>7200,  'uom'=>'unit',   'wt'=>12.0, 'reorder'=>4],
            ['sku'=>'HON-PRESSURE-TX', 'name'=>'Honeywell SmartLine Pressure TX',        'cat'=>'Instrumentation',   'brand'=>'Honeywell',       'cost'=>1850,  'uom'=>'unit',   'wt'=>2.2,  'reorder'=>10],
            ['sku'=>'ABB-TEMP-TX-265', 'name'=>'ABB TTF300 Temperature Transmitter',     'cat'=>'Instrumentation',   'brand'=>'ABB',             'cost'=>1250,  'uom'=>'unit',   'wt'=>1.5,  'reorder'=>15],
            ['sku'=>'END-YTA110-TEMP', 'name'=>'Endress+Hauser iTEMP TMT85',             'cat'=>'Instrumentation',   'brand'=>'Endress+Hauser',  'cost'=>980,   'uom'=>'unit',   'wt'=>0.8,  'reorder'=>20],
            // Valves & Actuators
            ['sku'=>'EMR-VALVE-FISHER','name'=>'Emerson Fisher EZ Control Valve 3"',     'cat'=>'Valves',            'brand'=>'Emerson',         'cost'=>4500,  'uom'=>'unit',   'wt'=>22.0, 'reorder'=>5],
            ['sku'=>'ROT-BUTTERFLY-DN','name'=>'Rotork Butterfly Valve DN200 w/Actuator','cat'=>'Valves',            'brand'=>'Rotork',          'cost'=>3200,  'uom'=>'unit',   'wt'=>18.5, 'reorder'=>6],
            ['sku'=>'VAL-GATE-SS-4IN', 'name'=>'Neles Gate Valve SS 4" 600#',            'cat'=>'Valves',            'brand'=>'Metso Neles',     'cost'=>2100,  'uom'=>'unit',   'wt'=>35.0, 'reorder'=>8],
            ['sku'=>'VAL-BALL-API-6D',  'name'=>'Ball Valve API 6D Trunnion 6"',         'cat'=>'Valves',            'brand'=>'Velan',           'cost'=>5600,  'uom'=>'unit',   'wt'=>48.0, 'reorder'=>4],
            // Electrical
            ['sku'=>'SIE-SINV-G120',   'name'=>'Siemens SINAMICS G120 VFD 11kW',        'cat'=>'Electrical',        'brand'=>'Siemens',         'cost'=>2800,  'uom'=>'unit',   'wt'=>7.0,  'reorder'=>6],
            ['sku'=>'SCH-MCCB-100A',   'name'=>'Schneider EasyPact MCCB 100A 3P',       'cat'=>'Electrical',        'brand'=>'Schneider',       'cost'=>420,   'uom'=>'unit',   'wt'=>2.0,  'reorder'=>20],
            ['sku'=>'ABB-CONTACTOR-65A','name'=>'ABB A65 Contactor 65A 415V',            'cat'=>'Electrical',        'brand'=>'ABB',             'cost'=>185,   'uom'=>'unit',   'wt'=>0.9,  'reorder'=>30],
            ['sku'=>'EAT-MFP-UPS-10K', 'name'=>'Eaton 9PX UPS 10kVA Online',            'cat'=>'Electrical',        'brand'=>'Eaton',           'cost'=>6200,  'uom'=>'unit',   'wt'=>85.0, 'reorder'=>2],
            // Mechanical / Piping
            ['sku'=>'PIPE-CS-SCH40-4IN','name'=>'Carbon Steel Pipe SCH40 4" x 6m',      'cat'=>'Piping',            'brand'=>'ASTM A106',       'cost'=>320,   'uom'=>'pcs',    'wt'=>55.0, 'reorder'=>50],
            ['sku'=>'FLG-SS-RF-6IN-150','name'=>'SS Flange Raised Face 6" 150# RF',      'cat'=>'Piping',            'brand'=>'ASME B16.5',      'cost'=>185,   'uom'=>'pcs',    'wt'=>8.5,  'reorder'=>40],
            ['sku'=>'GSKT-SPIRAL-4IN', 'name'=>'Spiral Wound Gasket 4" 300# SS+Graphite','cat'=>'Piping',            'brand'=>'Garlock',         'cost'=>45,    'uom'=>'pcs',    'wt'=>0.3,  'reorder'=>100],
            ['sku'=>'BOLT-STUD-M20-SS', 'name'=>'Stud Bolt M20x120 A193 B7/2H',         'cat'=>'Piping',            'brand'=>'Generic',         'cost'=>8,     'uom'=>'pcs',    'wt'=>0.2,  'reorder'=>500],
            // Safety Equipment
            ['sku'=>'HON-GAS-SEARCHPT','name'=>'Honeywell Searchpoint Gas Detector',     'cat'=>'Safety',            'brand'=>'Honeywell',       'cost'=>3400,  'uom'=>'unit',   'wt'=>4.5,  'reorder'=>5],
            ['sku'=>'MSA-ALTAIR-5X',   'name'=>'MSA Altair 5X Multigas Detector',       'cat'=>'Safety',            'brand'=>'MSA Safety',      'cost'=>1200,  'uom'=>'unit',   'wt'=>0.5,  'reorder'=>10],
            ['sku'=>'PPE-HELMET-3M',   'name'=>'3M H-700 Series Hard Hat',              'cat'=>'Safety',            'brand'=>'3M',              'cost'=>35,    'uom'=>'pcs',    'wt'=>0.4,  'reorder'=>50],
            ['sku'=>'PPE-SUIT-DUPONT', 'name'=>'DuPont Tyvek 400 Coverall Size L',      'cat'=>'Safety',            'brand'=>'DuPont',          'cost'=>18,    'uom'=>'pcs',    'wt'=>0.3,  'reorder'=>100],
            // Pumps & Compressors
            ['sku'=>'GRU-PUMP-3196',   'name'=>'Goulds 3196 Centrifugal Pump 3x4-10',   'cat'=>'Rotating Equipment','brand'=>'Goulds Pumps',    'cost'=>12800, 'uom'=>'unit',   'wt'=>180.0,'reorder'=>2],
            ['sku'=>'ROT-SCREW-COMP',  'name'=>'Atlas Copco GA75 Screw Compressor 75kW','cat'=>'Rotating Equipment','brand'=>'Atlas Copco',     'cost'=>42000, 'uom'=>'unit',   'wt'=>850.0,'reorder'=>1],
            // Cables & Wiring
            ['sku'=>'CBL-ARMRD-4C6MM', 'name'=>'Armoured Cable 4Cx6mm² SWA XLPE',      'cat'=>'Cables',            'brand'=>'Prysmian',        'cost'=>28,    'uom'=>'mtr',    'wt'=>1.2,  'reorder'=>500],
            ['sku'=>'CBL-INSTRU-2C15', 'name'=>'Instrumentation Cable 2Cx1.5mm² OS',    'cat'=>'Cables',            'brand'=>'Belden',          'cost'=>12,    'uom'=>'mtr',    'wt'=>0.4,  'reorder'=>1000],
            ['sku'=>'CBL-FO-24CORE',   'name'=>'Fibre Optic Cable 24-Core G.652D',      'cat'=>'Cables',            'brand'=>'Nexans',          'cost'=>22,    'uom'=>'mtr',    'wt'=>0.3,  'reorder'=>500],
            // Filtration
            ['sku'=>'FLT-COALESCER-2IN','name'=>'Pall Coalescer Filter Element 2" 10µ', 'cat'=>'Filtration',        'brand'=>'Pall Corporation', 'cost'=>580,  'uom'=>'unit',   'wt'=>2.5,  'reorder'=>20],
        ];

        $prodInsert = [];
        foreach ($productData as $p) {
            $prodInsert[] = [
                'sku'            => $p['sku'],
                'barcode'        => '60' . rand(10000000000, 99999999999),
                'name'           => $p['name'],
                'category'       => $p['cat'],
                'brand'          => $p['brand'],
                'description'    => 'Industrial-grade ' . $p['name'] . ' for oil & gas, petrochemical, and power generation applications.',
                'unit_of_measure'=> $p['uom'],
                'weight'         => $p['wt'],
                'cost_price'     => $p['cost'],
                'unit_price'     => round($p['cost'] * 1.35, 2),
                'reorder_level'  => $p['reorder'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        DB::table('products')->insert($prodInsert);
        $productIds = DB::table('products')->pluck('id')->toArray();
        $products   = DB::table('products')->get()->keyBy('id');

        // ──────────────────────────────────────────────────────────────
        // 5. VEHICLES & DRIVERS
        // ──────────────────────────────────────────────────────────────
        $vehicleData = [
            ['plate'=>'DXB-F-12340', 'type'=>'Heavy Truck',    'cap'=>20000, 'lat'=>25.2048,  'lng'=>55.2708],
            ['plate'=>'DXB-G-55891', 'type'=>'Flatbed Truck',  'cap'=>15000, 'lat'=>25.1972,  'lng'=>55.2796],
            ['plate'=>'AUH-A-33217', 'type'=>'Box Truck',      'cap'=>8000,  'lat'=>24.4539,  'lng'=>54.3773],
            ['plate'=>'RUH-P-77643', 'type'=>'Van',            'cap'=>3500,  'lat'=>24.6877,  'lng'=>46.7219],
            ['plate'=>'TXS-HOU-8823','type'=>'Semi-Trailer',   'cap'=>30000, 'lat'=>29.7604,  'lng'=>-95.3698],
        ];
        foreach ($vehicleData as $v) {
            DB::table('vehicles')->insert([
                'license_plate' => $v['plate'], 'type' => $v['type'],
                'status' => 'available', 'capacity' => $v['cap'],
                'latitude' => $v['lat'], 'longitude' => $v['lng'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $vehicleIds = DB::table('vehicles')->pluck('id')->toArray();

        $driverData = [
            ['name' => 'Mohammed Al-Rashidi', 'license' => 'DL-AE-2024-0091'],
            ['name' => 'Ravi Krishnamurthy',  'license' => 'DL-IN-2023-5547'],
            ['name' => 'Carlos Mendes',       'license' => 'DL-BR-2022-8823'],
        ];
        $driverIds = [];
        $driverCoords = [
            ['status' => 'on_job', 'lat' => 25.2048, 'lng' => 55.2708, 'veh_idx' => 0],
            ['status' => 'online', 'lat' => 25.1972, 'lng' => 55.2796, 'veh_idx' => 1],
            ['status' => 'online', 'lat' => 24.4539, 'lng' => 54.3773, 'veh_idx' => 2],
        ];
        foreach ($driverData as $idx => $d) {
            $email = strtolower(str_replace(' ', '.', $d['name'])) . '@driver.erp';
            $du = User::firstOrCreate(
                ['email' => $email],
                ['name' => $d['name'], 'password' => bcrypt('password')]
            );
            $coord = $driverCoords[$idx] ?? ['status' => 'online', 'lat' => null, 'lng' => null, 'veh_idx' => null];
            DB::table('drivers')->insert([
                'user_id' => $du->id,
                'license_number' => $d['license'],
                'status' => $coord['status'],
                'latitude' => $coord['lat'],
                'longitude' => $coord['lng'],
                'current_vehicle_id' => $coord['veh_idx'] !== null && isset($vehicleIds[$coord['veh_idx']]) ? $vehicleIds[$coord['veh_idx']] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $driverIds[] = DB::getPdo()->lastInsertId();
        }

        // ──────────────────────────────────────────────────────────────
        // 6. CRM LEADS  (pipeline stages, linked to customers)
        // ──────────────────────────────────────────────────────────────
        $leadDefs = [
            ['cust_idx'=>0, 'title'=>'Aramco GOSP-7 Instrumentation Upgrade',          'stage'=>'won',         'prob'=>95,  'value'=>1850000, 'src'=>'Referral'],
            ['cust_idx'=>1, 'title'=>'Bechtel FEED Study – Refinery Expansion',        'stage'=>'proposal',    'prob'=>60,  'value'=>520000,  'src'=>'Direct'],
            ['cust_idx'=>2, 'title'=>'Fluor EPC – LNG Train 3 Controls Package',       'stage'=>'negotiation', 'prob'=>75,  'value'=>3400000, 'src'=>'Trade Show'],
            ['cust_idx'=>3, 'title'=>'SABIC Ethylene Cracker DCS Migration',           'stage'=>'won',         'prob'=>95,  'value'=>2100000, 'src'=>'Existing Client'],
            ['cust_idx'=>4, 'title'=>'ADNOC Offshore SIL-2 Safety System',             'stage'=>'contacted',   'prob'=>30,  'value'=>780000,  'src'=>'Cold Call'],
            ['cust_idx'=>5, 'title'=>'CPChem Texas Facility Valve Replacement',        'stage'=>'proposal',    'prob'=>55,  'value'=>340000,  'src'=>'RFQ Response'],
            ['cust_idx'=>6, 'title'=>'L&T Heavy Engineering Motor Control Panels',     'stage'=>'won',         'prob'=>95,  'value'=>920000,  'src'=>'Tender'],
            ['cust_idx'=>7, 'title'=>'TechnipFMC Subsea Control Module Procurement',   'stage'=>'new',         'prob'=>15,  'value'=>4200000, 'src'=>'LinkedIn'],
            ['cust_idx'=>8, 'title'=>'Worley Parsons Engineering Services Package',    'stage'=>'contacted',   'prob'=>25,  'value'=>280000,  'src'=>'Cold Call'],
            ['cust_idx'=>9, 'title'=>'ENOC Fuel Terminal Automation Upgrade',          'stage'=>'negotiation', 'prob'=>80,  'value'=>1100000, 'src'=>'Referral'],
        ];

        $leadIds = [];
        foreach ($leadDefs as $ld) {
            $custId = $customerIds[$ld['cust_idx']];
            $cust   = DB::table('customers')->find($custId);
            DB::table('crm_leads')->insert([
                'customer_id'     => $custId,
                'title'           => $ld['title'],
                'company_name'    => $cust->name,
                'contact_name'    => $cust->contact_person,
                'email'           => $cust->email,
                'phone'           => $cust->phone,
                'deal_value'      => $ld['value'],
                'pipeline_stage'  => $ld['stage'],
                'deal_probability'=> $ld['prob'],
                'source'          => $ld['src'],
                'notes'           => 'Initial contact made. ' . $ld['title'] . ' opportunity identified through ' . $ld['src'] . '.',
                'assigned_user_id'=> $user->id,
                'created_at'      => $now->copy()->subDays(rand(10, 90)),
                'updated_at'      => $now,
            ]);
            $leadIds[] = DB::getPdo()->lastInsertId();
        }

        // CRM Activities
        $activityTypes = ['call', 'email', 'meeting', 'note'];
        $activities = [
            [0, 'call',    'Initial discovery call with Ahmed Al-Rashid. Confirmed budget approval for GOSP-7 project.'],
            [0, 'meeting', 'On-site visit to Aramco Dhahran HQ. Reviewed technical specifications for DCS upgrade.'],
            [0, 'email',   'Sent formal quotation QT-2026-001 for instrumentation supply package.'],
            [1, 'email',   'Responded to Bechtel FEED RFQ. Attached technical datasheets for DCS controllers.'],
            [1, 'call',    'Follow-up call with James O\'Brien. Budget under review by engineering committee.'],
            [2, 'meeting', 'Technical presentation to Fluor LNG project team in Houston. Strong interest confirmed.'],
            [2, 'note',    'Fluor team requested revised pricing for 2-year maintenance contract inclusion.'],
            [3, 'meeting', 'Kickoff meeting with SABIC procurement. Contract signing scheduled for next month.'],
            [3, 'email',   'Sent SABIC draft purchase agreement and compliance documentation.'],
            [4, 'call',    'Cold call to ADNOC procurement. Requested vendor registration documents.'],
            [5, 'email',   'Sent CPChem valve catalogue and ATEX certification docs.'],
            [6, 'meeting', 'L&T project meeting in Mumbai. Confirmed order for 12 MCC panels.'],
            [6, 'call',    'Confirmed delivery timeline with Rajesh Sharma. Q3 2026 delivery agreed.'],
            [9, 'meeting', 'Technical workshop with ENOC automation team at Dubai HQ.'],
            [9, 'email',   'Submitted revised proposal after ENOC scope clarification meeting.'],
        ];

        foreach ($activities as $a) {
            DB::table('crm_activities')->insert([
                'crm_lead_id'   => $leadIds[$a[0]],
                'type'          => $a[1],
                'description'   => $a[2],
                'activity_date' => $now->copy()->subDays(rand(1, 60))->toDateString(),
                'user_id'       => $user->id,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // ──────────────────────────────────────────────────────────────
        // 7. QUOTATIONS  (linked to leads + customers + products)
        // ──────────────────────────────────────────────────────────────
        $quotationDefs = [
            ['ref'=>'QT-2026-001', 'cust_idx'=>0, 'lead_idx'=>0, 'status'=>'accepted', 'valid'=>'+60 days',
             'items'=>[
                 ['prod_idx'=>0, 'qty'=>4,    'notes'=>'S7-1500 PLC for GOSP-7 Control Room'],
                 ['prod_idx'=>4, 'qty'=>8,    'notes'=>'Coriolis meters for hydrocarbon flow measurement'],
                 ['prod_idx'=>5, 'qty'=>24,   'notes'=>'Pressure transmitters across plant'],
                 ['prod_idx'=>8, 'qty'=>6,    'notes'=>'Control valves for separator train'],
             ]],
            ['ref'=>'QT-2026-002', 'cust_idx'=>3, 'lead_idx'=>3, 'status'=>'accepted', 'valid'=>'+45 days',
             'items'=>[
                 ['prod_idx'=>1, 'qty'=>2,   'notes'=>'Experion DCS for ethylene cracker primary loop'],
                 ['prod_idx'=>6, 'qty'=>40,  'notes'=>'Temperature transmitters site-wide'],
                 ['prod_idx'=>9, 'qty'=>12,  'notes'=>'Butterfly valves for ethylene pipeline'],
                 ['prod_idx'=>12,'qty'=>8,   'notes'=>'VFD drives for compressor motors'],
             ]],
            ['ref'=>'QT-2026-003', 'cust_idx'=>6, 'lead_idx'=>6, 'status'=>'accepted', 'valid'=>'+30 days',
             'items'=>[
                 ['prod_idx'=>13,'qty'=>30,  'notes'=>'MCCB for MCC panel distribution'],
                 ['prod_idx'=>14,'qty'=>60,  'notes'=>'Contactors for motor starters'],
                 ['prod_idx'=>26,'qty'=>2000,'notes'=>'Armoured power cable for panel wiring'],
                 ['prod_idx'=>3, 'qty'=>6,   'notes'=>'Modicon M580 PAC for PLC integration'],
             ]],
            ['ref'=>'QT-2026-004', 'cust_idx'=>9, 'lead_idx'=>9, 'status'=>'sent', 'valid'=>'+30 days',
             'items'=>[
                 ['prod_idx'=>0, 'qty'=>2,   'notes'=>'PLC for fuel terminal automation'],
                 ['prod_idx'=>20,'qty'=>6,   'notes'=>'Gas detectors for safety zone'],
                 ['prod_idx'=>5, 'qty'=>12,  'notes'=>'Pressure transmitters for storage tanks'],
             ]],
            ['ref'=>'QT-2026-005', 'cust_idx'=>1, 'lead_idx'=>1, 'status'=>'draft', 'valid'=>'+45 days',
             'items'=>[
                 ['prod_idx'=>2, 'qty'=>3,   'notes'=>'800xA modules for FEED study scope'],
                 ['prod_idx'=>7, 'qty'=>20,  'notes'=>'Temperature instruments'],
             ]],
        ];

        $quotationIds = [];
        foreach ($quotationDefs as $qd) {
            $totalAmt = 0;
            $taxAmt   = 0;

            // Calculate total
            foreach ($qd['items'] as $qi) {
                $prod = $products[$productIds[$qi['prod_idx']]];
                $totalAmt += $qi['qty'] * $prod->unit_price;
            }
            $taxAmt = round($totalAmt * 0.05, 2); // 5% VAT

            DB::table('quotations')->insert([
                'reference_no'    => $qd['ref'],
                'customer_id'     => $customerIds[$qd['cust_idx']],
                'crm_lead_id'     => $leadIds[$qd['lead_idx']],
                'status'          => $qd['status'],
                'valid_until'     => $now->copy()->addDays(intval($qd['valid']))->toDateString(),
                'total_amount'    => $totalAmt,
                'tax_amount'      => $taxAmt,
                'shipping_amount' => round($totalAmt * 0.01, 2),
                'notes'           => 'Prices valid as per quotation date. Delivery ex-works Dubai.',
                'created_at'      => $now->copy()->subDays(rand(5, 30)),
                'updated_at'      => $now,
            ]);
            $qId = DB::getPdo()->lastInsertId();
            $quotationIds[] = $qId;

            foreach ($qd['items'] as $qi) {
                $prod = $products[$productIds[$qi['prod_idx']]];
                $lineTotal = $qi['qty'] * $prod->unit_price;
                DB::table('quotation_items')->insert([
                    'quotation_id' => $qId,
                    'product_id'   => $prod->id,
                    'quantity'     => $qi['qty'],
                    'unit_price'   => $prod->unit_price,
                    'total_price'  => $lineTotal,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }

        // ──────────────────────────────────────────────────────────────
        // 8. SUPPLIER RFQs  (sent to suppliers for the products needed)
        // ──────────────────────────────────────────────────────────────
        $rfqDefs = [
            ['ref'=>'RFQ-2026-001', 'sup_idx'=>0, 'status'=>'received', 'amt'=>125000, 'delivery'=>'+30 days',
             'notes'=>'RFQ for Siemens PLC/SCADA components – GOSP-7 project.'],
            ['ref'=>'RFQ-2026-002', 'sup_idx'=>1, 'status'=>'received', 'amt'=>220000, 'delivery'=>'+45 days',
             'notes'=>'Honeywell DCS and instrumentation package for ethylene cracker.'],
            ['ref'=>'RFQ-2026-003', 'sup_idx'=>4, 'status'=>'sent',     'amt'=>89000,  'delivery'=>'+60 days',
             'notes'=>'Emerson control valves and flow meters for refinery project.'],
            ['ref'=>'RFQ-2026-004', 'sup_idx'=>3, 'status'=>'sent',     'amt'=>65000,  'delivery'=>'+30 days',
             'notes'=>'Schneider MCC panels and electrical components for L&T project.'],
            ['ref'=>'RFQ-2026-005', 'sup_idx'=>2, 'status'=>'draft',    'amt'=>0,      'delivery'=>'+60 days',
             'notes'=>'ABB instrumentation for ADNOC offshore SIL-2 safety system.'],
            ['ref'=>'RFQ-2026-006', 'sup_idx'=>5, 'status'=>'received', 'amt'=>42000,  'delivery'=>'+21 days',
             'notes'=>'Parker hydraulic components and fittings for rotating equipment.'],
            ['ref'=>'RFQ-2026-007', 'sup_idx'=>7, 'status'=>'sent',     'amt'=>18000,  'delivery'=>'+14 days',
             'notes'=>'3M safety PPE bulk order for site operations.'],
        ];

        foreach ($rfqDefs as $rd) {
            DB::table('rfqs')->insert([
                'reference_no'  => $rd['ref'],
                'supplier_id'   => $supplierIds[$rd['sup_idx']],
                'status'        => $rd['status'],
                'total_amount'  => $rd['amt'],
                'delivery_date' => $now->copy()->addDays(intval($rd['delivery']))->toDateString(),
                'notes'         => $rd['notes'],
                'created_at'    => $now->copy()->subDays(rand(5, 20)),
                'updated_at'    => $now,
            ]);
        }

        // ──────────────────────────────────────────────────────────────
        // 9. PURCHASE ORDERS  (NOT received → no GRN → no inventory log)
        // ──────────────────────────────────────────────────────────────
        $poDefs = [
            // Siemens PLC/SCADA for GOSP-7
            ['sup_idx'=>0, 'status'=>'approved', 'items'=>[
                ['prod_idx'=>0,  'qty'=>4,    'price'=>4350],
                ['prod_idx'=>3,  'qty'=>6,    'price'=>3920],
                ['prod_idx'=>12, 'qty'=>8,    'price'=>2850],
            ]],
            // Honeywell DCS + Instruments for SABIC
            ['sup_idx'=>1, 'status'=>'approved', 'items'=>[
                ['prod_idx'=>1,  'qty'=>2,    'price'=>19200],
                ['prod_idx'=>5,  'qty'=>24,   'price'=>1900],
                ['prod_idx'=>6,  'qty'=>40,   'price'=>1280],
            ]],
            // Emerson valves and meters
            ['sup_idx'=>4, 'status'=>'draft', 'items'=>[
                ['prod_idx'=>4,  'qty'=>8,    'price'=>7400],
                ['prod_idx'=>8,  'qty'=>6,    'price'=>4650],
            ]],
            // Schneider electrical components
            ['sup_idx'=>3, 'status'=>'approved', 'items'=>[
                ['prod_idx'=>13, 'qty'=>30,   'price'=>430],
                ['prod_idx'=>14, 'qty'=>60,   'price'=>190],
                ['prod_idx'=>3,  'qty'=>6,    'price'=>3900],
            ]],
            // ABB instrumentation
            ['sup_idx'=>2, 'status'=>'draft', 'items'=>[
                ['prod_idx'=>2,  'qty'=>3,    'price'=>9900],
                ['prod_idx'=>6,  'qty'=>20,   'price'=>1290],
                ['prod_idx'=>9,  'qty'=>12,   'price'=>3300],
            ]],
            // Piping materials
            ['sup_idx'=>5, 'status'=>'approved', 'items'=>[
                ['prod_idx'=>16, 'qty'=>80,   'price'=>330],
                ['prod_idx'=>17, 'qty'=>120,  'price'=>190],
                ['prod_idx'=>18, 'qty'=>500,  'price'=>47],
                ['prod_idx'=>19, 'qty'=>2000, 'price'=>8.5],
            ]],
            // Cables
            ['sup_idx'=>6, 'status'=>'draft', 'items'=>[
                ['prod_idx'=>26, 'qty'=>3000, 'price'=>29],
                ['prod_idx'=>27, 'qty'=>5000, 'price'=>12.5],
                ['prod_idx'=>28, 'qty'=>2000, 'price'=>23],
            ]],
            // Safety PPE
            ['sup_idx'=>7, 'status'=>'approved', 'items'=>[
                ['prod_idx'=>22, 'qty'=>200,  'price'=>36],
                ['prod_idx'=>23, 'qty'=>500,  'price'=>19],
                ['prod_idx'=>20, 'qty'=>8,    'price'=>3500],
                ['prod_idx'=>21, 'qty'=>15,   'price'=>1250],
            ]],
        ];

        $poIds = [];
        $grnAdded = false;
        foreach ($poDefs as $pd) {
            $subtotal = 0;
            foreach ($pd['items'] as $pi) {
                $subtotal += $pi['qty'] * $pi['price'];
            }
            
            $gstPct = 5; // 5% GST
            $gstAmt = $subtotal * ($gstPct / 100);
            $total = $subtotal + $gstAmt;

            DB::table('purchase_orders')->insert([
                'supplier_id'          => $supplierIds[$pd['sup_idx']],
                'status'               => $pd['status'] === 'approved' ? 'received' : $pd['status'], // Set some to received to simulate stock
                'subtotal'             => $subtotal,
                'gst_type'             => 'exclusive',
                'gst_percentage'       => $gstPct,
                'gst_amount'           => $gstAmt,
                'total_amount'         => $total,
                'remarks'              => 'Generated by Realistic Data Seeder.',
                'terms_and_conditions' => 'Standard 30 Days net. Delivery at site.',
                'created_at'           => $now->copy()->subDays(rand(3, 25)),
                'updated_at'           => $now,
            ]);
            $poId = DB::getPdo()->lastInsertId();
            $poIds[] = $poId;
            
            $isReceived = ($pd['status'] === 'approved'); // If it was approved, we'll mark as received to add inventory
            
            if ($isReceived) {
                DB::table('goods_receipt_notes')->insert([
                    'purchase_order_id' => $poId,
                    'user_id'           => $user->id,
                    'status'            => 'received',
                    'notes'             => 'Full delivery received in good condition.',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                $grnId = DB::getPdo()->lastInsertId();
            }

            foreach ($pd['items'] as $pi) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $poId,
                    'product_id'        => $productIds[$pi['prod_idx']],
                    'quantity'          => $pi['qty'],
                    'unit_price'        => $pi['price'],
                    'received_quantity' => $isReceived ? $pi['qty'] : 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                
                if ($isReceived) {
                    $prodId = $productIds[$pi['prod_idx']];
                    $binId = $binIds[0] ?? 1;

                    // Add to inventory transaction
                    DB::table('inventory_transactions')->insert([
                        'product_id'       => $prodId,
                        'to_bin_id'        => $binId,
                        'type'             => 'in',
                        'quantity'         => $pi['qty'],
                        'reference_type'   => 'App\Models\GoodsReceiptNote',
                        'reference_id'     => $grnId,
                        'user_id'          => $user->id,
                        'notes'            => 'Received via GRN',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    
                    // Update bin stock
                    $existingBin = DB::table('bin_product_stock')
                        ->where('warehouse_bin_id', $binId)
                        ->where('product_id', $prodId)
                        ->first();
                        
                    if ($existingBin) {
                        DB::table('bin_product_stock')
                            ->where('id', $existingBin->id)
                            ->update([
                                'quantity' => $existingBin->quantity + $pi['qty'],
                                'updated_at' => $now
                            ]);
                    } else {
                        DB::table('bin_product_stock')->insert([
                            'warehouse_bin_id' => $binId,
                            'product_id'       => $prodId,
                            'quantity'         => $pi['qty'],
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                    }
                }
            }

            // Account Payable for approved POs only
            if ($pd['status'] === 'approved') {
                DB::table('account_payables')->insert([
                    'purchase_order_id' => $poId,
                    'supplier_id'       => $supplierIds[$pd['sup_idx']],
                    'amount'            => $total,
                    'status'            => 'unpaid',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }

        // ──────────────────────────────────────────────────────────────
        // 10. SALES ORDERS  (converted from accepted quotations)
        // ──────────────────────────────────────────────────────────────
        $soDefs = [
            // Aramco GOSP-7 — from QT-2026-001
            ['cust_idx'=>0, 'status'=>'confirmed', 'quot_idx'=>0, 'items'=>[
                ['prod_idx'=>0, 'qty'=>4,  'price'=>5670],
                ['prod_idx'=>4, 'qty'=>8,  'price'=>9720],
                ['prod_idx'=>5, 'qty'=>24, 'price'=>2498],
                ['prod_idx'=>8, 'qty'=>6,  'price'=>6075],
            ]],
            // SABIC DCS Migration — from QT-2026-002
            ['cust_idx'=>3, 'status'=>'processing', 'quot_idx'=>1, 'items'=>[
                ['prod_idx'=>1, 'qty'=>2,  'price'=>24975],
                ['prod_idx'=>6, 'qty'=>40, 'price'=>1688],
                ['prod_idx'=>9, 'qty'=>12, 'price'=>4320],
            ]],
            // L&T MCC Panels — from QT-2026-003
            ['cust_idx'=>6, 'status'=>'confirmed', 'quot_idx'=>2, 'items'=>[
                ['prod_idx'=>13,'qty'=>30, 'price'=>567],
                ['prod_idx'=>14,'qty'=>60, 'price'=>250],
                ['prod_idx'=>26,'qty'=>2000,'price'=>37.8],
                ['prod_idx'=>3, 'qty'=>6,  'price'=>5130],
            ]],
            // Additional ENOC small order
            ['cust_idx'=>9, 'status'=>'pending', 'quot_idx'=>null, 'items'=>[
                ['prod_idx'=>20,'qty'=>4,  'price'=>4590],
                ['prod_idx'=>21,'qty'=>8,  'price'=>1620],
            ]],
            // Fluor engineering package
            ['cust_idx'=>2, 'status'=>'pending', 'quot_idx'=>null, 'items'=>[
                ['prod_idx'=>2, 'qty'=>2,  'price'=>13095],
                ['prod_idx'=>7, 'qty'=>15, 'price'=>1323],
            ]],
        ];

        $soIds = [];
        foreach ($soDefs as $sd) {
            $total = 0;
            foreach ($sd['items'] as $si) {
                $total += $si['qty'] * $si['price'];
            }

            DB::table('sales_orders')->insert([
                'customer_id'  => $customerIds[$sd['cust_idx']],
                'status'       => $sd['status'],
                'total_amount' => $total,
                'created_at'   => $now->copy()->subDays(rand(2, 15)),
                'updated_at'   => $now,
            ]);
            $soId = DB::getPdo()->lastInsertId();
            $soIds[] = $soId;

            foreach ($sd['items'] as $si) {
                DB::table('sales_order_items')->insert([
                    'sales_order_id' => $soId,
                    'product_id'     => $productIds[$si['prod_idx']],
                    'quantity'       => $si['qty'],
                    'unit_price'     => $si['price'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            // Invoices for confirmed/processing SOs
            if (in_array($sd['status'], ['confirmed', 'processing'])) {
                DB::table('invoices')->insert([
                    'sales_order_id' => $soId,
                    'status'         => 'unpaid',
                    'amount'         => $total,
                    'created_at'     => $now->copy()->subDays(rand(1, 10)),
                    'updated_at'     => $now,
                ]);
                $invId = DB::getPdo()->lastInsertId();

                DB::table('account_receivables')->insert([
                    'invoice_id'  => $invId,
                    'customer_id' => $customerIds[$sd['cust_idx']],
                    'amount'      => $total,
                    'status'      => 'unpaid',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        // ──────────────────────────────────────────────────────────────
        // 11. SHIPMENTS  (for confirmed SOs)
        // ──────────────────────────────────────────────────────────────
        $shipmentDefs = [
            ['so_idx'=>0, 'veh_idx'=>0, 'drv_idx'=>0, 'status'=>'in_transit',
             'track'=>'TRK-AE2026-DXB-001',
             'orig'=>'Jebel Ali Warehouse, Dubai, UAE',  'dest'=>'Saudi Aramco Site, Dhahran, Saudi Arabia',
             'olat'=>25.0083, 'olng'=>55.0694, 'dlat'=>26.2889, 'dlng'=>50.1500],
            ['so_idx'=>1, 'veh_idx'=>null, 'drv_idx'=>null, 'status'=>'processing',
             'track'=>'TRK-AE2026-DXB-002',
             'orig'=>'Jebel Ali Warehouse, Dubai, UAE',  'dest'=>'SABIC Site, Jubail, Saudi Arabia',
             'olat'=>25.0083, 'olng'=>55.0694, 'dlat'=>27.0112, 'dlng'=>49.6583],
            ['so_idx'=>2, 'veh_idx'=>1, 'drv_idx'=>1, 'status'=>'in_transit',
             'track'=>'TRK-IN2026-MUM-003',
             'orig'=>'Mumbai Logistics Hub, Navi Mumbai',  'dest'=>'L&T Heavy Engineering, Hazira, Surat',
             'olat'=>18.9526, 'olng'=>72.8394, 'dlat'=>21.1514, 'dlng'=>72.8079],
        ];

        foreach ($shipmentDefs as $sh) {
            DB::table('shipments')->insert([
                'sales_order_id'      => $soIds[$sh['so_idx']],
                'vehicle_id'          => $sh['veh_idx'] !== null && isset($vehicleIds[$sh['veh_idx']]) ? $vehicleIds[$sh['veh_idx']] : null,
                'driver_id'           => $sh['drv_idx'] !== null && isset($driverIds[$sh['drv_idx']]) ? $driverIds[$sh['drv_idx']] : null,
                'status'              => $sh['status'],
                'tracking_number'     => $sh['track'],
                'origin_address'      => $sh['orig'],
                'destination_address' => $sh['dest'],
                'origin_lat'          => $sh['olat'],
                'origin_lng'          => $sh['olng'],
                'dest_lat'            => $sh['dlat'],
                'dest_lng'            => $sh['dlng'],
                'created_at'          => $now->copy()->subDays(rand(1, 5)),
                'updated_at'          => $now,
            ]);
        }

        // ──────────────────────────────────────────────────────────────
        // 13. PURCHASE EXPENSES
        // ──────────────────────────────────────────────────────────────
        $expenseDefs = [
            ['po_idx'=>0, 'cat'=>'Freight & Shipping',       'amt'=>8500,  'date'=>'-5 days',  'ref'=>'EXP-2026-001', 'note'=>'Air freight charges – Siemens PLC shipment from Munich to Dubai'],
            ['po_idx'=>1, 'cat'=>'Customs & Import Duty',    'amt'=>22000, 'date'=>'-8 days',  'ref'=>'EXP-2026-002', 'note'=>'UAE customs duty and clearance for Honeywell DCS equipment'],
            ['po_idx'=>3, 'cat'=>'Freight & Shipping',       'amt'=>4200,  'date'=>'-3 days',  'ref'=>'EXP-2026-003', 'note'=>'Sea freight – Schneider switchgear containers Rotterdam to Dubai'],
            ['po_idx'=>5, 'cat'=>'Freight & Shipping',       'amt'=>3800,  'date'=>'-12 days', 'ref'=>'EXP-2026-004', 'note'=>'Piping materials logistics from Parker USA warehouse to Houston port'],
            ['po_idx'=>7, 'cat'=>'Warehouse Handling',       'amt'=>1200,  'date'=>'-2 days',  'ref'=>'EXP-2026-005', 'note'=>'3M PPE goods receipt and warehousing charges – Dubai depot'],
            ['po_idx'=>0, 'cat'=>'Engineering Consultancy',  'amt'=>15000, 'date'=>'-20 days', 'ref'=>'EXP-2026-006', 'note'=>'Third-party Factory Acceptance Test (FAT) witness fee – Siemens Munich'],
            ['po_idx'=>1, 'cat'=>'Travel & Site Visit',      'amt'=>6800,  'date'=>'-15 days', 'ref'=>'EXP-2026-007', 'note'=>'Team travel – Honeywell vendor audit visit to Houston facility'],
            ['po_idx'=>null,'cat'=>'Software / IT',          'amt'=>9500,  'date'=>'-30 days', 'ref'=>'EXP-2026-008', 'note'=>'Annual AVEVA PDMS engineering software licence renewal'],
            ['po_idx'=>null,'cat'=>'Office & Admin',         'amt'=>2100,  'date'=>'-7 days',  'ref'=>'EXP-2026-009', 'note'=>'Office supplies and stationery – Q2 2026 procurement'],
            ['po_idx'=>2,  'cat'=>'Inspection & Testing',    'amt'=>5500,  'date'=>'-18 days', 'ref'=>'EXP-2026-010', 'note'=>'Third-party ATEX/IECEx certification testing for Emerson valves'],
        ];

        foreach ($expenseDefs as $ed) {
            DB::table('expenses')->insert([
                'purchase_order_id' => isset($ed['po_idx']) && $ed['po_idx'] !== null ? $poIds[$ed['po_idx']] : null,
                'supplier_id'       => null,
                'category'          => $ed['cat'],
                'amount'            => $ed['amt'],
                'expense_date'      => $now->copy()->addDays(intval($ed['date']))->toDateString(),
                'reference_number'  => $ed['ref'],
                'notes'             => $ed['note'],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('✅  RealisticDataSeeder complete!');
        $this->command->info('    Suppliers: ' . count($supplierData));
        $this->command->info('    Customers: ' . count($customerData));
        $this->command->info('    Products: '  . count($productData));
        $this->command->info('    CRM Leads: ' . count($leadDefs));
        $this->command->info('    Quotations: '. count($quotationDefs));
        $this->command->info('    RFQs: '       . count($rfqDefs));
        $this->command->info('    Purchase Orders: ' . count($poDefs) . ' (NOT received — inventory log stays clean)');
        $this->command->info('    Sales Orders: '    . count($soDefs));
        $this->command->info('    Expenses: '        . count($expenseDefs));
    }
}
