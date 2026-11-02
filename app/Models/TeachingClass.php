<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeachingClass extends Model
{
    protected $fillable = [
        'teacher_id',
        'academic_year_id',
        'subject_id',
        'grade_level',
        'class_name',
        'schedule_info',
        'homeroom_teacher_name',
        'homeroom_teacher_nip',
        'homeroom_teacher_signature',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(ClassStudent::class)->orderBy('order_number')->orderBy('full_name');
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class)->where('is_active', true)->orderBy('order_number')->orderBy('full_name');
    }

    public function dailyJournals(): HasMany
    {
        return $this->hasMany(DailyJournal::class)->orderBy('journal_date')->orderBy('lesson_hours');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function gradeColumns(): HasMany
    {
        return $this->hasMany(GradeColumn::class)->orderBy('sort_order')->orderBy('id');
    }

    public function phColumns(): HasMany
    {
        return $this->hasMany(GradeColumn::class)->where('type', 'PH')->orderBy('sort_order')->orderBy('id');
    }

    public function ntColumns(): HasMany
    {
        return $this->hasMany(GradeColumn::class)->where('type', 'NT')->orderBy('sort_order')->orderBy('id');
    }

    public function gradeWeight(): HasOne
    {
        return $this->hasOne(GradeWeight::class);
    }

    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function getEffectiveKktpAttribute(): int
    {
        if ($this->subject) {
            return $this->subject->kktpForGrade($this->grade_level);
        }

        return SchoolSetting::current()->global_default_kktp;
    }

    public function ensureDefaultsInitialized(): void
    {
        if (! $this->gradeWeight()->exists()) {
            $this->gradeWeight()->create([
                'weight_ph' => 25,
                'weight_attendance' => 10,
                'weight_nt' => 20,
                'weight_sts' => 20,
                'weight_sas' => 25,
                'rounding_precision' => 2,
                'attendance_mode' => GradeWeight::MODE_MANUAL,
                'sick_leave_tolerance' => 3,
                'agreement_note' => 'Kontrak Belajar Standar',
            ]);
        }

        if (! $this->gradeColumns()->where('type', 'PH')->exists()) {
            $this->gradeColumns()->createMany([
                ['type' => 'PH', 'label' => 'PH 1', 'sort_order' => 1],
                ['type' => 'PH', 'label' => 'PH 2', 'sort_order' => 2],
            ]);
        }

        if (! $this->gradeColumns()->where('type', 'NT')->exists()) {
            $this->gradeColumns()->createMany([
                ['type' => 'NT', 'label' => 'NT 1', 'sort_order' => 1],
                ['type' => 'NT', 'label' => 'NT 2', 'sort_order' => 2],
            ]);
        }
    }
}
