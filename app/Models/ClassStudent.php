<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassStudent extends Model
{
    protected $fillable = [
        'school_class_id',
        'teaching_class_id',
        'order_number',
        'nis_nisn',
        'full_name',
        'nickname',
        'gender',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ClassStudent $model) {
            if (empty($model->school_class_id) && ! empty($model->teaching_class_id)) {
                $tc = $model->relationLoaded('teachingClass') ? $model->teachingClass : TeachingClass::find($model->teaching_class_id);
                if ($tc && $tc->school_class_id) {
                    $model->school_class_id = $tc->school_class_id;
                }
            }
            if (empty($model->teaching_class_id) && ! empty($model->school_class_id)) {
                $firstTc = TeachingClass::where('school_class_id', $model->school_class_id)->value('id');
                if ($firstTc) {
                    $model->teaching_class_id = $firstTc;
                }
            }
        });
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function studentGrade(): HasOne
    {
        return $this->hasOne(StudentGrade::class);
    }
}
