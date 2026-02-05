<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Vacation Leave', 'days_per_year' => 15, 'is_convertible' => true, 'is_cumulative' => true],
            ['name' => 'Sick Leave', 'days_per_year' => 15, 'is_convertible' => true, 'is_cumulative' => false],
            ['name' => 'Emergency Leave', 'days_per_year' => 5, 'is_convertible' => false, 'is_cumulative' => false],
            ['name' => 'Maternity Leave', 'days_per_year' => 105, 'is_convertible' => false, 'is_cumulative' => false],
            ['name' => 'Paternity Leave', 'days_per_year' => 7, 'is_convertible' => false, 'is_cumulative' => false],
            ['name' => 'Solo Parent Leave', 'days_per_year' => 7, 'is_convertible' => false, 'is_cumulative' => false],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}