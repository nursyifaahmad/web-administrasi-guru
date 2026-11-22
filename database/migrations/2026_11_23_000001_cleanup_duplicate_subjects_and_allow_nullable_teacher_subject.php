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

        // 2. Bersihkan mapel palsu jabatan non-KBM (seperti KEPSEK / Kep.Sek.) dan gabungkan mapel duplikat 0 kelas ke mapel induk
        $this->cleanupNonTeachingAndDuplicateSubjects();
    }

    public function down(): void
    {
        if (Schema::hasColumn('teacher_subjects', 'role_label')) {
            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->dropColumn('role_label');
            });
        }
    }

    protected function cleanupNonTeachingAndDuplicateSubjects(): void
    {
        $subjects = DB::table('subjects')->orderBy('id')->get();
        if ($subjects->isEmpty()) {
            return;
        }

        $hasModulesTable = Schema::hasTable('teaching_modules');
        $canonicalCodes = collect(SchoolScheduleService::CANONICAL_SUBJECT_CATALOG)
            ->pluck('code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->all();

        $classCounts = DB::table('teaching_classes')
            ->selectRaw('subject_id, COUNT(*) as cnt')
            ->groupBy('subject_id')
            ->pluck('cnt', 'subject_id')
            ->all();

        $moduleCounts = $hasModulesTable
            ? DB::table('teaching_modules')
                ->selectRaw('subject_id, COUNT(*) as cnt')
                ->groupBy('subject_id')
                ->pluck('cnt', 'subject_id')
                ->all()
            : [];

        $removedIds = [];

        // A. Lepaskan baris non-mapel (Kep.Sek., Kepala Sekolah, Wakasek, dll.) ke teacher_subjects.role_label
        foreach ($subjects as $sub) {
            if (! SchoolScheduleService::isNonTeachingRoleLabel($sub->name)
                && ! SchoolScheduleService::isNonTeachingRoleLabel($sub->code)) {
                continue;
            }

            $cCnt = (int) ($classCounts[$sub->id] ?? 0);
            $mCnt = (int) ($moduleCounts[$sub->id] ?? 0);
            if ($cCnt > 0 || $mCnt > 0) {
                continue;
            }

            $roleText = trim((string) $sub->name) ?: 'Kep.Sek.';
            DB::table('teacher_subjects')
                ->where('subject_id', $sub->id)
                ->update([
                    'subject_id' => null,
                    'role_label' => $roleText,
                ]);

            DB::table('school_schedule_items')
                ->where('subject_id', $sub->id)
                ->update(['subject_id' => null]);

            DB::table('subjects')->where('id', $sub->id)->delete();
            $removedIds[$sub->id] = true;
        }

        // B. Susun daftar kandidat induk mapel yang sah (punya kelas/modul aktif atau berkode katalog kanonik)
        $remainingSubjects = $subjects->reject(fn ($s) => isset($removedIds[$s->id]))->values();
        $canonicalTargets = $remainingSubjects->filter(function ($s) use ($classCounts, $moduleCounts, $canonicalCodes) {
            $hasUsage = ((int) ($classCounts[$s->id] ?? 0) + (int) ($moduleCounts[$s->id] ?? 0)) > 0;
            $isCatalogCode = in_array(strtoupper(trim((string) $s->code)), $canonicalCodes, true);

            return $hasUsage || $isCatalogCode;
        })->sortByDesc(function ($s) use ($classCounts, $moduleCounts, $canonicalCodes) {
            $usageScore = ((int) ($classCounts[$s->id] ?? 0) * 1000) + ((int) ($moduleCounts[$s->id] ?? 0) * 100);
            $catalogBonus = in_array(strtoupper(trim((string) $s->code)), $canonicalCodes, true) ? 50 : 0;

            return $usageScore + $catalogBonus;
        })->values();

        foreach ($remainingSubjects as $dup) {
            if (isset($removedIds[$dup->id])) {
                continue;
            }

            $dupCode = strtoupper(trim((string) $dup->code));
            if (in_array($dupCode, $canonicalCodes, true)) {
                continue;
            }

            $dupUsage = (int) ($classCounts[$dup->id] ?? 0) + (int) ($moduleCounts[$dup->id] ?? 0);
            if ($dupUsage > 0) {
                continue;
            }

            $target = $canonicalTargets->first(function ($t) use ($dup) {
                if ((int) $t->id === (int) $dup->id) {
                    return false;
                }

                return SchoolScheduleService::isSubjectLabelMatch($dup->name, $t->code, $t->name)
                    || SchoolScheduleService::isSubjectLabelMatch($dup->code, $t->code, $t->name);
            });

            if (! $target) {
                continue;
            }

            $this->mergeDuplicateSubject($dup, $target);
            $removedIds[$dup->id] = true;
        }
    }

    protected function mergeDuplicateSubject(object $dup, object $target): void
    {
        $dupRows = DB::table('teacher_subjects')->where('subject_id', $dup->id)->get();

        foreach ($dupRows as $row) {
            $existingCanonical = DB::table('teacher_subjects')
                ->where('user_id', $row->user_id)
                ->where('subject_id', $target->id)
                ->first();

            if ($existingCanonical) {
                $dupCodeNorm = $row->teaching_code_normalized ?? null;
                $dupCodeRaw = $row->teaching_code ?? null;

                DB::table('teacher_subjects')->where('id', $row->id)->delete();

                if (empty($existingCanonical->teaching_code_normalized) && ! empty($dupCodeNorm)) {
                    DB::table('teacher_subjects')
                        ->where('id', $existingCanonical->id)
                        ->update([
                            'teaching_code' => $dupCodeRaw,
                            'teaching_code_normalized' => $dupCodeNorm,
                        ]);
                }
            } else {
                DB::table('teacher_subjects')
                    ->where('id', $row->id)
                    ->update(['subject_id' => $target->id]);
            }
        }

        DB::table('school_schedule_items')
            ->where('subject_id', $dup->id)
            ->update([
                'subject_id' => $target->id,
                'subject_name' => $target->name,
            ]);

        DB::table('users')
            ->where('default_subject', $dup->name)
            ->update(['default_subject' => $target->name]);

        DB::table('subjects')->where('id', $dup->id)->delete();
    }
};
