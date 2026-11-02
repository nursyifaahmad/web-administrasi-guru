<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectKktp extends Model
{
    protected $fillable = [
        'subject_id',
        'grade_level',
        'kktp_value',
    ];

    protected function casts(): array
    {
        return [
            'kktp_value' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
