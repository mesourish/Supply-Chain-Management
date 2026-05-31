<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CalculateDemandForecasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demand:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate AI-powered demand forecasts and dynamic reorder levels for all products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting demand forecast calculation...');

        $products = \App\Models\Product::all();
        $thirtyDaysAgo = now()->subDays(30);

        foreach ($products as $product) {
            // Find total 'out' quantity in the last 30 days
            $totalOut = \App\Models\InventoryTransaction::where('product_id', $product->id)
                ->where('type', 'out')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->sum('quantity');

            // Average daily consumption
            $velocity = $totalOut / 30;
            
            // Reorder level = Velocity * Lead Time + 10% safety stock
            $leadTime = $product->lead_time_days ?? 7;
            $dynamicReorderLevel = (int)ceil(($velocity * $leadTime) * 1.10);

            $product->update([
                'velocity' => $velocity,
                'dynamic_reorder_level' => $dynamicReorderLevel,
            ]);

            $this->line("Calculated for {$product->sku}: Velocity = " . number_format($velocity, 2) . "/day, Reorder Level = {$dynamicReorderLevel}");
        }

        $this->info('Demand forecast complete.');
    }
}
