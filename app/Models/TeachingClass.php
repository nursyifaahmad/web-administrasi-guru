<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TeachingClass extends Model
{
    protected $fillable = [
        'teacher_id',
        'academic_year_id',
        'school_class_id',
        'subject_id',
        'grade_level',
        'class_name',
        'schedule_info',
        'teaching_days',
        'homeroom_teacher_name',
        'homeroom_teacher_nip',
        'homeroom_teacher_signature',
        'only_teaching_days',
        'is_grade_shared_with_admin',
        'is_grade_shared_with_students',
        'is_student_share_anti_screenshot',
        'student_share_slug',
        'student_share_enabled_at',
        'is_archived',
    ];

    protected static function booted(): void
    {
        static::saving(function (TeachingClass $model) {
            // Pastikan school_class_id selalu terisi secara konsisten
            if (empty($model->school_class_id) && ! empty($model->academic_year_id) && ! empty($model->class_name)) {
                $cleanName = trim($model->class_name);
                $sc = SchoolClass::query()->firstOrCreate(
                    [
                        'academic_year_id' => $model->academic_year_id,
                        'name' => $cleanName,
                    ],
                    [
                        'grade_level' => $model->grade_level ?: 'VII',
                        'homeroom_teacher_name' => $model->homeroom_teacher_name ?: '-',
                        'homeroom_teacher_nip' => $model->homeroom_teacher_nip,
                        'homeroom_teacher_signature' => $model->homeroom_teacher_signature,
                    ]
                );
                $model->school_class_id = $sc->id;
            }

            // Jika terhubung ke SchoolClass, sinkronkan data identitas dari SchoolClass
            if ($model->school_class_id && $model->relationLoaded('schoolClass') && $model->schoolClass) {
                $sc = $model->schoolClass;
                $model->grade_level = $sc->grade_level;
                $model->class_name = $sc->name;
                $model->homeroom_teacher_name = $sc->homeroom_teacher_name;
                $model->homeroom_teacher_nip = $sc->homeroom_teacher_nip;
                $model->homeroom_teacher_signature = $sc->homeroom_teacher_signature;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'teaching_days' => 'array',
            'only_teaching_days' => 'boolean',
            'is_grade_shared_with_admin' => 'boolean',
            'is_grade_shared_with_students' => 'boolean',
            'is_student_share_anti_screenshot' => 'boolean',
            'student_share_enabled_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    public function isTeachingDay(int $dayOfWeek): bool
    {
        if (empty($this->teaching_days) || ! is_array($this->teaching_days)) {
            return true;
        }

        return in_array($dayOfWeek, array_map('intval', $this->teaching_days), true);
    }

    private const SCHEDULE_DAY_NAMES_REGEX = "Minggu|Senin|Selasa|Rabu|Kamis|Jum'?at|Sabtu";

    public function getTeachingDaysLabelAttribute(): string
    {
        if (empty($this->teaching_days) || ! is_array($this->teaching_days)) {
            return 'Semua Hari (Senin–Sabtu)';
        }

        $names = [];
        foreach ($this->teaching_days as $d) {
            $names[] = \App\Support\IndonesianDate::DAYS[(int) $d] ?? '';
        }

        return implode(' & ', array_filter($names));
    }

    /**
     * Ekstrak segmen jadwal mengajar khusus untuk hari tertentu dari string schedule_info.
     */
    public function scheduleForDay(int $dayOfWeek): ?string
    {
        $raw = trim((string) ($this->schedule_info ?? ''));
        if ($raw === '') {
            return null;
        }

        $dayPattern = match ($dayOfWeek) {
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => "Jum'?at",
            6 => 'Sabtu',
            default => null,
        };

        if ($dayPattern === null) {
            return $raw;
        }

        $dayRegex = self::SCHEDULE_DAY_NAMES_REGEX;

        // Bila teks tidak memuat nama hari sama sekali (mis. "Jam 1-2"), anggap berlaku generik.
        if (! preg_match('/\b(?:' . $dayRegex . ')\b/iu', $raw)) {
            return $raw;
        }

        $parts = preg_split(
            '/\s*(?:[\x{2022}&;|\r\n]+|,(?=\s*(?:' . $dayRegex . ')\b))\s*/iu',
            $raw,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $matched = [];
        foreach ($parts ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && preg_match('/\b' . $dayPattern . '\b/iu', $part)) {
                $matched[] = $part;
            }
        }

        return ! empty($matched) ? implode(' • ', $matched) : null;
    }

    /**
     * Kunci pengurutan kronologis kelas pada hari tertentu berdasarkan jam mulai lalu nama kelas.
     */
    public function sortKeyForDay(int $dayOfWeek): string
    {
        $segment = (string) ($this->scheduleForDay($dayOfWeek) ?? '');
        $timeKey = '9999';
        $periodKey = '99';

        if (preg_match('/\b(\d{1,2})[.:]([0-5]\d)\b/', $segment, $m)) {
            $timeKey = sprintf('%02d%02d', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/\bjam\s*(\d{1,2})/i', $segment, $m)) {
            $periodKey = sprintf('%02d', (int) $m[1]);
        }

        return "{$timeKey}_{$periodKey}_{$this->class_name}";
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
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
        return $this->hasMany(ClassStudent::class, 'school_class_id', 'school_class_id')
            ->orderBy('order_number')
            ->orderBy('full_name');
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'school_class_id', 'school_class_id')
            ->where('is_active', true)
            ->orderBy('order_number')
            ->orderBy('full_name');
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

    /**
     * Buat slug publik unik yang mudah dikenali untuk akses lihat nilai mandiri murid.
     */
    public function generateUniqueStudentShareSlug(): string
    {
        $this->loadMissing(['subject', 'academicYear']);

        $prefix = Str::slug(implode('-', array_filter([
            $this->class_name,
            $this->subject?->code ?: $this->subject?->name,
            $this->academicYear?->semester,
        ])));
        $prefix = $prefix !== '' ? Str::limit($prefix, 60, '') : 'kelas';

        do {
            $candidate = $prefix . '-' . strtolower(Str::random(6));
        } while (self::query()->where('student_share_slug', $candidate)->where('id', '!=', $this->id)->exists());

        return $candidate;
    }

    /**
     * Pastikan buku kelas memiliki student_share_slug yang tersimpan dan kembalikan nilainya.
     */
    public function ensureStudentShareSlug(): string
    {
        if (empty($this->student_share_slug)) {
            $this->student_share_slug = $this->generateUniqueStudentShareSlug();
            $this->save();
        }

        return $this->student_share_slug;
    }

    /**
     * Kembalikan URL publik halaman lihat nilai murid bila slug sudah tersedia.
     */
    public function studentShareUrl(): ?string
    {
        return ! empty($this->student_share_slug)
            ? route('student-grades.show', $this->student_share_slug)
            : null;
    }
}
