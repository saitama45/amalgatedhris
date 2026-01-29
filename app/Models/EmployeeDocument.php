<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'applicant_id',
        'document_type_id',
        'file_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}