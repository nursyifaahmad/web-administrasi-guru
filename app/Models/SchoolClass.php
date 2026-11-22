<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $fillable = [
        'academic_year_id',
        'grade_level',
        'name',
        'homeroom_teacher_id',
        'homeroom_teacher_name',
        'homeroom_teacher_nip',
        'homeroom_teacher_signature',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'school_class_id')->orderBy('order_number')->orderBy('full_name');
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'school_class_id')->where('is_active', true)->orderBy('order_number')->orderBy('full_name');
    }

    public function teachingClasses(): HasMany
    {
        return $this->hasMany(TeachingClass::class, 'school_class_id');
    }

    public function teachingModules(): HasMany
    {
        return $this->hasMany(TeachingModule::class, 'school_class_id');
    }

    /**
     * Terapkan wali kelas dari Master Guru dan sinkronkan otomatis ke seluruh Buku Kelas pengampu
     */
    public function applyHomeroomTeacher(?User $teacher): self
    {
        $this->forceFill([
            'homeroom_teacher_id' => $teacher?->id,
            'homeroom_teacher_name' => $teacher?->name ?: '-',
            'homeroom_teacher_nip' => $teacher?->nip,
            'homeroom_teacher_signature' => $teacher?->signature_data,
        ])->save();

        $this->syncToTeachingClasses();

        return $this;
    }

    /**
     * Sinkronkan identitas nama kelas & wali kelas ke seluruh kelas ajar guru yang terhubung
     */
    public function syncToTeachingClasses(): void
    {
        $this->teachingClasses()->update([
            'grade_level' => $this->grade_level,
            'class_name' => $this->name,
            'homeroom_teacher_name' => $this->homeroom_teacher_name,
            'homeroom_teacher_nip' => $this->homeroom_teacher_nip,
            'homeroom_teacher_signature' => $this->homeroom_teacher_signature,
        ]);
    }
}
