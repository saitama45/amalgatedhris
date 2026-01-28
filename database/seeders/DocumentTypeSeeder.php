<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Resume / CV', 'is_required' => true],
            ['name' => 'Valid ID', 'is_required' => true],
            ['name' => 'NBI Clearance', 'is_required' => true],
            ['name' => 'Diploma / TOR', 'is_required' => true],
            ['name' => 'Medical Certificate', 'is_required' => true],
            ['name' => 'Birth Certificate', 'is_required' => true],
            ['name' => 'SSS Form E1', 'is_required' => false],
            ['name' => 'PhilHealth PMRF', 'is_required' => false],
            ['name' => 'Pag-IBIG MDF', 'is_required' => false],
            ['name' => 'BIR Form 2316', 'is_required' => false],
            ['name' => 'Certificate of Employment', 'is_required' => false],
        ];

        foreach ($types as $type) {
            DB::table('document_types')->updateOrInsert(
                ['name' => $type['name']],
                ['is_required' => $type['is_required'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}