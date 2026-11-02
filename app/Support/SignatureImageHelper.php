<?php

namespace App\Support;

class SignatureImageHelper
{
    /**
     * Cache hasil normalisasi per request agar gambar tanda tangan yang sama tidak diproses berulang.
     *
     * @var array<string, array{data_uri: string, width: int, height: int}|null>
     */
    protected static array $normalizedCache = [];

    /**
     * Cache hasil pemotongan margin kosong luar logo Kop Surat.
     *
     * @var array<string, array{data_uri: string, width: int, height: int}|null>
     */
    protected static array $trimmedLogoCache = [];

    /**
     * Potong ruang kosong (transparan atau margin putih luar) di sekeliling lambang/logo Kop Surat
     * TANPA mengubah warna interior lambang, sehingga tinggi visual logo kiri & kanan 100% presisi sama besar.
     *
     * @return array{data_uri: string, width: int, height: int}|null
     */
    public static function trimOuterPadding(?string $dataUri): ?array
    {
        if (empty($dataUri) || ! str_starts_with($dataUri, 'data:image/')) {
            return null;
        }

        $cacheKey = md5($dataUri);
        if (array_key_exists($cacheKey, self::$trimmedLogoCache)) {
            return self::$trimmedLogoCache[$cacheKey];
        }

        if (! function_exists('imagecreatefromstring')) {
            return self::$trimmedLogoCache[$cacheKey] = null;
        }

        $parts = explode(',', $dataUri, 2);
        if (count($parts) !== 2) {
            return self::$trimmedLogoCache[$cacheKey] = null;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false || $binary === '') {
            return self::$trimmedLogoCache[$cacheKey] = null;
        }

        $src = @imagecreatefromstring($binary);
        if (! $src) {
            return self::$trimmedLogoCache[$cacheKey] = null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return self::$trimmedLogoCache[$cacheKey] = null;
        }

        $maxDim = 600;
        if ($srcW > $maxDim || $srcH > $maxDim) {
            $scale = min($maxDim / $srcW, $maxDim / $srcH);
            $workW = max(1, (int) round($srcW * $scale));
            $workH = max(1, (int) round($srcH * $scale));
            $work = imagecreatetruecolor($workW, $workH);
            imagealphablending($work, false);
            imagesavealpha($work, true);
            $transparent = imagecolorallocatealpha($work, 255, 255, 255, 127);
            imagefilledrectangle($work, 0, 0, $workW, $workH, $transparent);
            imagecopyresampled($work, $src, 0, 0, 0, 0, $workW, $workH, $srcW, $srcH);
            imagedestroy($src);
        } else {
            $workW = $srcW;
            $workH = $srcH;
            $work = imagecreatetruecolor($workW, $workH);
            imagealphablending($work, false);
            imagesavealpha($work, true);
            $transparent = imagecolorallocatealpha($work, 255, 255, 255, 127);
            imagefilledrectangle($work, 0, 0, $workW, $workH, $transparent);
            imagecopy($work, $src, 0, 0, 0, 0, $workW, $workH);
            imagedestroy($src);
        }

        // Cek apakah keempat sudut merupakan latar putih pekat (misal logo dari file JPG/PNG berlatar putih)
        $corners = [[0, 0], [$workW - 1, 0], [0, $workH - 1], [$workW - 1, $workH - 1]];
        $hasOpaqueWhiteCorners = true;
        foreach ($corners as [$cx, $cy]) {
            $c = imagecolorat($work, $cx, $cy);
            $a = ($c >> 24) & 0x7F;
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;
            if (! ($a < 15 && $r >= 246 && $g >= 246 && $b >= 246)) {
                $hasOpaqueWhiteCorners = false;
                break;
            }
        }

        $minX = $workW;
        $minY = $workH;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $workH; $y++) {
            for ($x = 0; $x < $workW; $x++) {
                $rgba = imagecolorat($work, $x, $y);
                $gdAlpha = ($rgba >> 24) & 0x7F;
                if ($gdAlpha >= 120) {
                    continue;
                }

                if ($hasOpaqueWhiteCorners) {
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    if ($r >= 247 && $g >= 247 && $b >= 247) {
                        continue;
                    }
                }

                if ($x < $minX) {
                    $minX = $x;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($work);

            return self::$trimmedLogoCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => $workW,
                'height' => $workH,
            ];
        }

        $pad = 1;
        $cropX = max(0, $minX - $pad);
        $cropY = max(0, $minY - $pad);
        $cropW = min($workW - $cropX, ($maxX - $minX + 1) + ($pad * 2));
        $cropH = min($workH - $cropY, ($maxY - $minY + 1) + ($pad * 2));

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparentCrop = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $cropW, $cropH, $transparentCrop);
        imagecopy($cropped, $work, 0, 0, $cropX, $cropY, $cropW, $cropH);
        imagedestroy($work);

        ob_start();
        imagepng($cropped, null, 6);
        $pngData = ob_get_clean();
        imagedestroy($cropped);

        if (! $pngData) {
            return self::$trimmedLogoCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => $cropW,
                'height' => $cropH,
            ];
        }

        return self::$trimmedLogoCache[$cacheKey] = [
            'data_uri' => 'data:image/png;base64,' . base64_encode($pngData),
            'width' => $cropW,
            'height' => $cropH,
        ];
    }

    /**
     * Hapus latar putih/terang menjadi transparan & potong ruang kosong (auto-crop) di sekeliling tinta tanda tangan.
     *
     * @return array{data_uri: string, width: int, height: int}|null
     */
    public static function normalizeInk(?string $dataUri): ?array
    {
        if (empty($dataUri) || ! str_starts_with($dataUri, 'data:image/')) {
            return null;
        }

        $cacheKey = md5($dataUri);
        if (array_key_exists($cacheKey, self::$normalizedCache)) {
            return self::$normalizedCache[$cacheKey];
        }

        if (! function_exists('imagecreatefromstring')) {
            return self::$normalizedCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => 240,
                'height' => 100,
            ];
        }

        $parts = explode(',', $dataUri, 2);
        if (count($parts) !== 2) {
            return self::$normalizedCache[$cacheKey] = null;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false || $binary === '') {
            return self::$normalizedCache[$cacheKey] = null;
        }

        $src = @imagecreatefromstring($binary);
        if (! $src) {
            return self::$normalizedCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => 240,
                'height' => 100,
            ];
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return self::$normalizedCache[$cacheKey] = null;
        }

        // Batasi resolusi kerja maks 900x450 px agar pemindaian piksel selalu instan (< 5ms)
        $maxWorkW = 900;
        $maxWorkH = 450;
        if ($srcW > $maxWorkW || $srcH > $maxWorkH) {
            $scale = min($maxWorkW / $srcW, $maxWorkH / $srcH);
            $workW = max(1, (int) round($srcW * $scale));
            $workH = max(1, (int) round($srcH * $scale));
            $work = imagecreatetruecolor($workW, $workH);
            imagealphablending($work, false);
            imagesavealpha($work, true);
            $transparent = imagecolorallocatealpha($work, 255, 255, 255, 127);
            imagefilledrectangle($work, 0, 0, $workW, $workH, $transparent);
            imagecopyresampled($work, $src, 0, 0, 0, 0, $workW, $workH, $srcW, $srcH);
            imagedestroy($src);
        } else {
            $workW = $srcW;
            $workH = $srcH;
            $work = imagecreatetruecolor($workW, $workH);
            imagealphablending($work, false);
            imagesavealpha($work, true);
            $transparent = imagecolorallocatealpha($work, 255, 255, 255, 127);
            imagefilledrectangle($work, 0, 0, $workW, $workH, $transparent);
            imagecopy($work, $src, 0, 0, 0, 0, $workW, $workH);
            imagedestroy($src);
        }

        $minX = $workW;
        $minY = $workH;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $workH; $y++) {
            for ($x = 0; $x < $workW; $x++) {
                $rgba = imagecolorat($work, $x, $y);
                $gdAlpha = ($rgba >> 24) & 0x7F; // 0 = pekat, 127 = transparan penuh

                if ($gdAlpha >= 122) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $minChannel = min($r, $g, $b);

                $newAlpha = $gdAlpha;
                if ($minChannel >= 242 && $lum >= 245) {
                    // Piksel latar kertas putih -> ubah menjadi transparan penuh agar tidak menutupi teks
                    $newAlpha = 127;
                } elseif ($minChannel >= 212 && $lum >= 218) {
                    // Transisi halus anti-aliasing di tepi goresan tinta di atas latar putih
                    $paperAlpha = (int) round((($lum - 218) / (245 - 218)) * 127);
                    $newAlpha = max($gdAlpha, min(127, $paperAlpha));
                }

                if ($newAlpha !== $gdAlpha) {
                    $col = imagecolorallocatealpha($work, $r, $g, $b, $newAlpha);
                    imagesetpixel($work, $x, $y, $col);
                }

                if ($newAlpha < 116) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }

        // Jika kanvas kosong (tidak ada piksel tinta), kembalikan gambar asli apa adanya
        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($work);

            return self::$normalizedCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => $workW,
                'height' => $workH,
            ];
        }

        $pad = 3;
        $cropX = max(0, $minX - $pad);
        $cropY = max(0, $minY - $pad);
        $cropW = min($workW - $cropX, ($maxX - $minX + 1) + ($pad * 2));
        $cropH = min($workH - $cropY, ($maxY - $minY + 1) + ($pad * 2));

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparentCrop = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $cropW, $cropH, $transparentCrop);
        imagecopy($cropped, $work, 0, 0, $cropX, $cropY, $cropW, $cropH);
        imagedestroy($work);

        ob_start();
        imagepng($cropped, null, 6);
        $pngData = ob_get_clean();
        imagedestroy($cropped);

        if (! $pngData) {
            return self::$normalizedCache[$cacheKey] = [
                'data_uri' => $dataUri,
                'width' => $cropW,
                'height' => $cropH,
            ];
        }

        return self::$normalizedCache[$cacheKey] = [
            'data_uri' => 'data:image/png;base64,' . base64_encode($pngData),
            'width' => $cropW,
            'height' => $cropH,
        ];
    }

    /**
     * Hitung kotak dimensi & overlap margin negatif untuk efek "Tanda Tangan Basah"
     * agar tanda tangan tampil besar dan menyentuh/menutupi teks jabatan di atas serta nama di bawahnya,
     * tanpa menggeser sejajarnya baris nama kiri & kanan.
     *
     * @return array{data_uri: string, width: int, height: int, margin_top: int, margin_bottom: int}|null
     */
    public static function wetSignatureRenderBox(
        ?string $dataUri,
        int $spaceHeight = 40,
        int $targetHeight = 96,
        int $maxWidth = 250
    ): ?array {
        $norm = self::normalizeInk($dataUri);
        if (! $norm) {
            return null;
        }

        $w = max(1, $norm['width']);
        $h = max(1, $norm['height']);

        $scale = min($targetHeight / $h, $maxWidth / $w);
        $renderW = max(1, (int) round($w * $scale));
        $renderH = max(1, (int) round($h * $scale));

        // Pastikan tinggi minimum tetap cukup besar bila tanda tangan sangat melebar
        if ($renderH < 68 && $w > $h) {
            $boostScale = min(68 / $h, ($maxWidth + 30) / $w);
            $renderW = max(1, (int) round($w * $boostScale));
            $renderH = max(1, (int) round($h * $boostScale));
        }

        $extra = $renderH - $spaceHeight;
        if ($extra > 0) {
            $overlapTop = (int) round($extra * 0.46);
            $overlapBottom = $extra - $overlapTop;
            $marginTop = -$overlapTop;
            $marginBottom = -$overlapBottom;
        } else {
            $padTop = (int) floor(($spaceHeight - $renderH) / 2);
            $padBottom = $spaceHeight - $renderH - $padTop;
            $marginTop = $padTop;
            $marginBottom = $padBottom;
        }

        return [
            'data_uri' => $norm['data_uri'],
            'width' => $renderW,
            'height' => $renderH,
            'margin_top' => $marginTop,
            'margin_bottom' => $marginBottom,
        ];
    }
}
