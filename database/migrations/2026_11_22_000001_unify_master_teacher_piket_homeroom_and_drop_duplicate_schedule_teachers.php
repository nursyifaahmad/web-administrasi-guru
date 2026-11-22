<?php

use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom piket_days di tabel users (Master Guru)
        if (! Schema::hasColumn('users', 'piket_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('piket_days')->nullable()->after('default_subject');
            });
        }

        // 2. Tambah teaching_code_normalized unik di teacher_subjects
        if (! Schema::hasColumn('teacher_subjects', 'teaching_code_normalized')) {
            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->string('teaching_code_normalized', 30)->nullable()->after('teaching_code');
            });
        }

        // Backfill teaching_code_normalized sambil memastikan tidak ada bentrok sebelum pasang unique index
        $existingTs = DB::table('teacher_subjects')->orderBy('id')->get();
        $seenCodes = [];
        foreach ($existingTs as $ts) {
            $norm = $this->normalizeCode($ts->teaching_code);
            if ($norm !== null && isset($seenCodes[$norm])) {
                // Bersihkan duplikat residu pada baris lebih baru agar indeks unik dapat dibuat aman
                $norm = null;
            }
            if ($norm !== null) {
                $seenCodes[$norm] = true;
            }
            DB::table('teacher_subjects')
                ->where('id', $ts->id)
                ->update(['teaching_code_normalized' => $norm]);
        }

        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->unique('teaching_code_normalized', 'uniq_teacher_subjects_normalized_code');
        });

        // 3. Tambah homeroom_teacher_id di school_classes (Master Rombel) + unique per tahun ajaran
        if (! Schema::hasColumn('school_classes', 'homeroom_teacher_id')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->foreignId('homeroom_teacher_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 4. Migrasikan seluruh data dari school_schedule_teachers (jika ada) ke Master Guru & Master Rombel
        if (Schema::hasTable('school_schedule_teachers')) {
            $this->migrateScheduleTeachersIntoMasterData();
        }

        // Pastikan school_classes yang baru memiliki nama wali kelas tanpa ID tetap terhubung bila cocok dengan users
        $this->linkExistingSchoolClassesByTeacherName();

        Schema::table('school_classes', function (Blueprint $table) {
            $table->unique(['academic_year_id', 'homeroom_teacher_id'], 'uniq_school_class_year_homeroom_teacher');
        });

        // 5. Pensiunkan / hapus tabel ganda school_schedule_teachers
        Schema::dropIfExists('school_schedule_teachers');
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_schedule_teachers')) {
            Schema::create('school_schedule_teachers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20)->index();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('homeroom_class', 30)->nullable();
                $table->string('piket_day', 20)->nullable();
                $table->timestamps();

                $table->unique(['academic_year_id', 'code']);
            });
        }

        if (Schema::hasColumn('school_classes', 'homeroom_teacher_id')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->dropUnique('uniq_school_class_year_homeroom_teacher');
                $table->dropConstrainedForeignId('homeroom_teacher_id');
            });
        }

        if (Schema::hasColumn('teacher_subjects', 'teaching_code_normalized')) {
            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->dropUnique('uniq_teacher_subjects_normalized_code');
                $table->dropColumn('teaching_code_normalized');
            });
        }

        if (Schema::hasColumn('users', 'piket_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('piket_days');
            });
        }
    }

    protected function normalizeCode(?string $code): ?string
    {
        $clean = strtoupper((string) preg_replace('/\s+/', '', trim((string) $code)));

        return $clean !== '' ? $clean : null;
    }

    protected function normalizePiketDay(?string $day): ?string
    {
        $upper = strtoupper(trim((string) $day));
        if ($upper === '' || $upper === '-') {
            return null;
        }
        if ($upper === 'JUMAT' || $upper === 'JUM’AT') {
            return "JUM'AT";
        }
        $valid = ['SENIN', 'SELASA', 'RABU', 'KAMIS', "JUM'AT", 'SABTU'];

        return in_array($upper, $valid, true) ? $upper : null;
    }

    protected function cleanAlpha(?string $str): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z]/', '', (string) $str));
    }

    protected function isSimpleNameMatch(?string $a, ?string $b): bool
    {
        $ca = $this->cleanAlpha($a);
        $cb = $this->cleanAlpha($b);
        if ($ca === '' || $cb === '') {
            return false;
        }
        if ($ca === $cb || (strlen($ca) >= 5 && strlen($cb) >= 5 && (str_contains($ca, $cb) || str_contains($cb, $ca)))) {
            return true;
        }
        $pa = $this->cleanAlpha(explode(',', (string) $a)[0] ?? '');
        $pb = $this->cleanAlpha(explode(',', (string) $b)[0] ?? '');

        return strlen($pa) >= 4 && strlen($pb) >= 4 && ($pa === $pb || str_contains($pa, $pb) || str_contains($pb, $pa));
    }

    protected function migrateScheduleTeachersIntoMasterData(): void
    {
        $legacyRows = DB::table('school_schedule_teachers')->orderBy('id')->get();
        if ($legacyRows->isEmpty()) {
            return;
        }

        $now = now();
        $defaultPassword = Hash::make('guru123');
        $users = DB::table('users')->get();
        $subjects = DB::table('subjects')->get();
        $usedNormalizedCodes = DB::table('teacher_subjects')
            ->whereNotNull('teaching_code_normalized')
            ->pluck('user_id', 'teaching_code_normalized')
            ->all();

        foreach ($legacyRows as $row) {
            $normCode = $this->normalizeCode($row->code);
            if ($normCode === null) {
                continue;
            }

            $user = $row->user_id ? $users->firstWhere('id', $row->user_id) : null;
            if (! $user) {
                $user = $users->first(fn ($u) => $this->isSimpleNameMatch($u->name, $row->name));
            }

            if (! $user) {
                $baseSlug = Str::slug(explode(',', (string) $row->name)[0] ?: ('guru-' . $normCode), '.');
                $email = ($baseSlug ?: ('guru.' . strtolower($normCode))) . '@smpn2lemahabang.sch.id';
                $suffix = 1;
                while (DB::table('users')->where('email', $email)->exists()) {
                    $email = ($baseSlug ?: 'guru') . '.' . strtolower($normCode) . '.' . $suffix++ . '@smpn2lemahabang.sch.id';
                }

                $newUserId = DB::table('users')->insertGetId([
                    'name' => trim((string) $row->name),
                    'email' => $email,
                    'role' => 'guru',
                    'default_subject' => trim((string) $row->subject) ?: null,
                    'piket_days' => null,
                    'is_active' => true,
                    'password' => $defaultPassword,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $user = DB::table('users')->where('id', $newUserId)->first();
                $users->push($user);
            }

            // Sinkronkan piket_days
            $piketDay = $this->normalizePiketDay($row->piket_day ?? null);
            if ($piketDay !== null) {
                $currentDays = json_decode((string) ($user->piket_days ?? '[]'), true);
                $currentDays = is_array($currentDays) ? $currentDays : [];
                if (! in_array($piketDay, $currentDays, true)) {
                    $currentDays[] = $piketDay;
                    DB::table('users')->where('id', $user->id)->update([
                        'piket_days' => json_encode(array_values($currentDays)),
                    ]);
                    $user->piket_days = json_encode(array_values($currentDays));
                }
            }

            // Cocokkan Subject yang sudah ada memakai alias resmi (tanpa memotong awalan Bahasa 'B.')
            $subjLabel = trim((string) ($row->subject ?? ''));
            $subject = null;
            if ($subjLabel !== '' && ! SchoolScheduleService::isNonTeachingRoleLabel($subjLabel)) {
                $subject = $subjects->first(fn ($s) => SchoolScheduleService::isExactSubjectLabelMatch($subjLabel, $s->code, $s->name))
                    ?: $subjects->first(fn ($s) => SchoolScheduleService::isSubjectLabelMatch($subjLabel, $s->code, $s->name));

                if (! $subject) {
                    $cleanName = SchoolScheduleService::normalizeSubjectLabel($subjLabel) ?: $subjLabel;
                    $subjCodeBase = null;
                    foreach (SchoolScheduleService::CANONICAL_SUBJECT_CATALOG as $canon) {
                        if (SchoolScheduleService::isSubjectLabelMatch($subjLabel, $canon['code'], $canon['name'])) {
                            $cleanName = $canon['name'];
                            $subjCodeBase = $canon['code'];
                            break;
                        }
                    }

                    $subjCodeBase = $subjCodeBase
                        ?: (strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $cleanName), 0, 12)) ?: ('MP' . $normCode));
                    $subjCode = $subjCodeBase;
                    $sIdx = 1;
                    while (DB::table('subjects')->where('code', $subjCode)->exists()) {
                        $subjCode = substr($subjCodeBase, 0, 10) . $sIdx++;
                    }

                    $newSubjId = DB::table('subjects')->insertGetId([
                        'code' => $subjCode,
                        'name' => $cleanName,
                        'default_kktp' => 75,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $subject = DB::table('subjects')->where('id', $newSubjId)->first();
                    $subjects->push($subject);
                }
            }

            if ($subject && ! isset($usedNormalizedCodes[$normCode])) {
                $existingPair = DB::table('teacher_subjects')
                    ->where('user_id', $user->id)
                    ->where('subject_id', $subject->id)
                    ->first();

                if ($existingPair) {
                    if ($existingPair->teaching_code_normalized === null) {
                        DB::table('teacher_subjects')->where('id', $existingPair->id)->update([
                            'teaching_code' => $normCode,
                            'teaching_code_normalized' => $normCode,
                        ]);
                        $usedNormalizedCodes[$normCode] = $user->id;
                    }
                } else {
                    DB::table('teacher_subjects')->insert([
                        'user_id' => $user->id,
                        'subject_id' => $subject->id,
                        'teaching_code' => $normCode,
                        'teaching_code_normalized' => $normCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $usedNormalizedCodes[$normCode] = $user->id;
                }
            }

            // Sinkronkan Wali Kelas ke school_classes tahun ajaran terkait
            $rawHomeroom = trim((string) ($row->homeroom_class ?? ''));
            if ($rawHomeroom !== '') {
                $normClass = strtoupper(str_replace(['KELAS', 'RUANG', 'ROMBEL', ' ', '-', '_', '.'], '', $rawHomeroom));
                $normClass = str_replace(['VIII', 'VII', 'IX'], ['8', '7', '9'], $normClass);
                if (preg_match('/^([789])([A-Z])$/', $normClass, $m)) {
                    $formatted = (match ($m[1]) {
                        '7' => 'VII',
                        '8' => 'VIII',
                        '9' => 'IX',
                    }) . '-' . $m[2];

                    $sc = DB::table('school_classes')
                        ->where('academic_year_id', $row->academic_year_id)
                        ->where('name', $formatted)
                        ->first();

                    $alreadyAssignedElsewhere = DB::table('school_classes')
                        ->where('academic_year_id', $row->academic_year_id)
                        ->where('homeroom_teacher_id', $user->id)
                        ->exists();

                    if ($sc && $sc->homeroom_teacher_id === null && ! $alreadyAssignedElsewhere) {
                        DB::table('school_classes')->where('id', $sc->id)->update([
                            'homeroom_teacher_id' => $user->id,
                            'homeroom_teacher_name' => $user->name,
                            'homeroom_teacher_nip' => $user->nip,
                            'homeroom_teacher_signature' => $user->signature_data,
                        ]);
                    }
                }
            }

            // Tautkan item jadwal ke user_id & teacher_name
            DB::table('school_schedule_items')
                ->where('academic_year_id', $row->academic_year_id)
                ->whereRaw("UPPER(REPLACE(TRIM(teacher_code), ' ', '')) = ?", [$normCode])
                ->update([
                    'user_id' => $user->id,
                    'teacher_name' => $user->name,
                    'subject_id' => $subject?->id,
                    'subject_name' => $subject?->name ?? ($subjLabel ?: null),
                ]);
        }
    }

    protected function linkExistingSchoolClassesByTeacherName(): void
    {
        $users = DB::table('users')->get();
        $classes = DB::table('school_classes')->whereNull('homeroom_teacher_id')->orderBy('id')->get();

        foreach ($classes as $sc) {
            if (empty($sc->homeroom_teacher_name) || $sc->homeroom_teacher_name === '-') {
                continue;
            }
            $matched = $users->first(fn ($u) => $this->isSimpleNameMatch($u->name, $sc->homeroom_teacher_name));
            if (! $matched) {
                continue;
            }
            $alreadyUsed = DB::table('school_classes')
                ->where('academic_year_id', $sc->academic_year_id)
                ->where('homeroom_teacher_id', $matched->id)
                ->exists();
            if (! $alreadyUsed) {
                DB::table('school_classes')->where('id', $sc->id)->update([
                    'homeroom_teacher_id' => $matched->id,
                ]);
            }
        }
    }
};
