<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\TeachingClass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeSubjectClassesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:purge-subject 
                            {subject_code=MTK : Kode mata pelajaran yang ingin dibersihkan kelasnya (default: MTK)} 
                            {--force : Jalankan pembersihan tanpa konfirmasi prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bersihkan seluruh data kelas mengajar dan transaksi pada mata pelajaran tertentu tanpa menghapus data master rombel dan murid';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $code = strtoupper(trim($this->argument('subject_code')));
        $subject = Subject::query()
            ->where('code', $code)
            ->orWhere('name', 'like', "%{$code}%")
            ->first();

        if (! $subject) {
            $this->error("Mata pelajaran dengan kode atau nama '{$code}' tidak ditemukan.");
            return self::FAILURE;
        }

        $classQuery = TeachingClass::query()->where('subject_id', $subject->id);
        $totalClasses = $classQuery->count();

        if ($totalClasses === 0) {
            $this->info("Tidak ada kelas mengajar aktif pada mata pelajaran {$subject->name} ({$subject->code}).");
            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$totalClasses} kelas mengajar pada mata pelajaran {$subject->name} ({$subject->code}).");

        if (! $this->option('force')) {
            if (! $this->confirm("Apakah Anda yakin ingin menghapus seluruh {$totalClasses} buku kelas mapel ini? (Master murid di rombel tetap aman)")) {
                $this->info('Pembersihan dibatalkan.');
                return self::SUCCESS;
            }
        }

        $classIds = $classQuery->pluck('id')->all();

        DB::transaction(function () use ($classIds, $subject) {
            // 1. Lepas relasi murid pada class_students
            DB::table('class_students')
                ->whereIn('teaching_class_id', $classIds)
                ->update(['teaching_class_id' => null]);

            // 2. Hapus data transaksi terkait
            $gradesCount = DB::table('student_grades')->whereIn('teaching_class_id', $classIds)->delete();
            $compCount = DB::table('grade_columns')->whereIn('teaching_class_id', $classIds)->delete();
            $weightCount = DB::table('grade_weights')->whereIn('teaching_class_id', $classIds)->delete();
            $attCount = DB::table('attendances')->whereIn('teaching_class_id', $classIds)->delete();
            $jourCount = DB::table('daily_journals')->whereIn('teaching_class_id', $classIds)->delete();

            // 3. Hapus teaching_classes
            TeachingClass::query()->whereIn('id', $classIds)->delete();
        });

        $this->info("✓ Sukses! {$totalClasses} kelas mengajar pada mata pelajaran {$subject->name} ({$subject->code}) telah berhasil dibersihkan.");
        $this->comment("Master siswa pada tabel class_students dan rombel pada school_classes tetap 100% utuh.");

        return self::SUCCESS;
    }
}
