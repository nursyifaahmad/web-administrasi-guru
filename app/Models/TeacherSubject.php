<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubject extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
        'role_label',
        'teaching_code',
        'teaching_code_normalized',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $normalized = self::normalizeCode($model->teaching_code);
            $model->teaching_code = $normalized;
            $model->teaching_code_normalized = $normalized;
        });
    }

    public static function normalizeCode(?string $code): ?string
    {
        $clean = strtoupper((string) preg_replace('/\s+/', '', trim((string) $code)));

        return $clean !== '' ? $clean : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->subject?->name ?: ($this->role_label ?: '-');
    }
}
