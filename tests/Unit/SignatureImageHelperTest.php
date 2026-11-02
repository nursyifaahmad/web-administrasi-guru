<?php

namespace Tests\Unit;

use App\Support\SignatureImageHelper;
use PHPUnit\Framework\TestCase;

class SignatureImageHelperTest extends TestCase
{
    public function test_it_strips_white_background_auto_crops_ink_and_computes_wet_signature_overlap(): void
    {
        // Buat gambar tanda tangan 360x140 berlatar putih pekat dengan goresan kecil di tengah (100..220, 45..95)
        $im = imagecreatetruecolor(360, 140);
        $white = imagecolorallocate($im, 255, 255, 255);
        $ink = imagecolorallocate($im, 15, 23, 42);
        imagefilledrectangle($im, 0, 0, 360, 140, $white);
        imagesetthickness($im, 3);
        imageline($im, 100, 90, 220, 50, $ink);
        imageline($im, 140, 45, 175, 95, $ink);

        ob_start();
        imagepng($im);
        $dataUri = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        imagedestroy($im);

        $box = SignatureImageHelper::wetSignatureRenderBox($dataUri, 40, 104, 260);

        $this->assertNotNull($box);
        // Harus diperbesar mendekati tinggi target (~104px) dan memiliki margin negatif di atas & bawah
        $this->assertGreaterThanOrEqual(85, $box['height']);
        $this->assertLessThan(0, $box['margin_top']);
        $this->assertLessThan(0, $box['margin_bottom']);
        // Tinggi bersih ruang tanda tangan tetap tepat 40px agar kolom nama kiri & kanan tetap sejajar
        $this->assertSame(40, $box['height'] + $box['margin_top'] + $box['margin_bottom']);

        // Pastikan piksel sudut hasil normalisasi benar-benar transparan (alpha = 127 di GD)
        [, $b64] = explode(',', $box['data_uri'], 2);
        $croppedImg = imagecreatefromstring((string) base64_decode($b64, true));
        $this->assertNotFalse($croppedImg);
        $cornerAlpha = (imagecolorat($croppedImg, 0, 0) >> 24) & 0x7F;
        imagedestroy($croppedImg);

        $this->assertSame(127, $cornerAlpha);
    }

    public function test_it_trims_outer_logo_padding_without_altering_interior_white_details(): void
    {
        // Buat logo 200x200 transparan dengan perisai di tengah (50..150, 40..160) dan kotak putih pekat di dalam perisai (80..120, 80..120)
        $im = imagecreatetruecolor(200, 200);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 255, 255, 255, 127);
        imagefilledrectangle($im, 0, 0, 200, 200, $transparent);

        $blue = imagecolorallocatealpha($im, 29, 78, 216, 0);
        $white = imagecolorallocatealpha($im, 255, 255, 255, 0);
        imagefilledrectangle($im, 50, 40, 149, 159, $blue);
        imagefilledrectangle($im, 80, 80, 119, 119, $white); // Buku putih di tengah perisai

        ob_start();
        imagepng($im);
        $dataUri = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        imagedestroy($im);

        $trimmed = SignatureImageHelper::trimOuterPadding($dataUri);
        $this->assertNotNull($trimmed);

        // Ukuran asli 100x120 + pad 1px kiri/kanan/atas/bawah => 102x122 (margin 40-50px telah terpotong!)
        $this->assertSame(102, $trimmed['width']);
        $this->assertSame(122, $trimmed['height']);

        // Pastikan warna putih di bagian tengah perisai tetap putih pekat (alpha = 0, RGB = 255,255,255)
        [, $b64] = explode(',', $trimmed['data_uri'], 2);
        $cropped = imagecreatefromstring((string) base64_decode($b64, true));
        $this->assertNotFalse($cropped);
        $centerPixel = imagecolorat($cropped, 50, 60);
        imagedestroy($cropped);

        $this->assertSame(0, ($centerPixel >> 24) & 0x7F);
        $this->assertSame(255, ($centerPixel >> 16) & 0xFF);
        $this->assertSame(255, ($centerPixel >> 8) & 0xFF);
        $this->assertSame(255, $centerPixel & 0xFF);
    }
}
