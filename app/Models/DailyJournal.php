<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyJournal extends Model
{
    protected $fillable = [
        'teaching_class_id',
        'journal_date',
        'lesson_hours',
        'activity_material',
        'present_count',
        'absent_count',
        'attendance_breakdown',
        'is_attendance_overridden',
        'notes',
        'signature_data',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'present_count' => 'integer',
            'absent_count' => 'integer',
            'is_attendance_overridden' => 'boolean',
        ];
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }
}
