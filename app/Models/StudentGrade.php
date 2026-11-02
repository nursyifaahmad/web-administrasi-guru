<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    protected $fillable = [
        'teaching_class_id',
        'class_student_id',
        'attendance_score',
        'sts_score',
        'sas_score',
        'dynamic_scores',
        'avg_ph',
        'avg_nt',
        'final_score',
    ];

    protected function casts(): array
    {
        return [
            'attendance_score' => 'float',
            'sts_score' => 'float',
            'sas_score' => 'float',
            'dynamic_scores' => 'array',
            'avg_ph' => 'float',
            'avg_nt' => 'float',
            'final_score' => 'float',
        ];
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ClassStudent::class, 'class_student_id');
    }
}
