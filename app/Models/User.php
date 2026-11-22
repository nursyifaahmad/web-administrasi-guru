<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const PIKET_DAYS = ['SENIN', 'SELASA', 'RABU', 'KAMIS', "JUM'AT", 'SABTU'];

    protected $fillable = [
        'name',
        'email',
        'nip',
        'role',
        'phone',
        'default_subject',
        'piket_days',
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
            'piket_days' => 'array',
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

    public function teachingModules(): HasMany
    {
        return $this->hasMany(TeachingModule::class, 'teacher_id');
    }

    public function approvedTeachingModules(): HasMany
    {
        return $this->hasMany(TeachingModule::class, 'approved_by');
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

    public function homeroomSchoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    public function homeroomClassForYear(?int $academicYearId = null): ?SchoolClass
    {
        $yearId = $academicYearId ?: AcademicYear::active()?->id;
        if (! $yearId) {
            return null;
        }

        if ($this->relationLoaded('homeroomSchoolClasses')) {
            return $this->homeroomSchoolClasses->firstWhere('academic_year_id', $yearId);
        }

        return $this->homeroomSchoolClasses()->where('academic_year_id', $yearId)->first();
    }

    public static function normalizePiketDay(?string $day): ?string
    {
        $upper = strtoupper(trim((string) $day));
        if ($upper === '' || $upper === '-') {
            return null;
        }
        if ($upper === 'JUMAT' || $upper === 'JUM’AT') {
            $upper = "JUM'AT";
        }

        return in_array($upper, self::PIKET_DAYS, true) ? $upper : null;
    }

    /**
     * @param array<int, mixed>|null $days
     * @return list<string>
     */
    public static function normalizePiketDays(?array $days): array
    {
        if (empty($days)) {
            return [];
        }

        $clean = [];
        foreach ($days as $day) {
            $norm = self::normalizePiketDay(is_string($day) ? $day : (string) $day);
            if ($norm !== null) {
                $clean[$norm] = true;
            }
        }

        $ordered = [];
        foreach (self::PIKET_DAYS as $validDay) {
            if (isset($clean[$validDay])) {
                $ordered[] = $validDay;
            }
        }

        return $ordered;
    }

    public function getPiketDaysListAttribute(): array
    {
        return self::normalizePiketDays(is_array($this->piket_days) ? $this->piket_days : []);
    }

    public function getPiketDaysStringAttribute(): string
    {
        return implode(', ', $this->piket_days_list);
    }

    public function getSubjectCodesListAttribute(): array
    {
        if ($this->relationLoaded('teacherSubjects')) {
            return $this->teacherSubjects
                ->map(fn ($ts) => $ts->subject?->code ?: $ts->role_label)
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

    public function preferredSubjectId(): ?int
    {
        if ($this->relationLoaded('teacherSubjects') && $this->teacherSubjects->isNotEmpty()) {
            $subjId = $this->teacherSubjects->first(fn ($ts) => $ts->subject_id !== null)?->subject_id;
            if ($subjId) {
                return (int) $subjId;
            }
        }

        $directSubjId = $this->teacherSubjects()->whereNotNull('subject_id')->value('subject_id');
        if ($directSubjId) {
            return (int) $directSubjId;
        }

        if (! empty($this->default_subject)) {
            $matched = Subject::query()->where('is_active', true)->get()->first(function ($s) {
                return \App\Services\SchoolScheduleService::isSubjectMatch($this->default_subject, $s);
            });
            if ($matched) {
                return (int) $matched->id;
            }
        }

        return null;
    }
}
