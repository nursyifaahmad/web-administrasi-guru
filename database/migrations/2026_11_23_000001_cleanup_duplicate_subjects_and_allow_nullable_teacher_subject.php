<?php

use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Izinkan teacher_subjects.subject_id bernilai NULL + tambahkan role_label untuk tugas non-mapel (Kep.Sek., dll.)
        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'teacher_subjects'
                AND COLUMN_NAME = 'subject_id'
                AND REFERENCED_TABLE_NAME = 'subjects'
                AND CONSTRAINT_SCHEMA = DATABASE()
            ");

            if (! empty($foreignKeys)) {
                Schema::table('teacher_subjects', function (Blueprint $table) {
                    $table->dropForeign(['subject_id']);
                });
            }

            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->unsignedBigInteger('subject_id')->nullable()->change();
                if (! Schema::hasColumn('teacher_subjects', 'role_label')) {
                    $table->string('role_label', 120)->nullable()->after('subject_id');
                }
            });

            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->foreign('subject_id')
                    ->references('id')
                    ->on('subjects')
                    ->nullOnDelete();
            });
        } else {
            try {
                Schema::table('teacher_subjects', function (Blueprint $table) {
                    $table->dropForeign(['subject_id']);
                });
            } catch (\Throwable) {
                // SQLite fallback bila nama FK berbeda
            }

            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->unsignedBigInteger('subject_id')->nullable()->change();
                if (! Schema::hasColumn('teacher_subjects', 'role_label')) {
                    $table->string('role_label', 120)->nullable()->after('subject_id');
                }
            });

            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->foreign('subject_id')
                    ->references('id')
                    ->on('subjects')
                    ->nullOnDelete();
            });
        }

        // 2. Bersihkan mapel palsu jabatan non-KBM (Kep.Sek.) dan gabungkan mapel duplikat 0 kelas ke mapel induk
        SchoolScheduleService::cleanupDuplicateSubjects();
    }

    public function down(): void
    {
        if (Schema::hasColumn('teacher_subjects', 'role_label')) {
            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->dropColumn('role_label');
            });
        }
    }
};
