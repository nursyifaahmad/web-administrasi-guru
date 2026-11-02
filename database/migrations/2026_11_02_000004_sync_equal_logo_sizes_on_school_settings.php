<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_settings')->update([
            'logo_right_size' => DB::raw('logo_left_size'),
        ]);
    }

    public function down(): void
    {
        // Tidak ada rollback skema yang diperlukan
    }
};
