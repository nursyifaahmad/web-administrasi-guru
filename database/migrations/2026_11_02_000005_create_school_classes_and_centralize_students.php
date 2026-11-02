<?php

use App\Models\AcademicYear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat tabel master rombongan belajar (school_classes)
        if (! Schema::hasTable('school_classes')) {
            Schema::create('school_classes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->string('grade_level', 20); // VII, VIII, IX, dst.
                $table->string('name', 60); // VII-A, VII-B, dst.
                $table->string('homeroom_teacher_name');
                $table->string('homeroom_teacher_nip', 40)->nullable();
                $table->longText('homeroom_teacher_signature')->nullable();
                $table->timestamps();

                $table->unique(['academic_year_id', 'name'], 'uniq_school_class_year_name');
            });
        }

        // 2. Tambahkan school_class_id ke teaching_classes
        if (! Schema::hasColumn('teaching_classes', 'school_class_id')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->foreignId('school_class_id')->nullable()->after('academic_year_id')->constrained('school_classes')->nullOnDelete();
            });
        }

        // 3. Tambahkan school_class_id ke class_students dan jadikan teaching_class_id nullable
        if (! Schema::hasColumn('class_students', 'school_class_id')) {
            Schema::table('class_students', function (Blueprint $table) {
                $table->foreignId('school_class_id')->nullable()->after('id')->constrained('school_classes')->cascadeOnDelete();
            });
        }

        // 4. Migrasi data kelas & murid yang sudah ada agar terhubung ke school_classes
        $this->migrateExistingClassesAndStudents();
    }

    protected function migrateExistingClassesAndStudents(): void
    {
        $existingClasses = DB::table('teaching_classes')->orderBy('id')->get();
        if ($existingClasses->isEmpty()) {
            return;
        }

        $now = now();
        $classMap = []; // key: "{year_id}-{clean_name}" => school_class_id

        foreach ($existingClasses as $tc) {
            $yearId = (int) $tc->academic_year_id;
            $cleanName = trim((string) $tc->class_name);
            $key = "{$yearId}-" . mb_strtolower($cleanName);

            if (! isset($classMap[$key])) {
                $existingSchoolClass = DB::table('school_classes')
                    ->where('academic_year_id', $yearId)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])
                    ->first();

                if ($existingSchoolClass) {
                    $schoolClassId = $existingSchoolClass->id;
                } else {
                    $schoolClassId = DB::table('school_classes')->insertGetId([
                        'academic_year_id' => $yearId,
                        'grade_level' => trim((string) ($tc->grade_level ?: 'VII')),
                        'name' => $cleanName,
                        'homeroom_teacher_name' => $tc->homeroom_teacher_name ?: '-',
                        'homeroom_teacher_nip' => $tc->homeroom_teacher_nip,
                        'homeroom_teacher_signature' => $tc->homeroom_teacher_signature,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $classMap[$key] = $schoolClassId;
            } else {
                $schoolClassId = $classMap[$key];
            }

            DB::table('teaching_classes')
                ->where('id', $tc->id)
                ->update(['school_class_id' => $schoolClassId]);
        }

        // Hubungkan murid-murid yang ada ke school_class_id
        foreach ($classMap as $key => $schoolClassId) {
            // Ambil semua teaching_class yang terhubung ke school_class ini
            $teachingClassIds = DB::table('teaching_classes')
                ->where('school_class_id', $schoolClassId)
                ->pluck('id')
                ->toArray();

            if (empty($teachingClassIds)) {
                continue;
            }

            // Tentukan teaching_class yang memiliki murid terbanyak sebagai sumber kanonikal
            $bestTcId = DB::table('class_students')
                ->whereIn('teaching_class_id', $teachingClassIds)
                ->selectRaw('teaching_class_id, COUNT(*) as cnt')
                ->groupBy('teaching_class_id')
                ->orderByDesc('cnt')
                ->value('teaching_class_id');

            if (! $bestTcId) {
                continue;
            }

            // Pasang school_class_id untuk murid pada teaching_class kanonikal tersebut
            DB::table('class_students')
                ->where('teaching_class_id', $bestTcId)
                ->update(['school_class_id' => $schoolClassId]);

            $canonicalStudents = DB::table('class_students')
                ->where('school_class_id', $schoolClassId)
                ->get();

            $canonicalByName = [];
            $canonicalByOrder = [];
            foreach ($canonicalStudents as $cs) {
                $canonicalByName[mb_strtolower(trim($cs->full_name))] = $cs->id;
                $canonicalByOrder[(int) $cs->order_number] = $cs->id;
            }

            // Untuk teaching_class lain di rombel yang sama yang memiliki murid terduplikasi:
            $otherTcIds = array_diff($teachingClassIds, [$bestTcId]);
            foreach ($otherTcIds as $otherTcId) {
                $dupStudents = DB::table('class_students')
                    ->where('teaching_class_id', $otherTcId)
                    ->get();

                foreach ($dupStudents as $dup) {
                    $nameKey = mb_strtolower(trim($dup->full_name));
                    $targetCanonicalId = $canonicalByName[$nameKey] ?? ($canonicalByOrder[(int) $dup->order_number] ?? null);

                    if ($targetCanonicalId && $targetCanonicalId !== $dup->id) {
                        // Alihkan referensi di attendances & student_grades ke canonical ID
                        // Untuk menghindari duplicate key error jika sudah ada data pada canonical ID:
                        $existingAttDates = DB::table('attendances')
                            ->where('teaching_class_id', $otherTcId)
                            ->where('class_student_id', $targetCanonicalId)
                            ->pluck('attendance_date')
                            ->toArray();

                        DB::table('attendances')
                            ->where('teaching_class_id', $otherTcId)
                            ->where('class_student_id', $dup->id)
                            ->whereNotIn('attendance_date', $existingAttDates)
                            ->update(['class_student_id' => $targetCanonicalId]);

                        DB::table('attendances')
                            ->where('teaching_class_id', $otherTcId)
                            ->where('class_student_id', $dup->id)
                            ->delete();

                        $hasGradeOnCanonical = DB::table('student_grades')
                            ->where('teaching_class_id', $otherTcId)
                            ->where('class_student_id', $targetCanonicalId)
                            ->exists();

                        if (! $hasGradeOnCanonical) {
                            DB::table('student_grades')
                                ->where('teaching_class_id', $otherTcId)
                                ->where('class_student_id', $dup->id)
                                ->update(['class_student_id' => $targetCanonicalId]);
                        } else {
                            DB::table('student_grades')
                                ->where('teaching_class_id', $otherTcId)
                                ->where('class_student_id', $dup->id)
                                ->delete();
                        }

                        // Hapus murid duplikat
                        DB::table('class_students')->where('id', $dup->id)->delete();
                    } else {
                        // Jika murid ini belum ada di kanonikal, jadikan bagian dari rombel
                        DB::table('class_students')
                            ->where('id', $dup->id)
                            ->update(['school_class_id' => $schoolClassId]);
                        $canonicalByName[$nameKey] = $dup->id;
                        $canonicalByOrder[(int) $dup->order_number] = $dup->id;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('class_students', 'school_class_id')) {
            Schema::table('class_students', function (Blueprint $table) {
                $table->dropForeign(['school_class_id']);
                $table->dropColumn('school_class_id');
            });
        }

        if (Schema::hasColumn('teaching_classes', 'school_class_id')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->dropForeign(['school_class_id']);
                $table->dropColumn('school_class_id');
            });
        }

        Schema::dropIfExists('school_classes');
    }
};
