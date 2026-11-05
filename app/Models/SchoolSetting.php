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
        'logo_left_size',
        'logo_right_data',
        'logo_right_size',
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
            'logo_left_size' => 'integer',
            'logo_right_size' => 'integer',
            'global_default_kktp' => 'integer',
            'available_grade_levels' => 'array',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();
        if ($setting) {
            return $setting;
        }

        $staticLogoPath = public_path('logo-smpn2-lemahabang.png');
        $defaultLogoRight = null;
        if (file_exists($staticLogoPath)) {
            $raw = @file_get_contents($staticLogoPath);
            if ($raw) {
                $defaultLogoRight = 'data:image/png;base64,' . base64_encode($raw);
            }
        }

        return static::query()->create([
            'gov_header' => 'PEMERINTAH KABUPATEN KARAWANG',
            'department_header' => 'DINAS PENDIDIKAN, PEMUDA DAN OLAHRAGA',
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
            'npsn' => '20214811',
            'address' => 'Jl. Raya Lemahabang, Kec. Lemahabang, Kab. Karawang, Jawa Barat',
            'default_city' => 'Lemahabang',
            'headmaster_name' => 'Teti Kusmiyati, S.Pd., M.M.',
            'headmaster_nip' => '19720514 199802 2 003',
            'logo_right_data' => $defaultLogoRight,
            'logo_right_size' => 92,
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

    /**
     * Resolves the primary school logo for application branding (Sidebar header, Login page, etc.)
     * Prioritizes school logo (logo_right_data), then static asset fallback (logo-smpn2-lemahabang.png),
     * then logo_left_data if available.
     *
     * @return array{data_uri: ?string, width: int, height: int, cell_width: int, url: ?string}
     */
    public function resolvedAppLogo(): array
    {
        if (! empty($this->logo_right_data)) {
            $box = $this->resolvedLogoBox('right');
            if (! empty($box['data_uri'])) {
                $box['url'] = $box['data_uri'];
                return $box;
            }
        }

        $staticLogoRel = 'logo-smpn2-lemahabang.png';
        $staticLogoPath = public_path($staticLogoRel);
        if (file_exists($staticLogoPath)) {
            $raw = @file_get_contents($staticLogoPath);
            $dataUri = $raw ? ('data:image/png;base64,' . base64_encode($raw)) : null;
            return [
                'data_uri' => $dataUri,
                'width' => 92,
                'height' => 92,
                'cell_width' => 100,
                'url' => asset($staticLogoRel),
            ];
        }

        if (! empty($this->logo_left_data)) {
            $box = $this->resolvedLogoBox('left');
            if (! empty($box['data_uri'])) {
                $box['url'] = $box['data_uri'];
                return $box;
            }
        }

        return ['data_uri' => null, 'width' => 0, 'height' => 0, 'cell_width' => 0, 'url' => null];
    }

    /**
     * Hitung dimensi proporsional logo (data_uri terpotong presisi, width, height, cell_width) dalam satuan px
     * agar DomPDF, Excel, maupun pratinjau merender tinggi visual logo kiri & kanan 100% sama besar tanpa margin kosong.
     *
     * @return array{data_uri: ?string, width: int, height: int, cell_width: int}
     */
    public function resolvedLogoBox(string $side): array
    {
        $dataUri = $side === 'right' ? $this->logo_right_data : $this->logo_left_data;
        $targetHeight = (int) (($side === 'right' ? $this->logo_right_size : $this->logo_left_size) ?: 92);
        $targetHeight = max(40, min(160, $targetHeight));
        $maxWidth = (int) round($targetHeight * 1.45);

        $width = $targetHeight;
        $height = $targetHeight;
        $resolvedUri = $dataUri;

        if (! empty($dataUri)) {
            $trimmed = \App\Support\SignatureImageHelper::trimOuterPadding($dataUri);
            if ($trimmed && $trimmed['width'] > 0 && $trimmed['height'] > 0) {
                $resolvedUri = $trimmed['data_uri'];
                $ratio = $trimmed['width'] / $trimmed['height'];
                $height = $targetHeight;
                $width = (int) max(1, round($height * $ratio));
                if ($width > $maxWidth) {
                    $width = $maxWidth;
                    $height = (int) max(1, round($width / $ratio));
                }
            } elseif (str_contains($dataUri, ',')) {
                [, $b64] = explode(',', $dataUri, 2);
                $raw = base64_decode($b64, true);
                if ($raw !== false) {
                    $info = @getimagesizefromstring($raw);
                    if (is_array($info) && $info[0] > 0 && $info[1] > 0) {
                        $ratio = $info[0] / $info[1];
                        $height = $targetHeight;
                        $width = (int) max(1, round($height * $ratio));
                        if ($width > $maxWidth) {
                            $width = $maxWidth;
                            $height = (int) max(1, round($width / $ratio));
                        }
                    }
                }
            }
        }

        return [
            'data_uri' => $resolvedUri,
            'width' => $width,
            'height' => $height,
            'cell_width' => max(84, $width + 8),
        ];
    }

    /**
     * Format alamat sekolah menjadi tepat 1 baris bersih tanpa jeda baris (newline).
     */
    public function singleLineAddress(): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $this->address));
    }

    /**
     * Gabungkan NPSN (serta NSS bila ada) dalam satu baris bersama Telepon, Email, dan Website.
     */
    public function contactAndIdentityLine(): string
    {
        $parts = array_filter([
            ! empty($this->npsn) ? 'NPSN: ' . trim((string) $this->npsn) : null,
            ! empty($this->nss) ? 'NSS: ' . trim((string) $this->nss) : null,
            ! empty($this->phone) ? 'Telp: ' . trim((string) $this->phone) : null,
            ! empty($this->email) ? 'Email: ' . trim((string) $this->email) : null,
            ! empty($this->website) ? 'Web: ' . trim((string) $this->website) : null,
        ]);

        return implode(' | ', $parts);
    }
}
