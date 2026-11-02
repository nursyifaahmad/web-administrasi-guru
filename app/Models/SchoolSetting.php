<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $fillable = [
        'gov_header',
        'department_header',
        'school_name',
        'npsn',
        'nss',
        'address',
        'postal_code',
        'phone',
        'email',
        'website',
        'logo_left_data',
        'logo_right_data',
        'default_city',
        'headmaster_name',
        'headmaster_nip',
        'headmaster_signature',
        'global_default_kktp',
        'available_grade_levels',
    ];

    protected function casts(): array
    {
        return [
            'global_default_kktp' => 'integer',
            'available_grade_levels' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'gov_header' => 'PEMERINTAH KABUPATEN KARAWANG',
            'department_header' => 'DINAS PENDIDIKAN, PEMUDA DAN OLAHRAGA',
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
            'npsn' => '20214811',
            'address' => 'Jl. Raya Lemahabang, Kec. Lemahabang, Kab. Karawang, Jawa Barat',
            'default_city' => 'Lemahabang',
            'headmaster_name' => 'Teti Kusmiyati, S.Pd., M.M.',
            'headmaster_nip' => '19720514 199802 2 003',
            'global_default_kktp' => 75,
            'available_grade_levels' => ['VII', 'VIII', 'IX'],
        ]);
    }

    public function gradeLevels(): array
    {
        $levels = $this->available_grade_levels;

        return is_array($levels) && count($levels) > 0
            ? array_values($levels)
            : ['VII', 'VIII', 'IX'];
    }
}
