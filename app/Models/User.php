<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'nip',
        'role',
        'phone',
        'default_subject',
        'signature_data',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function teachingClasses(): HasMany
    {
        return $this->hasMany(TeachingClass::class, 'teacher_id');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'user_id');
    }

    public function subjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects')
            ->withPivot('teaching_code')
            ->withTimestamps();
    }

    public function getSubjectCodesListAttribute(): array
    {
        if ($this->relationLoaded('teacherSubjects')) {
            return $this->teacherSubjects
                ->map(fn ($ts) => $ts->subject?->code)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->subjects()->pluck('code')->all();
    }

    public function getSubjectCodesStringAttribute(): string
    {
        return implode(', ', $this->subject_codes_list);
    }

    public function getTeachingCodesListAttribute(): array
    {
        if ($this->relationLoaded('teacherSubjects')) {
            return $this->teacherSubjects
                ->pluck('teaching_code')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->teacherSubjects()->pluck('teaching_code')->filter()->unique()->values()->all();
    }

    public function getTeachingCodesStringAttribute(): string
    {
        return implode(', ', $this->teaching_codes_list);
    }
}
