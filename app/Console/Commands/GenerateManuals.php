<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class GenerateManuals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manual:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate SCM ERP Simplified User Manual in PDF and DOCX formats';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting user manual generation...');

        // 1. Generate DOCX via Python script
        $pythonScript = app_path('Console/Commands/generate_manual.py');
        $this->line('Executing Python generator script...');
        
        exec("python3 {$pythonScript} 2>&1", $output, $resultCode);

        if ($resultCode === 0) {
            $this->info('DOCX Manual generated successfully.');
        } else {
            $this->error('Failed to generate DOCX Manual.');
            $this->line(implode("\n", $output));
        }

        // 2. Generate PDF via DomPDF
        $this->line('Generating PDF manual using DomPDF...');
        try {
            $pdf = Pdf::setOption('isRemoteEnabled', true)->loadView('pdf.manual');
            $pdf->save(public_path('user_manual.pdf'));
            $this->info('PDF Manual generated successfully at: ' . public_path('user_manual.pdf'));
        } catch (\Exception $e) {
            $this->error('Failed to generate PDF Manual: ' . $e->getMessage());
            Log::error('Manual PDF compilation failed: ' . $e->getMessage());
        }

        $this->info('User manual generation complete.');
    }
}
