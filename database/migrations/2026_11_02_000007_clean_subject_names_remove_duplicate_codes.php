<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subjects')
            ->where('code', 'IPA')
            ->where('name', 'like', '%(IPA)%')
            ->update(['name' => 'Ilmu Pengetahuan Alam']);

        DB::table('subjects')
            ->where('code', 'IPS')
            ->where('name', 'like', '%(IPS)%')
            ->update(['name' => 'Ilmu Pengetahuan Sosial']);
    }

    public function down(): void
    {
        // Tidak perlu dibalikkan
    }
};
