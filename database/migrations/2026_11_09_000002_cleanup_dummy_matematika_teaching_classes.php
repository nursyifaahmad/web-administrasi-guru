<?php

use App\Models\Subject;
use App\Models\TeachingClass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membersihkan seluruh data kelas dummy pada mata pelajaran Matematika (MTK)
     * yang terbuat secara otomatis saat impor jadwal awal, tanpa menghapus master rombel & siswa.
     */
    public function up(): void
    {
        try {
            DB::transaction(function () {
                $subjects = Subject::query()
                    ->where('code', 'MTK')
                    ->orWhere('name', 'like', '%Matematika%')
                    ->get();

                if ($subjects->isEmpty()) {
                    return;
                }

                $subjectIds = $subjects->pluck('id')->all();

                $classIds = TeachingClass::query()
                    ->whereIn('subject_id', $subjectIds)
                    ->pluck('id')
                    ->all();

                if (empty($classIds)) {
                    return;
                }

                // 1. Lepas relasi murid pada class_students agar master murid di rombel tetap 100% utuh
                DB::table('class_students')
                    ->whereIn('teaching_class_id', $classIds)
                    ->update(['teaching_class_id' => null]);

                // 2. Hapus data transaksi terkait kelas mengajar tersebut
                DB::table('student_grades')->whereIn('teaching_class_id', $classIds)->delete();
                DB::table('grade_columns')->whereIn('teaching_class_id', $classIds)->delete();
                DB::table('grade_weights')->whereIn('teaching_class_id', $classIds)->delete();
                DB::table('attendances')->whereIn('teaching_class_id', $classIds)->delete();
                DB::table('daily_journals')->whereIn('teaching_class_id', $classIds)->delete();

                // 3. Hapus kelas mengajar dummy tersebut
                $deletedCount = TeachingClass::query()->whereIn('id', $classIds)->delete();

                Log::info("Berhasil membersihkan {$deletedCount} kelas dummy mata pelajaran Matematika (MTK). Master murid tetap aman.");
            });
        } catch (\Throwable $e) {
            Log::error('Gagal membersihkan kelas dummy Matematika: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data dummy tidak perlu dipulihkan
    }
};
