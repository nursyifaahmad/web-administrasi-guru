<?php

use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Jalankan ulang pembersihan idempoten dengan aturan lengkap (termasuk PENJAS / PENJASKES -> PJOK)
        SchoolScheduleService::cleanupDuplicateSubjects();
    }

    public function down(): void
    {
        // Pembersihan data tidak perlu dikembalikan
    }
};
