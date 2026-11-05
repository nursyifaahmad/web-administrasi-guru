<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolScheduleTeacher extends Model
{
    protected $fillable = [
        'academic_year_id',
        'code',
        'name',
        'subject',
        'user_id',
        'homeroom_class',
        'piket_day',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(SchoolScheduleItem::class, 'teacher_code', 'code');
    }
}
