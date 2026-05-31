<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fx:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync daily exchange rates for multi-currency processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting FX rate sync...');
        
        // Ensure base currency exists (USD by default)
        $base = \App\Models\Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'exchange_rate' => 1.0, 'symbol' => '$', 'is_base' => true]
        );
        
        try {
            $response = \Illuminate\Support\Facades\Http::get('https://open.er-api.com/v6/latest/USD');
            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['rates'] ?? [];
                
                $targetCurrencies = [
                    'EUR' => ['name' => 'Euro', 'symbol' => '€'],
                    'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
                    'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
                    'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
                    'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
                    'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
                ];

                foreach ($targetCurrencies as $code => $info) {
                    if (isset($rates[$code])) {
                        \App\Models\Currency::updateOrCreate(
                            ['code' => $code],
                            ['name' => $info['name'], 'exchange_rate' => $rates[$code], 'symbol' => $info['symbol'], 'is_base' => false]
                        );
                        $this->line("Synced: {$code} at rate {$rates[$code]}");
                    }
                }
                $this->info('FX Sync complete.');
            } else {
                $this->error('Failed to fetch rates from API.');
            }
        } catch (\Exception $e) {
            $this->error('Exception fetching FX rates: ' . $e->getMessage());
        }
    }
}
