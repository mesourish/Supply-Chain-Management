<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClearErpData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:clear-data 
                            {--force : Force the operation to run without confirmation} 
                            {--no-files : Skip clearing file storage} 
                            {--modules= : Comma-separated list of modules to wipe (sales, procurement, inventory, logistics, manufacturing, finance)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears selective or all operational ERP data from the database and cleans up uploaded files.';

    // Tables that should NEVER be wiped during an ERP reset
    protected $preserveTables = [
        'users',
        'roles',
        'permissions',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'system_constants',
        'migrations',
        'personal_access_tokens',
        'failed_jobs',
        'password_reset_tokens',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'currencies',
        'settings'
    ];

    // Folders in storage/app/public that should be preserved
    protected $preserveFolders = [
        'logos',
        'avatars',
        'defaults'
    ];

    // Dependency ordering of tables to avoid FK constraint violations (dependent/child tables first)
    protected $tableOrder = [
        // Level 1
        'journal_lines',
        'payment_logs',
        'invoice_items',
        'bom_items',
        'return_request_items',
        'stock_take_items',
        'bin_product_stock',
        'bin_transfers',
        'predictive_maintenances',
        'fleet_expenses',
        'fleet_marketplace_transfers',
        'fuel_anomalies',
        'vehicle_damage_audits',
        'equipment_usage_charges',
        'rfq_bids',
        
        // Level 2
        'account_receivables',
        'account_payables',
        'quality_checks',
        'manufacturing_orders',
        'return_requests',
        'shipments',
        'invoices',
        'expenses',
        'goods_receipt_notes',
        'inventory_transactions',
        'sales_order_items',
        'purchase_order_items',
        'purchase_order_logs',
        'product_supplier',
        
        // Level 3
        'sales_orders',
        'purchase_orders',
        'rfqs',
        'bills_of_materials',
        'warehouse_bins',
        
        // Level 4
        'products',
        'customers',
        'suppliers',
        'warehouses',
        'work_centers',
        'vehicles',
        'drivers',
        'addresses',
        'contact_persons',
        'projects',
        'journal_entries'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modulesOption = $this->option('modules');
        $moduleMsg = $modulesOption ? "selected modules [{$modulesOption}]" : "all operational modules";

        if (! $this->option('force') && ! $this->confirm("Are you absolutely sure you want to permanently clear data for {$moduleMsg}? This cannot be undone.")) {
            $this->info('Operation cancelled.');
            return 1;
        }

        $this->info("Starting ERP Data Reset for {$moduleMsg}...");

        // 1. Wipe Database Tables
        $this->clearDatabaseTables();

        // 2. Wipe Operational Storage Files
        if (! $this->option('no-files')) {
            $this->clearStorageFiles();
        } else {
            $this->info('Skipping file storage cleanup as per --no-files flag.');
        }

        $this->info('ERP Data Reset Complete!');
        return 0;
    }

    protected function clearDatabaseTables()
    {
        $this->info('Identifying tables to wipe...');

        // Get all tables from the database dynamically
        $allTables = [];
        $tablesList = Schema::getTables();
        foreach ($tablesList as $tableObj) {
            $allTables[] = $tableObj['name'];
        }

        $modulesOption = $this->option('modules');
        if ($modulesOption) {
            $selectedModules = explode(',', strtolower($modulesOption));
            
            // Map modules to tables precisely
            $moduleTables = [
                'sales' => ['customers', 'sales_orders', 'sales_order_items', 'quotations', 'quotation_items', 'crm_leads', 'crm_activities', 'projects'],
                'procurement' => ['suppliers', 'product_supplier', 'purchase_orders', 'purchase_order_items', 'purchase_order_logs', 'rfqs', 'rfq_bids'],
                'inventory' => ['products', 'warehouses', 'warehouse_bins', 'bin_product_stock', 'inventory_transactions', 'bin_transfers', 'stock_takes', 'stock_take_items', 'return_requests', 'return_request_items'],
                'logistics' => ['vehicles', 'drivers', 'shipments', 'predictive_maintenances', 'fleet_expenses', 'fleet_marketplace_transfers', 'fuel_anomalies', 'vehicle_damage_audits', 'equipment_usage_charges'],
                'manufacturing' => ['manufacturing_orders', 'bills_of_materials', 'bom_items', 'work_centers', 'quality_checks'],
                'finance' => ['invoices', 'invoice_items', 'account_payables', 'account_receivables', 'expenses', 'payment_logs', 'journal_entries', 'journal_lines']
            ];

            $tablesToWipe = [];
            foreach ($selectedModules as $mod) {
                $mod = trim($mod);
                if (isset($moduleTables[$mod])) {
                    $tablesToWipe = array_merge($tablesToWipe, $moduleTables[$mod]);
                }
            }
 
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                if (in_array('sales', $selectedModules)) {
                    $tablesToWipe = array_merge($tablesToWipe, [
                        'invoices', 'invoice_items', 'shipments', 
                        'account_receivables', 'payment_logs', 
                        'journal_lines', 'journal_entries',
                        'return_requests', 'return_request_items',
                        'manufacturing_orders'
                    ]);
                }
                if (in_array('procurement', $selectedModules)) {
                    $tablesToWipe = array_merge($tablesToWipe, [
                        'goods_receipt_notes', 'account_payables', 
                        'journal_lines', 'journal_entries',
                        'expenses'
                    ]);
                }
            }

            // Exclude preserved tables just in case, and filter to tables that actually exist in the schema
            $tablesToWipe = array_filter($tablesToWipe, function ($tableName) {
                return !in_array($tableName, $this->preserveTables);
            });
            $tablesToWipe = array_intersect($tablesToWipe, $allTables);
            $tablesToWipe = array_values(array_unique($tablesToWipe));
        } else {
            // Filter out tables that should be preserved
            $tablesToWipe = array_filter($allTables, function ($tableName) {
                return !in_array($tableName, $this->preserveTables);
            });
        }
 
        // Sort tables to ensure child/dependent tables are wiped before parent tables
        usort($tablesToWipe, function ($a, $b) {
            $posA = array_search($a, $this->tableOrder);
            $posB = array_search($b, $this->tableOrder);
            
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;
            
            return $posA <=> $posB;
        });

        if (empty($tablesToWipe)) {
            $this->info('No operational tables found to wipe for the selected modules.');
            return;
        }

        $this->info('Wiping ' . count($tablesToWipe) . ' tables...');
 
        Schema::disableForeignKeyConstraints();
 
        $bar = $this->output->createProgressBar(count($tablesToWipe));
        
        foreach ($tablesToWipe as $table) {
            DB::table($table)->truncate();
            $bar->advance();
        }
 
        $bar->finish();
        $this->newLine();
 
        Schema::enableForeignKeyConstraints();
        
        $this->info('Database tables wiped successfully.');
    }

    protected function clearStorageFiles()
    {
        $this->info('Cleaning up operational file storage (public disk)...');

        $directories = Storage::disk('public')->directories();
        $modulesOption = $this->option('modules');

        $foldersToClear = [];
        if ($modulesOption) {
            $selectedModules = explode(',', strtolower($modulesOption));
            $moduleFolders = [
                'sales' => ['attachments', 'documents'],
                'procurement' => ['attachments', 'documents'],
                'inventory' => ['products'],
                'finance' => ['attachments', 'documents', 'certificates'],
            ];

            foreach ($selectedModules as $mod) {
                $mod = trim($mod);
                if (isset($moduleFolders[$mod])) {
                    $foldersToClear = array_merge($foldersToClear, $moduleFolders[$mod]);
                }
            }
            $foldersToClear = array_unique($foldersToClear);
        }

        $clearedDirs = 0;
        foreach ($directories as $dir) {
            $baseDir = explode('/', $dir)[0]; // get the root directory name
            
            // Skip preserved folders
            if (in_array($baseDir, $this->preserveFolders)) {
                continue;
            }

            // If selective modules are chosen, only clear directories mapped to them
            if ($modulesOption && !in_array($baseDir, $foldersToClear)) {
                continue;
            }

            Storage::disk('public')->deleteDirectory($dir);
            $clearedDirs++;
        }

        $this->info("Deleted {$clearedDirs} operational directories from public storage.");
    }
}
