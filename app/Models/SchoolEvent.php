<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEvent extends Model
{
    public const TYPE_LIBUR_NASIONAL = 'libur_nasional';
    public const TYPE_KEGIATAN_SEKOLAH = 'kegiatan_sekolah';
    public const TYPE_UJIAN_ASESMEN = 'ujian_asesmen';
    public const TYPE_LIBUR_SEMESTER = 'libur_semester';

    public const TYPES = [
        self::TYPE_LIBUR_NASIONAL => 'Libur Nasional / Tanggal Merah',
        self::TYPE_KEGIATAN_SEKOLAH => 'Kegiatan Sekolah (Non-KBM)',
        self::TYPE_UJIAN_ASESMEN => 'Penilaian / Ujian / Asesmen',
        self::TYPE_LIBUR_SEMESTER => 'Libur Akhir Semester',
    ];

    protected $fillable = [
        'academic_year_id',
        'event_date',
        'event_end_date',
        'title',
        'type',
        'is_holiday',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'event_end_date' => 'date',
            'is_holiday' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Cek apakah event ini mencakup tanggal tertentu.
     */
    public function coversDate(CarbonInterface|string $date): bool
    {
        $d = Carbon::parse($date)->toDateString();
        $start = $this->event_date->toDateString();
        $end = $this->event_end_date ? $this->event_end_date->toDateString() : $start;

        return $d >= $start && $d <= $end;
    }

    /**
     * Scope event yang terjadi atau beririsan dengan bulan tertentu.
     */
    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $startMonth = sprintf('%04d-%02d-01', $year, $month);
        $endMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        return $query->where(function (Builder $q) use ($startMonth, $endMonth) {
            $q->whereBetween('event_date', [$startMonth, $endMonth])
                ->orWhere(function (Builder $sub) use ($startMonth, $endMonth) {
                    $sub->whereNotNull('event_end_date')
                        ->where('event_date', '<=', $endMonth)
                        ->where('event_end_date', '>=', $startMonth);
                });
        });
    }
}
