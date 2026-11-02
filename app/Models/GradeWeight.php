<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeWeight extends Model
{
    public const MODE_MANUAL = 'manual';
    public const MODE_AUTO = 'auto';

    protected $fillable = [
        'teaching_class_id',
        'weight_ph',
        'weight_attendance',
        'weight_nt',
        'weight_sts',
        'weight_sas',
        'rounding_precision',
        'attendance_mode',
        'sick_leave_tolerance',
        'agreement_note',
    ];

    protected function casts(): array
    {
        return [
            'weight_ph' => 'float',
            'weight_attendance' => 'float',
            'weight_nt' => 'float',
            'weight_sts' => 'float',
            'weight_sas' => 'float',
            'rounding_precision' => 'integer',
            'sick_leave_tolerance' => 'integer',
        ];
    }

    public function isAutoAttendance(): bool
    {
        return ($this->attendance_mode ?? self::MODE_MANUAL) === self::MODE_AUTO;
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }

    public function totalPercentage(): float
    {
        return round(
            $this->weight_ph
            + $this->weight_attendance
            + $this->weight_nt
            + $this->weight_sts
            + $this->weight_sas,
            2
        );
    }
}
