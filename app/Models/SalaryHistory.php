<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryHistory extends Model
{
    use HasFactory;

    protected $table = 'salary_history'; // Non-standard pluralization might require this

    protected $fillable = [
        'employment_record_id',
        'basic_rate',
        'allowance',
        'effective_date',
    ];

    protected $casts = [
        'basic_rate' => 'decimal:2',
        'allowance' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function employmentRecord()
    {
        return $this->belongsTo(EmploymentRecord::class);
    }
}
