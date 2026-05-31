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
    protected $signature = 'erp:clear-data {--force : Force the operation to run without confirmation} {--no-files : Skip clearing file storage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all operational ERP data from the database and cleans up uploaded files.';

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
        'currencies'
    ];

    // Folders in storage/app/public that should be preserved
    protected $preserveFolders = [
        'logos',
        'avatars', // if they have user profile pictures
        'defaults' // default app assets
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure you want to permanently clear all operational ERP data? This cannot be undone.')) {
            $this->info('Operation cancelled.');
            return 1;
        }

        $this->info('Starting ERP Data Reset...');

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

        // Filter out tables that should be preserved
        $tablesToWipe = array_filter($allTables, function ($tableName) {
            return !in_array($tableName, $this->preserveTables);
        });

        if (empty($tablesToWipe)) {
            $this->info('No operational tables found to wipe.');
            return;
        }

        $this->info('Wiping ' . count($tablesToWipe) . ' tables...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $bar = $this->output->createProgressBar(count($tablesToWipe));
        
        foreach ($tablesToWipe as $table) {
            DB::table($table)->truncate();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->info('Database tables wiped successfully.');
    }

    protected function clearStorageFiles()
    {
        $this->info('Cleaning up operational file storage (public disk)...');

        $directories = Storage::disk('public')->directories();

        $clearedDirs = 0;
        foreach ($directories as $dir) {
            // Check if the directory is explicitly in the preserve list
            $baseDir = explode('/', $dir)[0]; // get the root directory name
            
            if (!in_array($baseDir, $this->preserveFolders)) {
                Storage::disk('public')->deleteDirectory($dir);
                $clearedDirs++;
            }
        }

        $this->info("Deleted operational directories from public storage.");
    }
}
