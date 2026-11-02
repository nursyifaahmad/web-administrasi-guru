<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const VALID_STATUSES = ['.', 's', 'i', 'a', 'd'];

    protected $fillable = [
        'teaching_class_id',
        'class_student_id',
        'attendance_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
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

    public static function isCountedPresent(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['.', 'd'], true);
    }
}
