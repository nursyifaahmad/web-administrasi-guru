<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'code',
        'name',
        'default_kktp',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_kktp' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kktps(): HasMany
    {
        return $this->hasMany(SubjectKktp::class);
    }

    public function teachingClasses(): HasMany
    {
        return $this->hasMany(TeachingClass::class);
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function teachers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_subjects')
            ->withPivot('teaching_code')
            ->withTimestamps();
    }

    public function kktpForGrade(string $gradeLevel, ?int $globalDefault = null): int
    {
        if ($this->relationLoaded('kktps')) {
            $match = $this->kktps->firstWhere('grade_level', $gradeLevel);
            if ($match) {
                return (int) $match->kktp_value;
            }
        } else {
            $match = $this->kktps()->where('grade_level', $gradeLevel)->first();
            if ($match) {
                return (int) $match->kktp_value;
            }
        }

        return (int) ($this->default_kktp ?: ($globalDefault ?? SchoolSetting::current()->global_default_kktp));
    }
}
