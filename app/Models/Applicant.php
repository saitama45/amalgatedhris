<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'status',
        'resume_path',
        'exam_score',
        'interviewer_notes',
    ];

    protected $casts = [
        'exam_score' => 'decimal:2',
    ];
    
    // Add logic for full name accessor if needed
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
