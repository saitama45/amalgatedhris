<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class GenerateQRCodeImages extends Command
{
    protected $signature = 'qr:generate-images';
    protected $description = 'Generate QR code images for all employees';

    public function handle()
    {
        $employees = Employee::whereNotNull('qr_code')->get();
        $qrPath = public_path('storage/qr_codes');
        
        if (!file_exists($qrPath)) {
            mkdir($qrPath, 0755, true);
        }

        $this->info("Generating QR code images for {$employees->count()} employees...");
        
        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $employee) {
            $filename = $employee->qr_code . '.svg';
            $fullPath = $qrPath . DIRECTORY_SEPARATOR . $filename;
            
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(300)
                ->margin(1)
                ->errorCorrection('H')
                ->encoding('UTF-8')
                ->generate($employee->qr_code, $fullPath);
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('QR code images generated successfully!');
        
        return 0;
    }
}
