<?php

use App\Models\AcademicYear;
use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $year = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->firstOrCreate(
                ['year_name' => '2026/2027', 'semester' => 'Ganjil'],
                ['is_active' => true, 'start_date' => '2026-07-13', 'end_date' => '2026-12-19']
            );

        if ($year) {
            $service = new SchoolScheduleService();
            $service->importFromWakasekCsv($year);
            $service->syncToTeachingClasses($year);
        }
    }

    public function down(): void
    {
        // Data jadwal tidak memerlukan rollback skema
    }
};
