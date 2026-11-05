<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolScheduleItem extends Model
{
    protected $fillable = [
        'academic_year_id',
        'day_of_week',
        'shift',
        'period_order',
        'time_range',
        'class_name',
        'school_class_id',
        'teacher_code',
        'teacher_name',
        'subject_name',
        'user_id',
        'subject_id',
        'is_conflict',
        'conflict_notes',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'period_order' => 'integer',
            'is_conflict' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function isCommonActivity(): bool
    {
        if (empty($this->teacher_code)) {
            return false;
        }

        $upper = strtoupper(trim($this->teacher_code));

        return in_array($upper, [
            'UPACARA BENDERA',
            'UPACARA',
            'SHOLAT DHUHA',
            'DHUHA',
            'ISTIRAHAT',
            'KEGIATAN KEAGAMAAN',
            'SABTU BERSIH / OR',
            'SABTU BERSIH',
            'LITERASI',
        ], true);
    }
}
