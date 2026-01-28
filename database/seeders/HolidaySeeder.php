<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = now()->year;

        $holidays = [
            // Regular Holidays
            ['name' => 'New Year\'s Day', 'date' => "$year-01-01", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'Araw ng Kagitingan', 'date' => "$year-04-09", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'Maundy Thursday', 'date' => "$year-04-17", 'type' => 'Regular', 'is_recurring' => false], // Varies
            ['name' => 'Good Friday', 'date' => "$year-04-18", 'type' => 'Regular', 'is_recurring' => false], // Varies
            ['name' => 'Labor Day', 'date' => "$year-05-01", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'Independence Day', 'date' => "$year-06-12", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'National Heroes Day', 'date' => "$year-08-25", 'type' => 'Regular', 'is_recurring' => false], // Last Monday of Aug
            ['name' => 'Bonifacio Day', 'date' => "$year-11-30", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'Christmas Day', 'date' => "$year-12-25", 'type' => 'Regular', 'is_recurring' => true],
            ['name' => 'Rizal Day', 'date' => "$year-12-30", 'type' => 'Regular', 'is_recurring' => true],

            // Special Non-Working Holidays
            ['name' => 'Chinese New Year', 'date' => "$year-01-29", 'type' => 'Special Non-Working', 'is_recurring' => false], // Varies
            ['name' => 'EDSA Revolution Anniversary', 'date' => "$year-02-25", 'type' => 'Special Non-Working', 'is_recurring' => true],
            ['name' => 'Black Saturday', 'date' => "$year-04-19", 'type' => 'Special Non-Working', 'is_recurring' => false], // Varies
            ['name' => 'Ninoy Aquino Day', 'date' => "$year-08-21", 'type' => 'Special Non-Working', 'is_recurring' => true],
            ['name' => 'All Saints\' Day', 'date' => "$year-11-01", 'type' => 'Special Non-Working', 'is_recurring' => true],
            ['name' => 'Feast of Immaculate Conception', 'date' => "$year-12-08", 'type' => 'Special Non-Working', 'is_recurring' => true],
            ['name' => 'Last Day of the Year', 'date' => "$year-12-31", 'type' => 'Special Non-Working', 'is_recurring' => true],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                ['name' => $holiday['name'], 'date' => $holiday['date']],
                $holiday
            );
        }
    }
}
