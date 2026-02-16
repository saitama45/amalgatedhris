<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'immediate_head_id',
        'employee_code',
        'sss_no',
        'philhealth_no',
        'pagibig_no',
        'tin_no',
        'civil_status',
        'gender',
        'address',
        'home_no_street',
        'barangay',
        'city',
        'region',
        'zip_code',
        'emergency_contact',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'birthday',
        'face_data',
        'qr_code',
        'profile_photo',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function immediateHead()
    {
        return $this->belongsTo(Employee::class, 'immediate_head_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'immediate_head_id');
    }

    public function employmentRecords()
    {
        return $this->hasMany(EmploymentRecord::class);
    }

    public function activeEmploymentRecord()
    {
        return $this->hasOne(EmploymentRecord::class)->where('is_active', true);
    }
    
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function deductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }
}
