<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $logoPath = public_path('logo-smpn2-lemahabang.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo-smpn2-lemahabang.png');
        }

        if (file_exists($logoPath)) {
            $binary = @file_get_contents($logoPath);
            if ($binary !== false && strlen($binary) > 0) {
                $dataUri = 'data:image/png;base64,' . base64_encode($binary);

                DB::table('school_settings')->update([
                    'logo_right_data' => $dataUri,
                    'logo_right_size' => 92,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak perlu rollback skema untuk pembaruan logo bawaan sekolah
    }
};
