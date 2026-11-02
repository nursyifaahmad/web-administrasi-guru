<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeColumn extends Model
{
    protected $fillable = [
        'teaching_class_id',
        'type',
        'label',
        'topic_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function teachingClass(): BelongsTo
    {
        return $this->belongsTo(TeachingClass::class);
    }
}
