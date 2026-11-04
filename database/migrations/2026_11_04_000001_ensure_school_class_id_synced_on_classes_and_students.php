<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pastikan setiap teaching_classes memiliki school_class_id
        $classesWithoutSchoolClass = DB::table('teaching_classes')
            ->whereNull('school_class_id')
            ->get();

        foreach ($classesWithoutSchoolClass as $tc) {
            $yearId = $tc->academic_year_id;
            $cleanName = trim((string) $tc->class_name);
            if (! $cleanName || ! $yearId) {
                continue;
            }

            $schoolClass = DB::table('school_classes')
                ->where('academic_year_id', $yearId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])
                ->first();

            if (! $schoolClass) {
                $now = now();
                $scId = DB::table('school_classes')->insertGetId([
                    'academic_year_id' => $yearId,
                    'grade_level' => trim((string) ($tc->grade_level ?: 'VII')),
                    'name' => $cleanName,
                    'homeroom_teacher_name' => $tc->homeroom_teacher_name ?: '-',
                    'homeroom_teacher_nip' => $tc->homeroom_teacher_nip,
                    'homeroom_teacher_signature' => $tc->homeroom_teacher_signature,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $scId = $schoolClass->id;
            }

            DB::table('teaching_classes')
                ->where('id', $tc->id)
                ->update(['school_class_id' => $scId]);
        }

        // 2. Pastikan setiap class_students memiliki school_class_id (jika sebelumnya hanya punya teaching_class_id)
        $studentsWithoutSchoolClass = DB::table('class_students')
            ->whereNull('school_class_id')
            ->whereNotNull('teaching_class_id')
            ->get();

        foreach ($studentsWithoutSchoolClass as $student) {
            $tc = DB::table('teaching_classes')->where('id', $student->teaching_class_id)->first();
            if ($tc && $tc->school_class_id) {
                DB::table('class_students')
                    ->where('id', $student->id)
                    ->update(['school_class_id' => $tc->school_class_id]);
            }
        }

        // 3. Pastikan teaching_class_id terisi di class_students jika null (menggunakan id teaching_classes pertama pada rombel tsb)
        $studentsWithoutTc = DB::table('class_students')
            ->whereNull('teaching_class_id')
            ->whereNotNull('school_class_id')
            ->get();

        foreach ($studentsWithoutTc as $student) {
            $firstTcId = DB::table('teaching_classes')
                ->where('school_class_id', $student->school_class_id)
                ->value('id');

            if ($firstTcId) {
                DB::table('class_students')
                    ->where('id', $student->id)
                    ->update(['teaching_class_id' => $firstTcId]);
            }
        }
    }

    public function down(): void
    {
        // No-op: migrasi pembersihan data dan sinkronisasi id
    }
};
