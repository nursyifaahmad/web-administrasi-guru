<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeWeight extends Model
{
    protected $fillable = [
        'teaching_class_id',
        'weight_ph',
        'weight_attendance',
        'weight_nt',
        'weight_sts',
        'weight_sas',
        'rounding_precision',
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
        ];
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
