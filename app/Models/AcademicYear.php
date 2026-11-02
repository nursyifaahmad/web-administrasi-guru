<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'year_name',
        'semester',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function teachingClasses(): HasMany
    {
        return $this->hasMany(TeachingClass::class);
    }

    public function getFullLabelAttribute(): string
    {
        return "TA {$this->year_name} - Semester {$this->semester}";
    }

    public static function active(): self
    {
        return static::query()->where('is_active', true)->first()
            ?? static::query()->orderByDesc('id')->first()
            ?? static::query()->create([
                'year_name' => '2026/2027',
                'semester' => 'Ganjil',
                'is_active' => true,
            ]);
    }
}
