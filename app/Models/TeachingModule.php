<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingModule extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVISION = 'revision';

    protected $fillable = [
        'academic_year_id',
        'teacher_id',
        'subject_id',
        'school_class_id',
        'grade_level',
        'semester',
        'title',
        'topic',
        'target_meetings',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'status',
        'approved_by',
        'approved_at',
        'feedback_notes',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRevision($query)
    {
        return $query->where('status', self::STATUS_REVISION);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isRevision(): bool
    {
        return $this->status === self::STATUS_REVISION;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeEditedBy(User $user): bool
    {
        return (int) $this->teacher_id === (int) $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return (int) $this->teacher_id === (int) $user->id;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Disetujui & Disahkan',
            self::STATUS_REVISION => 'Perlu Revisi',
            self::STATUS_DRAFT => 'Draf',
            default => 'Menunggu Telaah',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-emerald-50 text-emerald-800 border-emerald-300 ring-1 ring-emerald-500/20',
            self::STATUS_REVISION => 'bg-rose-50 text-rose-800 border-rose-300 ring-1 ring-rose-500/20',
            self::STATUS_DRAFT => 'bg-slate-100 text-slate-700 border-slate-300',
            default => 'bg-amber-50 text-amber-800 border-amber-300 ring-1 ring-amber-500/20',
        };
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
}
