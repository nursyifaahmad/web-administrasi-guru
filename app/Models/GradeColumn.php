<?php

namespace App\Models;

use App\Support\IndonesianDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeColumn extends Model
{
    protected $fillable = [
        'teaching_class_id',
        'type',
        'label',
        'topic_note',
        'assessment_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Label tanggal penilaian ringkas bahasa Indonesia (mis. "10 Nov 2026").
     */
    public function getAssessmentDateLabelAttribute(): ?string
    {
        if (! $this->assessment_date) {
            return null;
        }

        $shortMonth = mb_substr(IndonesianDate::monthName((int) $this->assessment_date->month), 0, 3);

        return sprintf('%02d %s %d', $this->assessment_date->day, $shortMonth, $this->assessment_date->year);
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }
}
