<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SchoolScheduleService
{
    public static function cleanAlphaString(?string $str): string
    {
        if (empty($str)) {
            return '';
        }
        return strtolower(preg_replace('/[^a-zA-Z]/', '', $str));
    }

    /**
     * Normalisasi potongan jam tunggal (mis. "7.40" atau "07:40") menjadi format "HH:MM".
     */
    protected static function normalizeClockToken(string $token, string $fallback = ''): string
    {
        $clean = str_replace('.', ':', trim($token));
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $clean, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $clean !== '' ? $clean : $fallback;
    }

    /**
     * Konversi string waktu seperti "07.40 - 08.10" menjadi format jam mulai "07:40"
     * untuk keperluan pengurutan kronologis
     */
    public static function extractStartTime(?string $timeRange): string
    {
        if (empty($timeRange)) {
            return '99:99';
        }
        $parts = explode('-', $timeRange);

        return self::normalizeClockToken((string) ($parts[0] ?? ''), '99:99');
    }

    /**
     * Konversi string waktu seperti "07.40 - 08.10" menjadi format jam selesai "08:10".
     */
    public static function extractEndTime(?string $timeRange): string
    {
        if (empty($timeRange)) {
            return '';
        }
        $parts = explode('-', $timeRange);

        return self::normalizeClockToken((string) ($parts[1] ?? ($parts[0] ?? '')));
    }

    /**
     * Normalisasi nama rombel / kelas menjadi kode ringkas (misal: "VII-A", "VII A", "7-A" -> "7A")
     */
    public static function normalizeClassName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }
        $upper = strtoupper(trim($name));
        $upper = str_replace(['KELAS', 'RUANG', 'ROMBEL'], '', $upper);
        $upper = str_replace([' ', '-', '_', '.'], '', $upper);
        $upper = str_replace('VIII', '8', $upper);
        $upper = str_replace('VII', '7', $upper);
        $upper = str_replace('IX', '9', $upper);
        return trim($upper);
    }

    /**
     * Normalisasi kode mengajar agar konsisten antar form Master Guru, Matriks, & Excel (mis. '14 a' -> '14A').
     */
    public static function normalizeTeachingCode(?string $code): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', trim((string) $code)));
    }

    /**
     * Membandingkan kemiripan nama guru dengan memperhitungkan variasi penulisan,
     * singkatan populer (M., Moh., Muh., Muhammad, Muhamad), gelar, serta kata kunci nama.
     */
    public static function isNameMatch(?string $name1, ?string $name2): bool
    {
        if (empty($name1) || empty($name2)) {
            return false;
        }

        $c1 = self::cleanAlphaString($name1);
        $c2 = self::cleanAlphaString($name2);

        if ($c1 === $c2 || str_contains($c1, $c2) || str_contains($c2, $c1)) {
            return true;
        }

        // Hilangkan variasi awalan singkatan nama (muhammad, muhamad, mohammad, mohamad, moch, moh, muh -> m)
        $norm1 = preg_replace('/^(muhammad|muhamad|mohammad|mohamad|moch|moh|muh)/', 'm', $c1);
        $norm2 = preg_replace('/^(muhammad|muhamad|mohammad|mohamad|moch|moh|muh)/', 'm', $c2);
        if ($norm1 === $norm2 || str_contains($norm1, $norm2) || str_contains($norm2, $norm1)) {
            return true;
        }

        // Cek nama pokok sebelum koma gelar
        $pure1 = self::cleanAlphaString(explode(',', $name1)[0]);
        $pure2 = self::cleanAlphaString(explode(',', $name2)[0]);
        if (strlen($pure1) >= 4 && strlen($pure2) >= 4) {
            if ($pure1 === $pure2 || str_contains($pure1, $pure2) || str_contains($pure2, $pure1)) {
                return true;
            }
            $pNorm1 = preg_replace('/^(muhammad|muhamad|mohammad|mohamad|moch|moh|muh)/', 'm', $pure1);
            $pNorm2 = preg_replace('/^(muhammad|muhamad|mohammad|mohamad|moch|moh|muh)/', 'm', $pure2);
            if ($pNorm1 === $pNorm2 || str_contains($pNorm1, $pNorm2) || str_contains($pNorm2, $pNorm1)) {
                return true;
            }
            // Levenshtein distance untuk mengatasi selisih 1-2 huruf pada nama pokok
            if (levenshtein($pure1, $pure2) <= 2) {
                return true;
            }
            if ($pNorm1 !== '' && $pNorm2 !== '' && levenshtein($pNorm1, $pNorm2) <= 2) {
                return true;
            }
        }

        // Cek kata kunci nama unik
        foreach (['rifki', 'irfan', 'syifa', 'nurhaliza', 'wijaya', 'santoso'] as $kw) {
            if (str_contains($c1, $kw) && str_contains($c2, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Katalog kanonik mata pelajaran standar SMP sebagai rujukan pencocokan alias dan inisialisasi awal DB kosong.
     *
     * @var list<array{code: string, name: string}>
     */
    public const CANONICAL_SUBJECT_CATALOG = [
        ['code' => 'MTK', 'name' => 'Matematika'],
        ['code' => 'BIN', 'name' => 'Bahasa Indonesia'],
        ['code' => 'BIG', 'name' => 'Bahasa Inggris'],
        ['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam'],
        ['code' => 'IPS', 'name' => 'Ilmu Pengetahuan Sosial'],
        ['code' => 'PKN', 'name' => 'Pendidikan Pancasila'],
        ['code' => 'PAI', 'name' => 'Pendidikan Agama Islam & Budi Pekerti'],
        ['code' => 'INF', 'name' => 'Informatika'],
        ['code' => 'PJK', 'name' => 'PJOK'],
        ['code' => 'SBD', 'name' => 'Seni Budaya & Prakarya'],
        ['code' => 'BD', 'name' => 'Bahasa Daerah'],
        ['code' => 'BK', 'name' => 'Bimbingan Konseling'],
    ];

    /**
     * Deteksi apakah teks pada kolom mata pelajaran adalah jabatan/tugas non-KBM (mis. Kep.Sek.),
     * yang tidak boleh dijadikan baris Mata Pelajaran / KKTP.
     */
    public static function isNonTeachingRoleLabel(?string $rawLabel): bool
    {
        $clean = self::cleanAlphaString($rawLabel);
        if ($clean === '') {
            return false;
        }

        if (in_array($clean, [
            'kepsek',
            'kepalasekolah',
            'kepalamadrasah',
            'kepalasek',
            'wakasek',
            'wakilkepalasekolah',
            'wakilkapalasekolah',
            'tu',
            'tatausaha',
            'kepalatu',
            'bendahara',
            'operator',
        ], true)) {
            return true;
        }

        return str_starts_with($clean, 'kepsek') || str_starts_with($clean, 'kepalasekolah');
    }

    /**
     * Bersihkan teks mata pelajaran tanpa merusak awalan huruf Bahasa (`B.`),
     * membuang bullet opsi (`a.`, `b. B.`), serta memperluas singkatan `B.` -> `Bahasa`.
     */
    public static function normalizeSubjectLabel(?string $rawLabel): string
    {
        $label = trim((string) preg_replace('/\s+/', ' ', trim((string) $rawLabel)));
        if ($label === '') {
            return '';
        }

        // Hapus bullet opsi sub-item (contoh: "a.PAI", "a. SENI BUDAYA", "b. B. DAERAH") tanpa menghapus "B.INDONESIA"
        $label = trim((string) preg_replace('/^(?:a\.\s*|[b-z]\.\s*(?=[a-z]\.))/i', '', $label));

        // Ekspansi "B." / "Bhs." menjadi "Bahasa "
        $label = trim((string) preg_replace('/^(?:bhs|b)\.\s*/i', 'Bahasa ', $label));

        return trim((string) preg_replace('/\s+/', ' ', $label));
    }

    /**
     * Pencocokan persis (prioritas utama sebelum alias luas) terhadap sepasang kode & nama mata pelajaran.
     */
    public static function isExactSubjectLabelMatch(?string $rawSubject, ?string $subjectCode, ?string $subjectName): bool
    {
        if (empty($rawSubject) || self::isNonTeachingRoleLabel($rawSubject)) {
            return false;
        }

        $cleanRaw = self::cleanAlphaString(self::normalizeSubjectLabel($rawSubject));
        $cleanRawOriginal = self::cleanAlphaString($rawSubject);
        $cleanName = self::cleanAlphaString(self::normalizeSubjectLabel($subjectName));
        $cleanCode = strtolower(trim((string) $subjectCode));

        if ($cleanRaw === '' && $cleanRawOriginal === '') {
            return false;
        }

        if ($cleanRaw === $cleanName || $cleanRawOriginal === $cleanName) {
            return true;
        }

        if ($cleanCode !== '' && ($cleanRaw === $cleanCode || $cleanRawOriginal === $cleanCode)) {
            return true;
        }

        // Jika sekolah secara khusus punya mapel bernama "Bahasa Cirebon" atau "Bahasa Sunda"
        if (str_contains($cleanRaw, 'cirebon') && str_contains($cleanName, 'cirebon')) {
            return true;
        }
        if (str_contains($cleanRaw, 'sunda') && str_contains($cleanName, 'sunda')) {
            return true;
        }

        return false;
    }

    /**
     * Membandingkan teks nama mata pelajaran dari jadwal/CSV terhadap kode & nama master Subject (termasuk alias).
     * Mendukung B.INDONESIA -> BIN, B. INGGRIS -> BIG, b. B. DAERAH / B. DAERAH / B. CIREBON -> BD, PENJAS -> PJOK, dll.
     */
    public static function isSubjectLabelMatch(?string $rawSubject, ?string $subjectCode, ?string $subjectName): bool
    {
        if (empty($rawSubject) || self::isNonTeachingRoleLabel($rawSubject)) {
            return false;
        }

        if (self::isExactSubjectLabelMatch($rawSubject, $subjectCode, $subjectName)) {
            return true;
        }

        $cleanRaw = self::cleanAlphaString(self::normalizeSubjectLabel($rawSubject));
        $cleanName = self::cleanAlphaString(self::normalizeSubjectLabel($subjectName));
        $cleanCode = strtolower(trim((string) $subjectCode));

        if ($cleanRaw === '') {
            return false;
        }

        if (strlen($cleanRaw) >= 3 && strlen($cleanName) >= 3
            && (str_starts_with($cleanName, $cleanRaw) || str_starts_with($cleanRaw, $cleanName))) {
            return true;
        }

        $expandedRaw = preg_replace('/^b([a-z]{3,})/', 'bahasa$1', $cleanRaw);
        if ($expandedRaw === $cleanName
            || (strlen((string) $expandedRaw) >= 3 && strlen($cleanName) >= 3
                && (str_starts_with($cleanName, (string) $expandedRaw) || str_starts_with((string) $expandedRaw, $cleanName)))) {
            return true;
        }

        if (str_contains($cleanRaw, 'inggris') || $cleanRaw === 'bing' || $cleanRaw === 'big') {
            return str_contains($cleanName, 'inggris') || $cleanCode === 'big';
        }
        if (str_contains($cleanRaw, 'indonesi') || $cleanRaw === 'bindo' || $cleanRaw === 'bin') {
            return str_contains($cleanName, 'indonesi') || $cleanCode === 'bin';
        }
        if (str_contains($cleanRaw, 'daerah') || str_contains($cleanRaw, 'cirebon') || $cleanRaw === 'bd' || str_contains($cleanRaw, 'mulok')) {
            return str_contains($cleanName, 'daerah') || $cleanCode === 'bd';
        }
        if (str_starts_with($cleanRaw, 'mat') || $cleanRaw === 'mtk') {
            return str_contains($cleanName, 'matematika') || $cleanCode === 'mtk';
        }
        if (str_contains($cleanRaw, 'penjas') || str_contains($cleanRaw, 'pjok') || str_contains($cleanRaw, 'pendidikanjasmani') || str_contains($cleanRaw, 'jasmaniolahraga') || $cleanRaw === 'pjk') {
            return str_contains($cleanName, 'pjok') || str_contains($cleanName, 'penjas') || str_contains($cleanName, 'pendidikanjasmani') || str_contains($cleanName, 'jasmaniolahraga') || in_array($cleanCode, ['pjk', 'pjok', 'penjas'], true);
        }
        if (str_contains($cleanRaw, 'seni') || $cleanRaw === 'sbk' || $cleanRaw === 'sbd' || $cleanRaw === 'sbdp') {
            return str_contains($cleanName, 'seni') || in_array($cleanCode, ['sbd', 'sbk', 'sbdp'], true);
        }
        if (str_contains($cleanRaw, 'pancasila') || $cleanRaw === 'pkn' || $cleanRaw === 'ppkn') {
            return str_contains($cleanName, 'pancasila') || str_contains($cleanName, 'pkn') || in_array($cleanCode, ['pkn', 'ppkn'], true);
        }
        if ((str_contains($cleanRaw, 'agama') && str_contains($cleanRaw, 'islam')) || str_contains($cleanRaw, 'pabp') || $cleanRaw === 'pai') {
            return str_contains($cleanName, 'agama') || str_contains($cleanName, 'pai') || in_array($cleanCode, ['pai', 'pabp'], true);
        }
        if (str_contains($cleanRaw, 'bimbingan') || str_contains($cleanRaw, 'konseling') || $cleanRaw === 'bk' || $cleanRaw === 'layananbk') {
            return str_contains($cleanName, 'bimbingan') || str_contains($cleanName, 'konseling') || $cleanCode === 'bk';
        }
        if (str_contains($cleanRaw, 'informatika') || $cleanRaw === 'tik' || $cleanRaw === 'inf') {
            return str_contains($cleanName, 'informatika') || $cleanCode === 'inf';
        }
        if (str_contains($cleanRaw, 'pengetahuanalam') || $cleanRaw === 'ipa') {
            return str_contains($cleanName, 'pengetahuanalam') || $cleanCode === 'ipa';
        }
        if (str_contains($cleanRaw, 'pengetahuansosial') || $cleanRaw === 'ips') {
            return str_contains($cleanName, 'pengetahuansosial') || $cleanCode === 'ips';
        }

        if (strlen($cleanRaw) >= 5 && str_contains($cleanName, $cleanRaw)) {
            return true;
        }

        return false;
    }

    /**
     * Membandingkan teks nama mata pelajaran dari jadwal/CSV dengan master data Subject.
     */
    public static function isSubjectMatch(?string $rawSubject, Subject $subject): bool
    {
        return self::isSubjectLabelMatch($rawSubject, $subject->code, $subject->name);
    }

    /**
     * Cari Subject tercocok pada koleksi Subject dengan prioritas kecocokan persis sebelum alias.
     *
     * @param  iterable<Subject>  $subjects
     */
    public static function findBestMatchingSubject(?string $rawSubject, iterable $subjects): ?Subject
    {
        if (empty($rawSubject) || self::isNonTeachingRoleLabel($rawSubject)) {
            return null;
        }

        foreach ($subjects as $s) {
            if (self::isExactSubjectLabelMatch($rawSubject, $s->code, $s->name)) {
                return $s;
            }
        }

        foreach ($subjects as $s) {
            if (self::isSubjectLabelMatch($rawSubject, $s->code, $s->name)) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Bersihkan mata pelajaran palsu/duplikat (0 kelas) agar relasinya kembali terhubung ke mapel induk sah
     * serta pindahkan tugas non-KBM (seperti Kep.Sek.) ke teacher_subjects.role_label.
     */
    public static function cleanupDuplicateSubjects(): void
    {
        $subjects = DB::table('subjects')->orderBy('id')->get();
        if ($subjects->isEmpty()) {
            return;
        }

        $hasModulesTable = Schema::hasTable('teaching_modules');
        $canonicalCodes = array_values(array_unique(array_merge(
            collect(self::CANONICAL_SUBJECT_CATALOG)->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all(),
            ['PJOK', 'PPKN', 'PABP', 'SBDP']
        )));

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
            if (! self::isNonTeachingRoleLabel($sub->name)
                && ! self::isNonTeachingRoleLabel($sub->code)) {
                continue;
            }

            $usage = (int) ($classCounts[$sub->id] ?? 0) + (int) ($moduleCounts[$sub->id] ?? 0);
            if ($usage > 0) {
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

        // B. Susun kandidat induk mapel sah (diurutkan berdasarkan penggunaan kelas & modul tertinggi, lalu kode kanonik)
        $remainingSubjects = $subjects->reject(fn ($s) => isset($removedIds[$s->id]))->values();
        $usageOf = fn ($s): int => (int) ($classCounts[$s->id] ?? 0) + (int) ($moduleCounts[$s->id] ?? 0);

        $canonicalTargets = $remainingSubjects->filter(function ($s) use ($usageOf, $canonicalCodes) {
            $isCatalogCode = in_array(strtoupper(trim((string) $s->code)), $canonicalCodes, true);

            return $usageOf($s) > 0 || $isCatalogCode;
        })->sortByDesc(function ($s) use ($classCounts, $moduleCounts, $canonicalCodes) {
            $usageScore = ((int) ($classCounts[$s->id] ?? 0) * 1000) + ((int) ($moduleCounts[$s->id] ?? 0) * 100);
            $catalogBonus = in_array(strtoupper(trim((string) $s->code)), $canonicalCodes, true) ? 50 : 0;

            return $usageScore + $catalogBonus;
        })->values();

        foreach ($remainingSubjects as $dup) {
            if (isset($removedIds[$dup->id])) {
                continue;
            }

            $dupUsage = $usageOf($dup);
            if ($dupUsage > 0) {
                continue;
            }

            $dupCode = strtoupper(trim((string) $dup->code));
            $target = $canonicalTargets->first(function ($t) use ($dup, $dupCode, $canonicalCodes, $usageOf) {
                if ((int) $t->id === (int) $dup->id) {
                    return false;
                }

                // Apabila kedua-duanya 0 kelas, jangan hapus kode yang termasuk katalog kanonik
                if ($usageOf($t) === 0 && in_array($dupCode, $canonicalCodes, true)) {
                    return false;
                }

                return self::isSubjectLabelMatch($dup->name, $t->code, $t->name)
                    || self::isSubjectLabelMatch($dup->code, $t->code, $t->name);
            });

            if (! $target) {
                continue;
            }

            self::mergeSubjectRows($dup, $target);
            $removedIds[$dup->id] = true;
        }
    }

    protected static function mergeSubjectRows(object $dup, object $target): void
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

    public const RAW_WAKASEK_CSV = <<<'CSV'
JADWAL PELAJARAN SELAMA PERBAIKAN RUANG KELAS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
SEMESTER GANJIL,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
TAHUN PELAJARAN 2026/2027,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
HARI,JM,WAKTU,KELAS VII,,,,,,,KELAS IX,,,,,,,HARI,JM,WAKTU,KELAS VIII,,,,,,,Kode,Nama Guru,Mata Pelajaran,No.,NAMA GURU,WAL KELAS
,KE,,A,B,C,D,E,F,G,A,B,C,D,E,F,G,,KE,,A,B,C,D,E,F,G,,,,,,
SENIN,0,07.00 - 07.40,UPACARA BENDERA,,,,,,,UPACARA BENDERA,,,,,,,SENIN,1,12.15 - 12.45,11,15,23,21,22,17,34,1,"TETI KUSMIYATI, S.Pd., MM",Kep.Sek.,1,"LINLIN SUHERLINA, SE",7A
,1,07.40 - 08.10,2,4,7,24A,19,22,27,16,6,29,5,11,12,28,,2,12.45 - 13.15,11,15,23,14A,22,17,34,2,"HJ.SUPRIYATIN, M.Pd.I.",PAI,2,"TRI MULYANI, S.Pd",7B
,2,08.10 - 08.40,2,4,7,14B,9,10,27,16,6,29,13,11,12,28,,3,13.15 - 13.45,8,11,25,23,28,17,24A,3,"RUDI INDRAWAN, S.Pd.",PENJAS,3,"SRI RAHAYU, S.Pd.",7C
,3,08.40 - 09.10,2,4,7,14B,9,10,18,16,21,29,13,11,28,5,,4,13.45 - 14.15,8,11,25,34,28,21,27,4,Dra. EEM AMIRAH,B.INDONESIA,4,"WATI'AH, S.Pd",7D
,4,09.10 - 09.40,7,2,26,30,10,9,18,31,21,6,17,12,24A,5,,,14.15 - 14.45,ISTIRAHAT,,,,,,,5,"TOHIR, S.Ag.",PAI,5,"NOPI NOPIANI, S.Pd",7E
,,09.40 - 10.10,ISTIRAHAT,,,,,,,ISTIRAHAT,,,,,,,,5,14.45 - 15.15,25,8,14A,34,16,22,27,6,"MAMAN SUPARMAN, SE",IPS,6,"DUDI GUMILAR, S.Pd.",7F
,5,10.10 - 10.40,7,2,26,30,10,9,18,31,21,6,17,12,19,5,,6,15.15 - 15.45,25,14A,24B,15,16,28,22,7,"WATI'AH, S.Pd",MATEMATIKA,7,"ISMAIL, S.Pd. I",7G
,6,10.40 - 11.10,4,7,2,10,32,8,9,13,18,20,27,17,19,21,,7,15.45 - 16.15,25,14A,24B,15,16,28,22,8,"SUSILAWATI,S.Pd.",MATEMATIKA,8,"SUSILAWATI,S.Pd.",8A
,7,11.10 - 11.40,4,7,2,10,32,8,9,13,24A,20,27,17,19,21,,,,,,,,,,,9,"LINLIN SUHERLINA, SE",IPS,9,"INTAN PUTRI R,S.Pd.",8B
SELASA,0,07.00 - 07.30,SHOLAT DHUHA,,,,,,,SHOLAT DHUHA,,,,,,,,1,12.00 - 12.30,11,24B,30,21,15,34,7,10,"TRI MULYANI, S.Pd",B. INGGRIS,10,"SOETAN ASRUL SANI,S.Pd.",8C
SELASA,1,07.30 - 08.00,3,3,10,9,4,31,27,20,13,19,29,17,28,11,SELASA,2,12.30 - 13.00,11,24B,30,21,15,34,7,11,"ADITYA SRI RUMAWAS, S.Pd",IPA,11,"AHMAD NASHIH,S.Pd.",8D
,2,08.00 - 08.30,3,3,10,9,4,31,27,20,13,19,29,17,28,11,,3,13.00 - 13.30,11,25,29,23,28,15,21,12,"MUHAMMAD IRFAN, S.Pd",B. INGGRIS,12,"AFNITA PUSPITASARI, S.Pd.",8E
,3,08.30 - 09.00,3,3,26,18,4,10,9,20,19,24A,27,13,12,23,,4,13.30 - 14.00,22,25,29,23,28,15,17,13,"SURYATI, S.Pd",B. CIREBON,13,"LAELATUL NURJANAH, S.E",8F
,4,09.00 - 09.30,7,4,26,18,2,10,9,24A,19,21,27,13,12,3,,5,14.00 - 14.25,ISTIRAHAT,,,,,,,14 a,"ISMAIL, S.Pd. I",a.PAI,14,"ADE SRI PUTIH, S.Kom., M.Pd",8G
,,09.30 - 09.55,ISTIRAHAT,,,,,,,ISTIRAHAT,,,,,,,,,14.25 - 14.55,22,30,20,25,34,28,17,14 b,"ISMAIL, S.Pd. I",b. B. DAERAH,15,"PURWANTO, S.Pd",9A
,5,09.55 - 10.25,10,4,26,22,9,2,18,6,31,21,19,28,13,3,,6,14.55 - 15.25,22,16,20,25,34,28,17,15,"INTAN PUTRI R,S.Pd.",B. INGGRIS,16,"MOH. RIFKI FIRDIANSYAH, S.Pd",9B
,6,10.25 - 10.55,10,4,30,32,9,2,18,6,31,21,19,28,13,3,,7,15.25 - 15.55,32,16,15,29,13,26,27,16,"SOETAN ASRUL SANI,S.Pd.",PENJASKES,17,"NURUL MAULINA F., S.Pd",9C
,7,10.55 - 11.25,4,7,30,32,18,9,2,31,18,5,6,19,21,13,,8,15.55 - 16.25,32,16,15,29,13,26,27,17,"KIKI SUKIRNO,S.Pd.",B. INDONESIA,18,"LAILINA MUZIYAH, S.Pd",9D
,8,11.25 - 11.55,4,7,30,32,18,9,2,31,18,5,6,19,23,13,,,,,,,,,,,18,"DUDI GUMILAR, S.Pd.",IPA,19,"KIKI SUKIRNO,S.Pd.",9E
RABU,0,07.00 - 07.30,SHOLAT DHUHA,,,,,,,SHOLAT DHUHA,,,,,,,RABU,1,12.00 - 12.30,33,11,23,14A,28,26,30,19,"NOPI NOPIANI, S.Pd",SENI BUDAYA,20,"TOHIR, S.Ag.",9F
,1,07.30 - 08.00,7,10,13,3,19,31,14B,18,27,20,29,11,17,12,,2,12.30 - 13.00,8,11,33,14A,21,28,30,20,"M. RIFKI FIRDIANSYAH,S.Pd",MATEMATIKA,21,"YOGI SETIAWAN, S.Pd",9G
,2,08.00 - 08.30,7,10,13,3,19,31,14B,18,27,20,29,11,17,12,,3,13.00 - 13.30,8,11,25,34,21,30,13,21,"MAKSUM, S.Kom., MPd",INFORMATIKA,,,
,3,08.30 - 09.00,10,26,2,3,18,19,32,27,31,20,29,12,11,17,,4,13.30 - 14.00,8,33,25,34,21,30,13,22,"ADE SRI PUTIH, S.Kom., M.Pd",PEND. PANCASILA,No.,NAMA GURU PIKET,HARI
,4,09.00 - 09.30,10,26,22,30,3,19,32,27,31,5,20,12,11,17,,,14.00 - 14.25,ISTIRAHAT,,,,,,,23,"AHMAD NASHIH,S.Pd.",PEND. PANCASILA,1,"MOH. RIFKI FIRDOANSYAH, S.Pd",SENIN
,,09.30 - 09.55,ISTIRAHAT,,,,,,,ISTIRAHAT,,,,,,,,5,14.25 - 14.55,25,15,29,33,22,21,26,24a,"TETI OKTAVIANI, S.Pd",LAYANAN BK,2,"ISMAIL, S.Pd. I",SELASA
,5,09.55 - 10.25,13,7,22,2,3,18,31,19,6,29,20,17,5,23,,6,14.55 - 15.25,25,15,16,16,29,21,26,24b,"TETI OKTAVIANI, S.Pd",B. DAERAH,3,"TETI OKTAVIANI, S.Pd",RABU
,6,10.25 - 10.55,13,30,22,2,3,18,31,19,6,29,20,17,5,23,,7,15.25 - 15.55,15,8,16,16,25,34,21,25,"AFNITA PUSPITASARI, S.Pd.",B.INDONESIA,4,"PURWANTO, S.Pd",KAMIS
,7,10.55 - 11.25,26,13,10,18,8,2,22,31,20,27,6,23,28,19,,8,15.55 - 16.25,15,8,16,16,25,34,21,26,"SRI RAHAYU, S.Pd.",IPA,5,"NURUL MAULINA F., S.Pd",JUM'AT
,8,11.25 - 11.55,26,13,10,18,8,32,2,31,20,27,6,23,28,19,,,,,,,,,,,27,"PURWANTO, S.Pd",B. INGGRIS,6,"LAILINA MUZIYAH, S.Pd",SABTU
KAMIS,0,07.00 - 07.30,SHOLAT DHUHA,,,,,,,SHOLAT DHUHA,,,,,,,KAMIS,1,12.00 - 12.30,34,8,29,25,14A,17,14A,28,"YOGI SETIAWAN, S.Pd",MATEMATIKA,,,
,1,07.30 - 08.00,4,30,3,9,24A,22,19,18,5,31,23,6,17,21,,2,12.30 - 13.00,34,8,29,20,24A,17,14A,29,"LAILINA MUZIYAH, S.Pd",IPA,,,
,2,08.00 - 08.30,4,30,3,9,32,22,19,21,20,31,23,6,17,24A,,3,13.00 - 13.30,24B,34,15,20,25,17,14A,30,"LAELATUL NURJANAH, S.E",a. SENI BUDAYA,,,
,3,08.30 - 09.00,30,9,3,22,8,24A,19,23,20,31,5,21,6,12,,4,13.30 - 14.00,24B,34,15,29,25,24A,17,31,"NURUL MAULINA F., S.Pd",B. INDONESIA,KETERANGAN :,,
,4,09.00 - 09.30,30,9,24A,18,8,3,31,19,20,16,5,21,6,12,,,14.00 - 14.25,ISTIRAHAT,,,,,,,32,"AHMAD NUR SYIFA, S.KoM",INFORMATIKA,1,Jadwal ini berlaku selama adanya perbaikan ruang kelas,
,,09.30 - 09.55,ISTIRAHAT,,,,,,,ISTIRAHAT,,,,,,,,5,14.25 - 14.55,25,30,34,29,15,14A,17,33,"PIOH SUSANTI, S.Pd",LAYANAN BK,2,Ketentuan Pembagian MBG,
,5,09.55 - 10.25,9,24A,4,10,8,3,31,23,18,16,19,21,11,6,,6,14.55 - 15.25,16,30,34,29,15,14A,17,34,"DEVI PURNAMAWATI, S.E.I",IPS,,* Hari Senin s.d. Kamis Mulai Pukul 11.50 WIB,
,6,10.25 - 10.55,9,32,4,10,22,3,31,23,18,16,21,24A,11,6,,7,15.25 - 15.55,16,25,14A,30,29,15,26,,,,,* Hari Jum'at Shift Pagi Pukul 10.30 WIB,
,7,10.55 - 11.25,32,10,9,4,22,32,8,18,31,13,21,5,23,17,,8,15.55 - 16.25,16,25,14A,30,29,15,26,,,,,* Hari Jum'at Shift Siang Pukul 12.30 WIB,
,8,11.25 - 11.55,24A,10,9,4,22,32,8,18,31,13,21,5,23,17,,,,,,,,,,,,,,,,
JUM'AT,0,07.00 - 08.00,KEGIATAN KEAGAMAAN,,,,,,,KEGIATAN KEAGAMAAN,,,,,,,JUM'AT,0,,,,,,,,,,,,,,
,1,08.00 - 08.30,22,9,4,7,2,14B,3,27,5,19,17,23,6,11,,1,13.00 - 13.30,15,34,30,24B,29,14A,26,,,,,,
,2,08.30 - 09.00,22,9,4,7,2,14B,3,27,5,23,17,28,6,11,,2,13.30 -14.00,15,34,21,24B,29,30,16,,,,,,
,,09.00 - 09.25,ISTIRAHAT,,,,,,,ISTIRAHAT,,,,,,,,,14.00 - 14.25,ISTIRAHAT,,,,,,,,,,,,
,3,09.25 - 10.05,9,26,7,4,18,8,3,20,27,6,24A,28,17,11,,3,14.25 - 14.55,30,25,21,15,34,13,16,,"Lemahabang,  02 Nopember  2026",,,,
,4,10.05 - 10.35,9,26,7,4,18,8,24A,20,27,6,23,28,17,19,,4,14.55 - 15.20,30,25,21,15,34,13,16,,"Kepala Sekolah,",,,,
SABTU,0,07.00 - 07.40,SABTU BERSIH / OR,,,,,,,SABTU BERSIH / OR,,,,,,,SABTU,1,12.15 - 12.45,2,14A,34,30,25,22,7,,,,,,
,1,07.40 - 08.10,32,26,9,4,10,31,22,21,16,23,17,3,5,6,,2,12.45 - 13.15,2,23,34,20,25,22,7,,,,,,
,2,08.10 - 08.40,32,2,9,4,10,31,22,21,16,23,17,3,11,6,,3,13.15 - 13.45,2,23,25,20,30,26,7,,,,,,
,3,08.40 - 09.10,22,32,4,2,14B,19,31,6,16,27,20,3,21,17,,4,13.45 - 14.15,34,23,25,20,30,26,22,,"TETI KUSMIYATI, S.Pd",,,,
,,09.10 - 09.40,30,32,4,22,14B,8,32,6,19,27,20,5,21,17,,,14.15 - 14.45,ISTIRAHAT,,,,,,,,Pembina Utama Muda / Ivc,,,,
,4,09.40 - 10.00,,,,,,,,ISTIRAHAT,,,,,,,,5,14.45 - 15.15,34,21,20,25,30,16,30,,NIP. 19701104 199512 2 002,,,,
,5,10.00 - 10.30,26,22,32,7,4,18,8,5,23,31,16,6,3,28,,6,15.15 - 15.45,30,21,20,25,14A,16,34,,,,,,
,6,10.30 - 11.00,26,22,32,7,4,18,8,5,23,31,16,6,3,28,,7,15.45 - 16.15,32,21,20,25,14A,16,34,,,,,,
,7,11.00 - 11.30,26,22,32,7,4,18,8,5,23,31,16,19,3,28,,,,,,,,,,,,,,,,
CSV;

    public const DAYS_MAP = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    /**
     * Parse hari Indonesia menjadi angka 1..6
     */
    public function parseDayOfWeek(string $dayName): int
    {
        $d = strtoupper(trim(str_replace("'", '', $dayName)));
        return match ($d) {
            'SENIN' => 1,
            'SELASA' => 2,
            'RABU' => 3,
            'KAMIS' => 4,
            'JUMAT', 'JUM’AT' => 5,
            'SABTU' => 6,
            default => 1,
        };
    }

    /**
     * Memuat jadwal resmi Wakasek SMPN 2 Lemahabang (21 Rombel, 36 Kode Guru, 2 Shift)
     * sekaligus menautkan / membuat entri terpusat pada Master Guru & Master Rombel.
     */
    public function importFromWakasekCsv(AcademicYear $academicYear, ?string $csvContent = null): array
    {
        $content = $csvContent ?: self::RAW_WAKASEK_CSV;
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        $teachersData = [];
        $homeroomMap = [];
        $piketEntries = [];

        foreach ($lines as $idx => $line) {
            if ($idx < 5) {
                continue;
            }
            $row = str_getcsv($line);
            $code = self::normalizeTeachingCode($row[27] ?? '');
            $name = trim($row[28] ?? '');
            $subject = trim($row[29] ?? '');

            if ($code !== '' && $name !== '') {
                $teachersData[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'subject' => $subject,
                ];
            }

            // Wali Kelas (baris 5 s.d. 25 dengan rombel resmi 7A-9G)
            $wkName = trim($row[31] ?? '');
            $wkClass = self::normalizeClassName($row[32] ?? '');
            if ($wkName !== '' && preg_match('/^[7-9][A-G]$/', $wkClass)) {
                $homeroomMap[$wkClass] = $wkName;
            }

            // Guru Piket (Senin s.d. Sabtu)
            $piketGuru = trim($row[31] ?? '');
            $piketHari = User::normalizePiketDay($row[32] ?? '');
            if ($piketGuru !== '' && $piketHari !== null) {
                $piketEntries[] = [
                    'day' => $piketHari,
                    'name' => $piketGuru,
                ];
            }
        }

        DB::transaction(function () use ($academicYear, $teachersData, $homeroomMap, $piketEntries, $lines) {
            // Pastikan 21 Rombel dasar tersedia sebelum sinkronisasi wali kelas
            $allClassNames = [
                '7A' => 'VII', '7B' => 'VII', '7C' => 'VII', '7D' => 'VII', '7E' => 'VII', '7F' => 'VII', '7G' => 'VII',
                '8A' => 'VIII', '8B' => 'VIII', '8C' => 'VIII', '8D' => 'VIII', '8E' => 'VIII', '8F' => 'VIII', '8G' => 'VIII',
                '9A' => 'IX', '9B' => 'IX', '9C' => 'IX', '9D' => 'IX', '9E' => 'IX', '9F' => 'IX', '9G' => 'IX',
            ];

            $schoolClassesMap = [];
            foreach ($allClassNames as $cName => $grade) {
                $formattedName = $grade . '-' . substr($cName, -1);
                $sc = SchoolClass::query()->firstOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $formattedName,
                    ],
                    [
                        'grade_level' => $grade,
                        'homeroom_teacher_name' => '-',
                    ]
                );
                $schoolClassesMap[$cName] = $sc;
                $schoolClassesMap[$formattedName] = $sc;
            }

            // Sinkronkan Master Guru (users + teacher_subjects + piket_days) & Wali Kelas (school_classes)
            $codeTeacherMap = $this->syncImportedMasterEntities($academicYear, $teachersData, $piketEntries, $homeroomMap, true);

            // Bersihkan items lama pada tahun ajaran ini
            SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

            $currentDayM = 'SENIN';
            $currentDayA = 'SENIN';

            $pagiColumns = [
                3 => '7A', 4 => '7B', 5 => '7C', 6 => '7D', 7 => '7E', 8 => '7F', 9 => '7G',
                10 => '9A', 11 => '9B', 12 => '9C', 13 => '9D', 14 => '9E', 15 => '9F', 16 => '9G',
            ];
            $siangColumns = [
                20 => '8A', 21 => '8B', 22 => '8C', 23 => '8D', 24 => '8E', 25 => '8F', 26 => '8G',
            ];

            foreach ($lines as $idx => $line) {
                if ($idx < 5) {
                    continue;
                }
                $row = str_getcsv($line);

                if (! empty(trim($row[0] ?? ''))) {
                    $currentDayM = trim($row[0]);
                }
                if (! empty(trim($row[17] ?? ''))) {
                    $currentDayA = trim($row[17]);
                }

                $jamM = trim($row[1] ?? '');
                $waktuM = trim($row[2] ?? '');
                $jamA = trim($row[18] ?? '');
                $waktuA = trim($row[19] ?? '');

                $shiftPayloads = [];
                if ($waktuM !== '') {
                    $shiftPayloads[] = [
                        'shift' => 'pagi',
                        'day_num' => $this->parseDayOfWeek($currentDayM),
                        'period_order' => is_numeric($jamM) ? (int) $jamM : 0,
                        'time_range' => $waktuM,
                        'columns' => $pagiColumns,
                    ];
                }
                if ($waktuA !== '') {
                    $shiftPayloads[] = [
                        'shift' => 'siang',
                        'day_num' => $this->parseDayOfWeek($currentDayA),
                        'period_order' => is_numeric($jamA) ? (int) $jamA : 0,
                        'time_range' => $waktuA,
                        'columns' => $siangColumns,
                    ];
                }

                foreach ($shiftPayloads as $sp) {
                    foreach ($sp['columns'] as $colIdx => $clsShort) {
                        $rawVal = trim($row[$colIdx] ?? '');
                        if ($rawVal === '') {
                            continue;
                        }

                        $normVal = self::normalizeTeachingCode($rawVal);
                        $sc = $schoolClassesMap[$clsShort] ?? null;
                        $isAct = SchoolScheduleItem::isCommonActivityLabel($rawVal);
                        $tMeta = $isAct ? null : ($codeTeacherMap[$normVal] ?? null);

                        SchoolScheduleItem::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'day_of_week' => $sp['day_num'],
                            'shift' => $sp['shift'],
                            'period_order' => $sp['period_order'],
                            'time_range' => $sp['time_range'],
                            'class_name' => $clsShort,
                            'school_class_id' => $sc?->id,
                            'teacher_code' => $isAct ? $rawVal : $normVal,
                            'teacher_name' => $tMeta['name'] ?? ($isAct ? $rawVal : null),
                            'subject_name' => $tMeta['subject'] ?? null,
                            'user_id' => $tMeta['user_id'] ?? null,
                            'subject_id' => $tMeta['subject_id'] ?? null,
                        ]);
                    }
                }
            }
        });

        $conflicts = $this->detectConflicts($academicYear);

        return [
            'teachers_count' => $this->getMasterTeacherDirectory($academicYear)->count(),
            'items_count' => SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count(),
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Memeriksa kesiapan 2 Pilar Master Data sebelum membuat jadwal baru dari awal:
     * 1. Data Master Guru (terdaftar, memiliki penugasan mapel dan kode mengajar)
     * 2. Data Master Rombel & Murid (terdaftar di tabel SchoolClass untuk tahun ajaran aktif)
     */
    public function checkMasterPrerequisites(AcademicYear $academicYear): array
    {
        // 1. Pilar Master Guru
        $activeTeachers = User::query()
            ->with(['teacherSubjects.subject'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'guru')->orWhereHas('teacherSubjects');
            })
            ->orderBy('name')
            ->get();

        $totalTeachers = $activeTeachers->count();
        $incompleteTeachers = $activeTeachers->filter(function ($t) {
            $hasSubjects = $t->teacherSubjects->isNotEmpty() || ! empty($t->default_subject);
            $hasCodes = ! empty($t->teaching_codes_list);

            return ! $hasSubjects || ! $hasCodes;
        })->values();

        $isTeachersReady = ($totalTeachers > 0) && ($incompleteTeachers->isEmpty());

        // 2. Pilar Master Rombel & Murid
        $classes = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->withCount(['students', 'activeStudents'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $totalClasses = $classes->count();
        $classesByGrade = $classes->groupBy('grade_level');
        $isClassesReady = $totalClasses > 0;

        $isReady = $isTeachersReady && $isClassesReady;

        return [
            'is_ready' => $isReady,
            'teachers' => [
                'is_ready' => $isTeachersReady,
                'total' => $totalTeachers,
                'complete' => $totalTeachers - $incompleteTeachers->count(),
                'incomplete_count' => $incompleteTeachers->count(),
                'incomplete_list' => $incompleteTeachers->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'nip' => $t->nip,
                    'has_subject' => $t->teacherSubjects->isNotEmpty() || ! empty($t->default_subject),
                    'has_code' => ! empty($t->teaching_codes_list),
                    'missing' => empty($t->teaching_codes_list) && ($t->teacherSubjects->isEmpty() && empty($t->default_subject))
                        ? 'Belum ada mapel & kode mengajar'
                        : (empty($t->teaching_codes_list) ? 'Belum ada kode mengajar' : 'Belum ada mapel'),
                ])->all(),
            ],
            'classes' => [
                'is_ready' => $isClassesReady,
                'total' => $totalClasses,
                'by_grade' => $classesByGrade->map->count()->all(),
                'list' => $classes->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => self::normalizeClassName($c->name),
                    'grade_level' => $c->grade_level,
                    'students_count' => $c->active_students_count ?: $c->students_count,
                    'homeroom_teacher' => $c->homeroom_teacher_name,
                ])->all(),
            ],
        ];
    }

    /**
     * Direktori terpadu kode mengajar aktif langsung dari Master Guru (User + TeacherSubject)
     * yang otomatis terhubung ke tugas Wali Kelas (SchoolClass) & Hari Piket (User::piket_days).
     *
     * @return Collection<int, object>
     */
    public function getMasterTeacherDirectory(?AcademicYear $academicYear = null): Collection
    {
        $yearId = $academicYear?->id ?: AcademicYear::active()?->id;

        $teacherSubjects = TeacherSubject::query()
            ->with(['user', 'subject'])
            ->whereNotNull('teaching_code_normalized')
            ->where('teaching_code_normalized', '!=', '')
            ->get()
            ->filter(fn (TeacherSubject $ts) => $ts->user !== null && $ts->user->is_active);

        $homeroomByTeacher = $yearId
            ? SchoolClass::query()
                ->where('academic_year_id', $yearId)
                ->whereNotNull('homeroom_teacher_id')
                ->get()
                ->keyBy('homeroom_teacher_id')
            : collect();

        return $teacherSubjects
            ->map(function (TeacherSubject $ts) use ($homeroomByTeacher) {
                $user = $ts->user;
                $sc = $homeroomByTeacher->get($user->id);
                $homeroomCode = $sc ? self::normalizeClassName($sc->name) : null;
                $piketDays = $user->piket_days_list;

                return (object) [
                    'id' => $ts->id,
                    'code' => $ts->teaching_code_normalized ?: self::normalizeTeachingCode($ts->teaching_code),
                    'name' => $user->name,
                    'subject' => $ts->subject?->name ?? ($ts->role_label ?: ($user->default_subject ?: '-')),
                    'subject_code' => $ts->subject?->code,
                    'subject_id' => $ts->subject_id,
                    'user_id' => $user->id,
                    'nip' => $user->nip,
                    'homeroom_class' => $homeroomCode !== '' ? $homeroomCode : null,
                    'homeroom_class_name' => $sc?->name,
                    'school_class_id' => $sc?->id,
                    'piket_days' => $piketDays,
                    'piket_day' => ! empty($piketDays) ? implode(', ', $piketDays) : null,
                ];
            })
            ->sort(fn (object $a, object $b) => strnatcasecmp((string) $a->code, (string) $b->code))
            ->values();
    }

    /**
     * Daftar jadwal Guru Piket langsung dari Master Guru (`users.piket_days`),
     * terurut Senin s.d. Sabtu lalu nama guru tanpa duplikasi untuk guru berkode ganda.
     *
     * @return Collection<int, object>
     */
    public function getPiketScheduleList(): Collection
    {
        $orderMap = array_flip(User::PIKET_DAYS);
        $users = User::query()
            ->with(['teacherSubjects'])
            ->where('is_active', true)
            ->whereNotNull('piket_days')
            ->get();

        $entries = collect();
        $seenKeys = [];

        foreach ($users as $user) {
            foreach ($user->piket_days_list as $day) {
                $dedupKey = "{$day}|{$user->id}";
                if (isset($seenKeys[$dedupKey])) {
                    continue;
                }
                $seenKeys[$dedupKey] = true;

                $entries->push((object) [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'nip' => $user->nip,
                    'piket_day' => $day,
                    'day_order' => $orderMap[$day] ?? 99,
                    'teaching_codes_string' => $user->teaching_codes_string,
                ]);
            }
        }

        return $entries->sort(function (object $a, object $b) {
            if ($a->day_order !== $b->day_order) {
                return $a->day_order <=> $b->day_order;
            }

            return strnatcasecmp($a->name, $b->name);
        })->values();
    }

    /**
     * Kelompokkan jadwal Guru Piket per hari Senin s.d. Sabtu dari Master Guru.
     *
     * @return array<string, list<object>>
     */
    public function getPiketGroupedByDay(): array
    {
        $grouped = [];
        foreach (User::PIKET_DAYS as $day) {
            $grouped[$day] = [];
        }

        foreach ($this->getPiketScheduleList() as $entry) {
            if (isset($grouped[$entry->piket_day])) {
                $grouped[$entry->piket_day][] = $entry;
            }
        }

        return $grouped;
    }

    /**
     * Sinkronkan metadata item jadwal pada suatu tahun ajaran langsung dari Data Master Guru
     * (`users`, `teacher_subjects`, dan `subjects`).
     */
    public function syncScheduleTeachersFromMaster(AcademicYear $academicYear): int
    {
        $syncedCount = 0;

        DB::transaction(function () use ($academicYear, &$syncedCount) {
            $teacherSubjects = TeacherSubject::query()
                ->with(['user', 'subject'])
                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                ->get();

            foreach ($teacherSubjects as $ts) {
                $code = $ts->teaching_code_normalized ?: self::normalizeTeachingCode($ts->teaching_code);
                if ($code === '') {
                    continue;
                }

                $user = $ts->user;
                $subjectName = $ts->subject?->name ?? ($ts->role_label ?: $user->default_subject);

                $affected = SchoolScheduleItem::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->whereRaw("UPPER(REPLACE(TRIM(teacher_code), ' ', '')) = ?", [$code])
                    ->update([
                        'teacher_code' => $code,
                        'user_id' => $user->id,
                        'teacher_name' => $user->name,
                        'subject_id' => $ts->subject_id,
                        'subject_name' => $subjectName,
                    ]);

                $syncedCount += max(1, $affected);
            }

            // Sinkronkan juga item yang sudah memiliki user_id tanpa kode atau berbeda ejaan nama
            $activeUsers = User::query()->where('is_active', true)->get();
            foreach ($activeUsers as $user) {
                SchoolScheduleItem::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('user_id', $user->id)
                    ->update(['teacher_name' => $user->name]);
            }

            // Tautkan item slot tanpa user_id bila nama pengajar di item cocok dengan Master Guru
            $unlinkedItems = SchoolScheduleItem::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereNull('user_id')
                ->whereNotNull('teacher_name')
                ->get();

            foreach ($unlinkedItems as $item) {
                if ($item->isCommonActivity()) {
                    continue;
                }
                $matched = $activeUsers->first(fn (User $u) => self::isNameMatch($item->teacher_name, $u->name));
                if ($matched) {
                    $item->update([
                        'user_id' => $matched->id,
                        'teacher_name' => $matched->name,
                    ]);
                    $syncedCount++;
                }
            }
        });

        return $syncedCount;
    }

    /**
     * Validasi anti-bentrok dan sinkronkan seluruh kode guru, piket, serta wali kelas hasil impor
     * langsung ke sumber data tunggal (`users`, `teacher_subjects`, `subjects`, `school_classes`).
     *
     * @param array<string, array{code?: string, name: string, subject?: string}> $parsedTeachers
     * @param list<array{day: string, name: string}> $parsedPiket
     * @param array<string, string> $parsedHomerooms Map classCode ('7A') => teacherName
     * @return array<string, array{user_id: int, name: string, subject: ?string, subject_id: ?int}>
     *
     * @throws \InvalidArgumentException
     */
    protected function syncImportedMasterEntities(
        AcademicYear $academicYear,
        array $parsedTeachers,
        array $parsedPiket,
        array $parsedHomerooms,
        bool $allowCanonicalSubjectCreation = false
    ): array {
        // 1. Validasi anti-bentrok internal & terhadap pemilik kode aktif di Master Guru
        $normalizedFileTeachers = [];
        foreach ($parsedTeachers as $rawCode => $tData) {
            $normCode = self::normalizeTeachingCode((string) ($tData['code'] ?? $rawCode));
            $tName = trim((string) ($tData['name'] ?? ''));
            $tSubj = trim((string) ($tData['subject'] ?? ''));
            if ($normCode === '' || $tName === '') {
                continue;
            }

            if (isset($normalizedFileTeachers[$normCode])) {
                if (! self::isNameMatch($normalizedFileTeachers[$normCode]['name'], $tName)) {
                    throw new \InvalidArgumentException(
                        "Bentrok Kode Guru '{$normCode}' di dalam file: dipakai oleh '{$normalizedFileTeachers[$normCode]['name']}' dan '{$tName}'."
                    );
                }
                continue;
            }

            $normalizedFileTeachers[$normCode] = [
                'code' => $normCode,
                'name' => $tName,
                'subject' => $tSubj,
            ];
        }

        $existingByCode = TeacherSubject::query()
            ->with(['user', 'subject'])
            ->whereNotNull('teaching_code_normalized')
            ->get()
            ->keyBy('teaching_code_normalized');

        foreach ($normalizedFileTeachers as $normCode => $tData) {
            $holderTs = $existingByCode->get($normCode);
            if ($holderTs && $holderTs->user && ! self::isNameMatch($holderTs->user->name, $tData['name'])) {
                throw new \InvalidArgumentException(
                    "Bentrok Kode Mengajar '{$normCode}': di file terdaftar atas nama '{$tData['name']}', sedangkan di Data Master Guru kode '{$normCode}' sudah dimiliki oleh '{$holderTs->user->name}'."
                );
            }
        }

        // 2. Validasi anti-bentrok Wali Kelas dalam file (1 guru tidak boleh menjadi wali > 1 rombel)
        $homeroomAssignments = [];
        foreach ($parsedHomerooms as $clsCode => $wName) {
            $normCls = self::normalizeClassName((string) $clsCode);
            $cleanWName = trim((string) $wName);
            if ($normCls === '' || $cleanWName === '' || $cleanWName === '-') {
                continue;
            }

            foreach ($homeroomAssignments as $assignedEntry) {
                if ($assignedEntry['class_code'] !== $normCls && self::isNameMatch($assignedEntry['teacher_name'], $cleanWName)) {
                    throw new \InvalidArgumentException(
                        "Bentrok Wali Kelas pada file: guru '{$cleanWName}' tercatat menjadi Wali Kelas untuk lebih dari satu rombel ({$assignedEntry['class_code']} & {$normCls})."
                    );
                }
            }
            $homeroomAssignments[$normCls] = [
                'class_code' => $normCls,
                'teacher_name' => $cleanWName,
            ];
        }

        $users = User::query()->get();
        $subjects = Subject::query()->get();
        $codeMetaMap = [];

        // 3. Buat / tautkan akun Master Guru & kode mengajar untuk tabel kode guru
        foreach ($normalizedFileTeachers as $normCode => $tData) {
            $holderTs = $existingByCode->get($normCode);
            $user = $holderTs?->user ?: $users->first(fn (User $u) => self::isNameMatch($u->name, $tData['name']));

            if (! $user) {
                $user = $this->createMasterTeacherAccount($tData['name'], $tData['subject'], $normCode, $users);
            } elseif (! $user->is_active) {
                $user->update(['is_active' => true]);
            }

            $resolved = $this->resolveImportedSubjectAssignment(
                $tData['subject'],
                $normCode,
                $holderTs,
                $user,
                $subjects,
                $allowCanonicalSubjectCreation
            );

            $subject = $resolved['subject'];
            $roleLabel = $resolved['role_label'];

            if ($subject) {
                $existingPair = TeacherSubject::query()
                    ->where('user_id', $user->id)
                    ->where('subject_id', $subject->id)
                    ->first();

                if ($existingPair) {
                    $dirtyUpdates = [];
                    if ($existingPair->teaching_code_normalized !== $normCode) {
                        $dirtyUpdates['teaching_code'] = $normCode;
                    }
                    if ($existingPair->role_label !== null) {
                        $dirtyUpdates['role_label'] = null;
                    }
                    if (! empty($dirtyUpdates)) {
                        $existingPair->update($dirtyUpdates);
                    }
                } else {
                    TeacherSubject::query()->create([
                        'user_id' => $user->id,
                        'subject_id' => $subject->id,
                        'role_label' => null,
                        'teaching_code' => $normCode,
                    ]);
                }

                $displaySubject = $subject->name;
                $subjectId = $subject->id;
            } else {
                $existingNonSubject = ($holderTs && $holderTs->user_id === $user->id && $holderTs->subject_id === null)
                    ? $holderTs
                    : TeacherSubject::query()
                        ->where('user_id', $user->id)
                        ->whereNull('subject_id')
                        ->where('teaching_code_normalized', $normCode)
                        ->first();

                if ($existingNonSubject) {
                    $existingNonSubject->update([
                        'role_label' => $roleLabel,
                        'teaching_code' => $normCode,
                    ]);
                } else {
                    TeacherSubject::query()->create([
                        'user_id' => $user->id,
                        'subject_id' => null,
                        'role_label' => $roleLabel,
                        'teaching_code' => $normCode,
                    ]);
                }

                $displaySubject = $roleLabel;
                $subjectId = null;
            }

            if (empty($user->default_subject) && ! empty($displaySubject)) {
                $user->update(['default_subject' => $displaySubject]);
            }

            $codeMetaMap[$normCode] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'subject' => $displaySubject,
                'subject_id' => $subjectId,
            ];
        }

        // Lengkapi juga peta kode untuk guru yang sudah ada di Master Guru walau file tidak menyertakan tabel legenda
        foreach ($existingByCode as $normCode => $ts) {
            if (! isset($codeMetaMap[$normCode]) && $ts->user && $ts->user->is_active) {
                $codeMetaMap[$normCode] = [
                    'user_id' => $ts->user->id,
                    'name' => $ts->user->name,
                    'subject' => $ts->subject?->name ?? ($ts->role_label ?: $ts->user->default_subject),
                    'subject_id' => $ts->subject_id,
                ];
            }
        }

        // 4. Sinkronkan Guru Piket ke users.piket_days
        if (! empty($parsedPiket)) {
            $piketDaysByUserId = [];
            foreach ($parsedPiket as $pRow) {
                $pDay = User::normalizePiketDay($pRow['day'] ?? '');
                $pName = trim((string) ($pRow['name'] ?? ''));
                if ($pDay === null || $pName === '') {
                    continue;
                }

                $pUser = $users->first(fn (User $u) => self::isNameMatch($u->name, $pName));
                if (! $pUser) {
                    $pUser = $this->createMasterTeacherAccount($pName, null, 'piket', $users);
                }
                $piketDaysByUserId[$pUser->id][] = $pDay;
            }

            // Perbarui jadwal piket agar mencerminkan tabel piket terbaru tanpa menyisakan entri usang
            User::query()->whereNotNull('piket_days')->update(['piket_days' => null]);
            foreach ($piketDaysByUserId as $uId => $days) {
                $normDays = User::normalizePiketDays($days);
                User::query()->where('id', $uId)->update([
                    'piket_days' => ! empty($normDays) ? json_encode($normDays) : null,
                ]);
            }
        }

        // 5. Sinkronkan Wali Kelas ke school_classes tahun ajaran aktif + Buku Kelas
        if (! empty($homeroomAssignments)) {
            $resolvedHomeroomUsers = [];
            foreach ($homeroomAssignments as $normCls => $entry) {
                $wkUser = $users->first(fn (User $u) => self::isNameMatch($u->name, $entry['teacher_name']));
                if (! $wkUser) {
                    $wkUser = $this->createMasterTeacherAccount($entry['teacher_name'], null, 'wk-' . strtolower($normCls), $users);
                }
                $resolvedHomeroomUsers[$normCls] = $wkUser;
            }

            $assignedUserIds = collect($resolvedHomeroomUsers)->pluck('id')->unique()->all();

            // Lepaskan sementara posisi lama untuk rombel target dan guru target agar pemindahan kelas bebas constraint unik
            $existingYearClasses = SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->get();

            foreach ($existingYearClasses as $existingSc) {
                $scCode = self::normalizeClassName($existingSc->name);
                if (isset($resolvedHomeroomUsers[$scCode]) || in_array($existingSc->homeroom_teacher_id, $assignedUserIds, true)) {
                    $existingSc->update(['homeroom_teacher_id' => null]);
                }
            }

            foreach ($resolvedHomeroomUsers as $normCls => $wkUser) {
                $formattedName = self::formatClassDisplayName($normCls);
                $gradeRoman = explode('-', $formattedName)[0] ?: 'VII';

                $sc = SchoolClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->get()
                    ->first(fn (SchoolClass $c) => self::normalizeClassName($c->name) === $normCls);

                if (! $sc) {
                    $sc = SchoolClass::query()->create([
                        'academic_year_id' => $academicYear->id,
                        'grade_level' => $gradeRoman,
                        'name' => $formattedName,
                        'homeroom_teacher_name' => $wkUser->name,
                    ]);
                }

                $sc->applyHomeroomTeacher($wkUser);
            }
        }

        return $codeMetaMap;
    }

    protected function createMasterTeacherAccount(string $name, ?string $subjectLabel, string $fallbackSlug, Collection $usersCache): User
    {
        $baseName = trim(explode(',', $name)[0] ?? $name);
        $slug = Str::slug($baseName ?: ('guru-' . $fallbackSlug), '.');
        if ($slug === '') {
            $slug = 'guru.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fallbackSlug));
        }

        $email = $slug . '@smpn2lemahabang.sch.id';
        $idx = 1;
        while (User::query()->where('email', $email)->exists()) {
            $email = $slug . '.' . $idx++ . '@smpn2lemahabang.sch.id';
        }

        $cleanSubj = $subjectLabel !== null
            ? (self::isNonTeachingRoleLabel($subjectLabel)
                ? trim($subjectLabel)
                : self::normalizeSubjectLabel($subjectLabel))
            : null;

        $created = User::query()->create([
            'name' => trim($name),
            'email' => $email,
            'role' => 'guru',
            'default_subject' => $cleanSubj ?: null,
            'is_active' => true,
            'password' => Hash::make('guru123'),
        ]);

        $usersCache->push($created);

        return $created;
    }

    /**
     * Selesaikan penugasan mapel/jabatan dari label CSV/Excel tanpa membuat mata pelajaran duplikat.
     *
     * @return array{subject: ?Subject, role_label: ?string}
     *
     * @throws \InvalidArgumentException
     */
    protected function resolveImportedSubjectAssignment(
        ?string $rawLabel,
        string $codeSeed,
        ?TeacherSubject $holderTs,
        User $user,
        Collection $subjectsCache,
        bool $allowCanonicalSubjectCreation = false
    ): array {
        $trimmed = trim((string) $rawLabel);

        // 1. Jabatan non-KBM (seperti Kep.Sek.) -> jangan pernah buat Mata Pelajaran baru di tabel subjects
        if (self::isNonTeachingRoleLabel($trimmed)) {
            return [
                'subject' => null,
                'role_label' => $trimmed,
            ];
        }

        // 2. Jika kolom Mata Pelajaran kosong pada baris legenda, gunakan relasi existing bila ada
        if ($trimmed === '') {
            if ($holderTs) {
                return [
                    'subject' => $holderTs->subject,
                    'role_label' => $holderTs->role_label,
                ];
            }
            if (! empty($user->default_subject)) {
                return $this->resolveImportedSubjectAssignment(
                    $user->default_subject,
                    $codeSeed,
                    null,
                    $user,
                    $subjectsCache,
                    $allowCanonicalSubjectCreation
                );
            }

            throw new \InvalidArgumentException(
                "Kolom Mata Pelajaran untuk Kode Guru '{$codeSeed}' ({$user->name}) kosong. Pilih/isi nama mata pelajaran yang terdaftar di Master Mapel & KKTP."
            );
        }

        // 3. Cocokkan ketat terhadap Master Mata Pelajaran yang sudah ada (termasuk alias pintar)
        $matched = self::findBestMatchingSubject($trimmed, $subjectsCache);
        if ($matched) {
            return [
                'subject' => $matched,
                'role_label' => null,
            ];
        }

        // 4. Pada Import Excel/CSV dari admin, dilarang keras membuat baris Subject baru secara otomatis
        if (! $allowCanonicalSubjectCreation) {
            throw new \InvalidArgumentException(
                "Mata pelajaran '{$trimmed}' pada Kode Guru '{$codeSeed}' belum terdaftar di Master Mata Pelajaran & KKTP. Tambahkan atau perbaiki penamaannya di menu Admin -> Master Mapel & KKTP terlebih dahulu."
            );
        }

        // 5. Hanya untuk inisialisasi awal template Wakasek pada database kosong: buat dari katalog kanonik bersih
        $cleanName = self::normalizeSubjectLabel($trimmed) ?: $trimmed;
        $baseCode = null;
        foreach (self::CANONICAL_SUBJECT_CATALOG as $canon) {
            if (self::isSubjectLabelMatch($trimmed, $canon['code'], $canon['name'])) {
                $cleanName = $canon['name'];
                $baseCode = $canon['code'];
                break;
            }
        }

        if ($baseCode === null) {
            $baseCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $cleanName));
            $baseCode = substr($baseCode ?: ('MP' . $codeSeed), 0, 12);
        }

        $subjCode = $baseCode;
        $i = 1;
        while (Subject::query()->where('code', $subjCode)->exists()) {
            $subjCode = substr($baseCode, 0, 10) . $i++;
        }

        $created = Subject::query()->create([
            'code' => $subjCode,
            'name' => $cleanName,
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $subjectsCache->push($created);

        return [
            'subject' => $created,
            'role_label' => null,
        ];
    }

    public static function formatClassDisplayName(string $normalizedClassCode): string
    {
        if (preg_match('/^(\d+)([A-Z0-9]+)$/', $normalizedClassCode, $m)) {
            $gradeRoman = match ($m[1]) {
                '7' => 'VII',
                '8' => 'VIII',
                '9' => 'IX',
                default => $m[1],
            };

            return $gradeRoman . '-' . $m[2];
        }

        return $normalizedClassCode;
    }

    /**
     * Deteksi jadwal bertabrakan (bentrok: guru sama di jam & hari yang sama pada kelas berbeda)
     * Mengembalikan daftar konflik dan otomatis mengupdate kolom `is_conflict` di database.
     */
    public function detectConflicts(AcademicYear $academicYear): array
    {
        $items = SchoolScheduleItem::query()
            ->where('academic_year_id', $academicYear->id)
            ->get();

        // Reset semua status bentrok terlebih dahulu
        SchoolScheduleItem::query()
            ->where('academic_year_id', $academicYear->id)
            ->update([
                'is_conflict' => false,
                'conflict_notes' => null,
            ]);

        $conflicts = [];
        $conflictedItemIds = [];

        // Kelompokkan per hari, shift, dan waktu/jam
        $grouped = $items->groupBy(fn ($item) => "{$item->day_of_week}_{$item->shift}_{$item->time_range}");

        foreach ($grouped as $key => $slotItems) {
            // Kelompokkan per kode guru ternormalisasi
            $teacherGroups = $slotItems->groupBy(fn ($item) => self::normalizeTeachingCode((string) $item->teacher_code));

            foreach ($teacherGroups as $code => $clashingItems) {
                if ($code === '' || $clashingItems->first()->isCommonActivity()) {
                    continue;
                }

                if ($clashingItems->count() > 1) {
                    $clashingClasses = $clashingItems->pluck('class_name')->values()->all();
                    $dayName = self::DAYS_MAP[$clashingItems->first()->day_of_week] ?? 'Hari ' . $clashingItems->first()->day_of_week;
                    $time = $clashingItems->first()->time_range;
                    $period = $clashingItems->first()->period_order;
                    $shift = ucfirst($clashingItems->first()->shift);

                    $note = "⚠️ BENTROK: Kode {$code} ({$clashingItems->first()->teacher_name}) dijadwalkan bersamaan di kelas " . implode(' & ', $clashingClasses);

                    foreach ($clashingItems as $item) {
                        $item->is_conflict = true;
                        $item->conflict_notes = $note;
                        $item->save();
                        $conflictedItemIds[] = $item->id;
                    }

                    $conflicts[] = [
                        'day_of_week' => $clashingItems->first()->day_of_week,
                        'day_name' => $dayName,
                        'shift' => $shift,
                        'period_order' => $period,
                        'time_range' => $time,
                        'teacher_code' => $code,
                        'teacher_name' => $clashingItems->first()->teacher_name ?: "Guru {$code}",
                        'subject_name' => $clashingItems->first()->subject_name ?: '-',
                        'clashing_classes' => $clashingClasses,
                        'item_ids' => $clashingItems->pluck('id')->all(),
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Wizard "Buat Jadwal Baru dari Awal" (Inisialisasi draf matriks kosong atau template)
     */
    public function initializeSchedule(AcademicYear $academicYear, array $options): array
    {
        $shiftMode = $options['shift_mode'] ?? 'double'; // 'double' (Pagi & Siang) atau 'single' (Reguler Pagi)
        $daysCount = (int) ($options['days_count'] ?? 6); // 5 atau 6 hari
        $sourceMode = $options['source_mode'] ?? 'empty'; // 'empty' atau 'wakasek_template'

        if ($sourceMode === 'wakasek_template') {
            return $this->importFromWakasekCsv($academicYear);
        }

        // Hapus draf jadwal lama untuk tahun ajaran ini
        SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

        // 1. Tentukan rombel terkunci dari Master Rombel
        $schoolClassesList = SchoolClass::query()->where('academic_year_id', $academicYear->id)->get();
        if ($schoolClassesList->isNotEmpty()) {
            if ($shiftMode === 'double') {
                $classesSiang = $schoolClassesList->filter(fn ($sc) => $sc->grade_level === 'VIII' || str_starts_with(self::normalizeClassName($sc->name), '8'))
                    ->map(fn ($sc) => self::normalizeClassName($sc->name))->values()->all();
                $classesPagi = $schoolClassesList->filter(fn ($sc) => ! in_array(self::normalizeClassName($sc->name), $classesSiang))
                    ->map(fn ($sc) => self::normalizeClassName($sc->name))->values()->all();
            } else {
                $classesPagi = $schoolClassesList->map(fn ($sc) => self::normalizeClassName($sc->name))->values()->all();
                $classesSiang = [];
            }
        } else {
            $classesPagi = ['7A', '7B', '7C', '7D', '7E', '7F', '7G', '9A', '9B', '9C', '9D', '9E', '9F', '9G'];
            $classesSiang = ($shiftMode === 'double') ? ['8A', '8B', '8C', '8D', '8E', '8F', '8G'] : [];
        }

        // 2. Sinkronkan item jadwal dengan Data Master Guru (users + teacher_subjects)
        $this->syncScheduleTeachersFromMaster($academicYear);

        // Standar slot jam per hari
        $timeSlotsPagi = [
            1 => [ // Senin
                ['jam' => 0, 'waktu' => '07.00 - 07.40', 'activity' => 'UPACARA BENDERA'],
                ['jam' => 1, 'waktu' => '07.40 - 08.10', 'activity' => null],
                ['jam' => 2, 'waktu' => '08.10 - 08.40', 'activity' => null],
                ['jam' => 3, 'waktu' => '08.40 - 09.10', 'activity' => null],
                ['jam' => 4, 'waktu' => '09.10 - 09.40', 'activity' => null],
                ['jam' => 0, 'waktu' => '09.40 - 10.10', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '10.10 - 10.40', 'activity' => null],
                ['jam' => 6, 'waktu' => '10.40 - 11.10', 'activity' => null],
                ['jam' => 7, 'waktu' => '11.10 - 11.40', 'activity' => null],
            ],
            2 => [ // Selasa
                ['jam' => 0, 'waktu' => '07.00 - 07.30', 'activity' => 'SHOLAT DHUHA'],
                ['jam' => 1, 'waktu' => '07.30 - 08.00', 'activity' => null],
                ['jam' => 2, 'waktu' => '08.00 - 08.30', 'activity' => null],
                ['jam' => 3, 'waktu' => '08.30 - 09.00', 'activity' => null],
                ['jam' => 4, 'waktu' => '09.00 - 09.30', 'activity' => null],
                ['jam' => 0, 'waktu' => '09.30 - 09.55', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '09.55 - 10.25', 'activity' => null],
                ['jam' => 6, 'waktu' => '10.25 - 10.55', 'activity' => null],
                ['jam' => 7, 'waktu' => '10.55 - 11.25', 'activity' => null],
                ['jam' => 8, 'waktu' => '11.25 - 11.55', 'activity' => null],
            ],
            5 => [ // Jumat
                ['jam' => 0, 'waktu' => '07.00 - 08.00', 'activity' => 'KEGIATAN KEAGAMAAN'],
                ['jam' => 1, 'waktu' => '08.00 - 08.30', 'activity' => null],
                ['jam' => 2, 'waktu' => '08.30 - 09.00', 'activity' => null],
                ['jam' => 0, 'waktu' => '09.00 - 09.25', 'activity' => 'ISTIRAHAT'],
                ['jam' => 3, 'waktu' => '09.25 - 10.05', 'activity' => null],
                ['jam' => 4, 'waktu' => '10.05 - 10.35', 'activity' => null],
            ],
            6 => [ // Sabtu
                ['jam' => 0, 'waktu' => '07.00 - 07.40', 'activity' => 'SABTU BERSIH / OR'],
                ['jam' => 1, 'waktu' => '07.40 - 08.10', 'activity' => null],
                ['jam' => 2, 'waktu' => '08.10 - 08.40', 'activity' => null],
                ['jam' => 3, 'waktu' => '08.40 - 09.10', 'activity' => null],
                ['jam' => 4, 'waktu' => '09.10 - 09.40', 'activity' => null],
                ['jam' => 0, 'waktu' => '09.40 - 10.00', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '10.00 - 10.30', 'activity' => null],
                ['jam' => 6, 'waktu' => '10.30 - 11.00', 'activity' => null],
                ['jam' => 7, 'waktu' => '11.00 - 11.30', 'activity' => null],
            ],
        ];
        $timeSlotsPagi[3] = $timeSlotsPagi[2]; // Rabu sama dengan Selasa
        $timeSlotsPagi[4] = $timeSlotsPagi[2]; // Kamis sama dengan Selasa

        $timeSlotsSiang = [
            1 => [ // Senin
                ['jam' => 1, 'waktu' => '12.15 - 12.45', 'activity' => null],
                ['jam' => 2, 'waktu' => '12.45 - 13.15', 'activity' => null],
                ['jam' => 3, 'waktu' => '13.15 - 13.45', 'activity' => null],
                ['jam' => 4, 'waktu' => '13.45 - 14.15', 'activity' => null],
                ['jam' => 0, 'waktu' => '14.15 - 14.45', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '14.45 - 15.15', 'activity' => null],
                ['jam' => 6, 'waktu' => '15.15 - 15.45', 'activity' => null],
                ['jam' => 7, 'waktu' => '15.45 - 16.15', 'activity' => null],
            ],
            2 => [ // Selasa-Kamis
                ['jam' => 1, 'waktu' => '12.00 - 12.30', 'activity' => null],
                ['jam' => 2, 'waktu' => '12.30 - 13.00', 'activity' => null],
                ['jam' => 3, 'waktu' => '13.00 - 13.30', 'activity' => null],
                ['jam' => 4, 'waktu' => '13.30 - 14.00', 'activity' => null],
                ['jam' => 0, 'waktu' => '14.00 - 14.25', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '14.25 - 14.55', 'activity' => null],
                ['jam' => 6, 'waktu' => '14.55 - 15.25', 'activity' => null],
                ['jam' => 7, 'waktu' => '15.25 - 15.55', 'activity' => null],
                ['jam' => 8, 'waktu' => '15.55 - 16.25', 'activity' => null],
            ],
            5 => [ // Jumat
                ['jam' => 1, 'waktu' => '13.00 - 13.30', 'activity' => null],
                ['jam' => 2, 'waktu' => '13.30 - 14.00', 'activity' => null],
                ['jam' => 0, 'waktu' => '14.00 - 14.25', 'activity' => 'ISTIRAHAT'],
                ['jam' => 3, 'waktu' => '14.25 - 14.55', 'activity' => null],
                ['jam' => 4, 'waktu' => '14.55 - 15.20', 'activity' => null],
            ],
            6 => [ // Sabtu
                ['jam' => 1, 'waktu' => '12.15 - 12.45', 'activity' => null],
                ['jam' => 2, 'waktu' => '12.45 - 13.15', 'activity' => null],
                ['jam' => 3, 'waktu' => '13.15 - 13.45', 'activity' => null],
                ['jam' => 4, 'waktu' => '13.45 - 14.15', 'activity' => null],
                ['jam' => 0, 'waktu' => '14.15 - 14.45', 'activity' => 'ISTIRAHAT'],
                ['jam' => 5, 'waktu' => '14.45 - 15.15', 'activity' => null],
                ['jam' => 6, 'waktu' => '15.15 - 15.45', 'activity' => null],
                ['jam' => 7, 'waktu' => '15.45 - 16.15', 'activity' => null],
            ],
        ];
        $timeSlotsSiang[3] = $timeSlotsSiang[2];
        $timeSlotsSiang[4] = $timeSlotsSiang[2];

        $schoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->get()->keyBy(function ($sc) {
            return self::normalizeClassName($sc->name);
        });

        DB::transaction(function () use ($academicYear, $daysCount, $classesPagi, $classesSiang, $timeSlotsPagi, $timeSlotsSiang, $schoolClasses) {
            for ($d = 1; $d <= $daysCount; $d++) {
                // Pagi
                $slotsPagi = $timeSlotsPagi[$d] ?? [];
                foreach ($slotsPagi as $slot) {
                    foreach ($classesPagi as $cls) {
                        SchoolScheduleItem::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'day_of_week' => $d,
                            'shift' => 'pagi',
                            'period_order' => $slot['jam'],
                            'time_range' => $slot['waktu'],
                            'class_name' => $cls,
                            'school_class_id' => $schoolClasses->get($cls)?->id,
                            'teacher_code' => $slot['activity'],
                            'teacher_name' => $slot['activity'],
                        ]);
                    }
                }

                // Siang
                if (! empty($classesSiang)) {
                    $slotsSiang = $timeSlotsSiang[$d] ?? [];
                    foreach ($slotsSiang as $slot) {
                        foreach ($classesSiang as $cls) {
                            SchoolScheduleItem::query()->create([
                                'academic_year_id' => $academicYear->id,
                                'day_of_week' => $d,
                                'shift' => 'siang',
                                'period_order' => $slot['jam'],
                                'time_range' => $slot['waktu'],
                                'class_name' => $cls,
                                'school_class_id' => $schoolClasses->get($cls)?->id,
                                'teacher_code' => $slot['activity'],
                                'teacher_name' => $slot['activity'],
                            ]);
                        }
                    }
                }
            }
        });

        return [
            'status' => 'success',
            'message' => 'Matriks jadwal baru berhasil dibuat dari awal.',
            'items_count' => SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count(),
        ];
    }

    /**
     * Konversi total menit ke format string waktu jam sekolah (misal: 460 -> "07.40")
     */
    public static function formatMinutesToTime(int $minutes): string
    {
        $h = (int) floor($minutes / 60);
        $m = $minutes % 60;
        return sprintf('%02d.%02d', $h, $m);
    }

    /**
     * Konversi string waktu "07:00" atau "07.00" menjadi total menit dari tengah malam
     */
    public static function parseTimeToMinutes(?string $timeStr): int
    {
        if (empty($timeStr)) {
            return 7 * 60; // default 07:00
        }
        $parts = preg_split('/[:.]/', trim($timeStr));
        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Pecah bobot JP suatu mapel menjadi sesi blok jam berurutan
     * (Contoh: 4 JP -> [2, 2]; 5 JP -> [3, 2]; 6 JP -> [3, 3]; 3 JP -> [3])
     */
    public static function splitJpIntoBlocks(int $totalJp): array
    {
        if ($totalJp <= 3) {
            return [$totalJp];
        }
        if ($totalJp === 4) {
            return [2, 2];
        }
        if ($totalJp === 5) {
            return [3, 2];
        }
        if ($totalJp === 6) {
            return [3, 3];
        }
        if ($totalJp === 7) {
            return [4, 3];
        }
        if ($totalJp === 8) {
            return [4, 4];
        }
        return array_merge([3], self::splitJpIntoBlocks($totalJp - 3));
    }

    /**
     * Preset default waktu dan kegiatan non-KBM (Standar SMPN 2 Lemahabang)
     */
    public function getDefaultTimeGridConfig(): array
    {
        return [
            'jp_duration' => 40,
            'start_time' => '07:00',
            'start_time_siang' => '12:15',
            'days_count' => 6,
            'daily_jp' => [
                1 => 7, // Senin (7 JP)
                2 => 8, // Selasa (8 JP)
                3 => 8, // Rabu (8 JP)
                4 => 8, // Kamis (8 JP)
                5 => 5, // Jumat (5 JP)
                6 => 7, // Sabtu (7 JP)
            ],
            'activities' => [
                ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                ['day' => 2, 'name' => 'SHOLAT DHUHA', 'position' => 'before_jp_1', 'duration' => 30],
                ['day' => 3, 'name' => 'SHOLAT DHUHA', 'position' => 'before_jp_1', 'duration' => 30],
                ['day' => 4, 'name' => 'SHOLAT DHUHA', 'position' => 'before_jp_1', 'duration' => 30],
                ['day' => 5, 'name' => 'KEGIATAN KEAGAMAAN', 'position' => 'before_jp_1', 'duration' => 60],
                ['day' => 6, 'name' => 'SABTU BERSIH / OR', 'position' => 'before_jp_1', 'duration' => 40],
                ['day' => 'all', 'name' => 'ISTIRAHAT', 'position' => 'after_jp_4', 'duration' => 30],
            ],
        ];
    }

    /**
     * Kalkulasi matematis pembagian jam pelajaran dan kegiatan non-KBM per hari (Pagi & Siang)
     */
    public function calculateTimeGrid(array $config = []): array
    {
        $defaults = $this->getDefaultTimeGridConfig();
        $jpDuration = (int) ($config['jp_duration'] ?? $defaults['jp_duration']);
        if ($jpDuration < 20 || $jpDuration > 60) {
            $jpDuration = 40;
        }

        $startTimeStr = $config['start_time'] ?? $defaults['start_time'];
        $startTimeSiangStr = $config['start_time_siang'] ?? $defaults['start_time_siang'];
        $daysCount = (int) ($config['days_count'] ?? $defaults['days_count']);
        if ($daysCount < 5 || $daysCount > 6) {
            $daysCount = 6;
        }

        $dailyJp = $config['daily_jp'] ?? $defaults['daily_jp'];
        $activities = $config['activities'] ?? $defaults['activities'];

        $grid = [];
        $dayNames = self::DAYS_MAP;

        for ($d = 1; $d <= $daysCount; $d++) {
            $curMinutesPagi = self::parseTimeToMinutes($startTimeStr);
            $slotsPagi = [];
            $totalJp = (int) ($dailyJp[$d] ?? 8);

            // 1. Kegiatan sebelum JP 1 (Pagi) (misal Upacara, Sholat Dhuha)
            foreach ($activities as $act) {
                $matchDay = ($act['day'] === 'all' || (int) $act['day'] === $d);
                if ($matchDay && ($act['position'] ?? '') === 'before_jp_1') {
                    $startStr = self::formatMinutesToTime($curMinutesPagi);
                    $curMinutesPagi += (int) ($act['duration'] ?? 30);
                    $endStr = self::formatMinutesToTime($curMinutesPagi);
                    $slotsPagi[] = [
                        'period_order' => 0,
                        'time_range' => "{$startStr} - {$endStr}",
                        'activity' => $act['name'],
                        'is_activity' => true,
                    ];
                }
            }

            // 2. Jam Pelajaran Pagi (1 s.d. totalJp)
            for ($jp = 1; $jp <= $totalJp; $jp++) {
                $startStr = self::formatMinutesToTime($curMinutesPagi);
                $curMinutesPagi += $jpDuration;
                $endStr = self::formatMinutesToTime($curMinutesPagi);
                $slotsPagi[] = [
                    'period_order' => $jp,
                    'time_range' => "{$startStr} - {$endStr}",
                    'activity' => null,
                    'is_activity' => false,
                ];

                // Cek kegiatan/istirahat setelah JP ini
                foreach ($activities as $act) {
                    $matchDay = ($act['day'] === 'all' || (int) $act['day'] === $d);
                    if ($matchDay && ($act['position'] ?? '') === "after_jp_{$jp}") {
                        $actStart = self::formatMinutesToTime($curMinutesPagi);
                        $curMinutesPagi += (int) ($act['duration'] ?? 30);
                        $actEnd = self::formatMinutesToTime($curMinutesPagi);
                        $slotsPagi[] = [
                            'period_order' => 0,
                            'time_range' => "{$actStart} - {$actEnd}",
                            'activity' => $act['name'],
                            'is_activity' => true,
                        ];
                    }
                }
            }

            // 3. Jam Pelajaran Siang (1 s.d. totalJp) dihitung dari $startTimeSiangStr
            $curMinutesSiang = self::parseTimeToMinutes($startTimeSiangStr);
            $slotsSiang = [];

            for ($jp = 1; $jp <= $totalJp; $jp++) {
                $startStr = self::formatMinutesToTime($curMinutesSiang);
                $curMinutesSiang += $jpDuration;
                $endStr = self::formatMinutesToTime($curMinutesSiang);
                $slotsSiang[] = [
                    'period_order' => $jp,
                    'time_range' => "{$startStr} - {$endStr}",
                    'activity' => null,
                    'is_activity' => false,
                ];

                // Cek jeda istirahat siang setelah JP ini (misal Istirahat setelah JP 4)
                foreach ($activities as $act) {
                    $matchDay = ($act['day'] === 'all' || (int) $act['day'] === $d);
                    if ($matchDay && ($act['position'] ?? '') === "after_jp_{$jp}") {
                        $actStart = self::formatMinutesToTime($curMinutesSiang);
                        $curMinutesSiang += (int) ($act['duration'] ?? 30);
                        $actEnd = self::formatMinutesToTime($curMinutesSiang);
                        $slotsSiang[] = [
                            'period_order' => 0,
                            'time_range' => "{$actStart} - {$actEnd}",
                            'activity' => $act['name'],
                            'is_activity' => true,
                        ];
                    }
                }
            }

            $grid[$d] = [
                'day_num' => $d,
                'day_name' => $dayNames[$d] ?? "Hari {$d}",
                'total_jp' => $totalJp,
                'slots' => $slotsPagi,
                'slots_siang' => $slotsSiang,
            ];
        }

        return $grid;
    }

    /**
     * Membangun jadwal dari wizard baru (Manual / Auto Generate dengan Algoritma Anti-Bentrok)
     */
    public function buildScheduleFromWizard(AcademicYear $academicYear, array $payload): array
    {
        $timeConfig = $payload['time_config'] ?? [];
        $selectedClasses = $payload['selected_classes'] ?? [];
        $workloads = $payload['workloads'] ?? [];
        $generationMode = $payload['generation_mode'] ?? 'auto'; // 'auto' atau 'manual'
        $shiftMode = $payload['shift_mode'] ?? 'single'; // 'single' atau 'double'
        $customClassesSiang = $payload['classes_siang'] ?? null;
        if (is_string($customClassesSiang)) {
            $customClassesSiang = json_decode($customClassesSiang, true);
        }

        // 1. Ambil data rombel terkunci dari Master Rombel untuk tahun ajaran aktif
        $schoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->get()->keyBy(function ($c) {
            return self::normalizeClassName($c->name);
        });
        $validClassCodes = $schoolClasses->keys()->all();

        if (empty($selectedClasses)) {
            $selectedClasses = $validClassCodes ?: [
                '7A', '7B', '7C', '7D', '7E', '7F', '7G',
                '8A', '8B', '8C', '8D', '8E', '8F', '8G',
                '9A', '9B', '9C', '9D', '9E', '9F', '9G',
            ];
        } else {
            // Kunci: Hanya izinkan rombel yang terdaftar di Master Rombel jika rombel di DB ada
            if (! empty($validClassCodes)) {
                $filtered = array_values(array_intersect($selectedClasses, $validClassCodes));
                if (! empty($filtered)) {
                    $selectedClasses = $filtered;
                }
            }
        }

        // 2. Sinkronkan item jadwal pengajar dengan Data Master Guru (users + teacher_subjects)
        $this->syncScheduleTeachersFromMaster($academicYear);

        // Pastikan timeConfig menyertakan start_time_siang jika ada
        if (! isset($timeConfig['start_time_siang']) && isset($payload['start_time_siang'])) {
            $timeConfig['start_time_siang'] = $payload['start_time_siang'];
        }

        // Kalkulasi time grid
        $timeGrid = $this->calculateTimeGrid($timeConfig);

        // Pisahkan kelas pagi & siang jika mode double shift
        $classesPagi = [];
        $classesSiang = [];
        if ($shiftMode === 'double') {
            if (is_array($customClassesSiang)) {
                $classesSiang = array_values(array_intersect($selectedClasses, $customClassesSiang));
            } else {
                $classesSiang = array_values(array_filter($selectedClasses, fn ($c) => str_starts_with($c, '8')));
            }
            $classesPagi = array_values(array_diff($selectedClasses, $classesSiang));
        } else {
            $classesPagi = $selectedClasses;
            $classesSiang = [];
        }

        $allocatedCount = 0;
        $unassignedCount = 0;

        DB::transaction(function () use (
            $academicYear, $selectedClasses, $classesPagi, $classesSiang, $shiftMode,
            $timeGrid, $workloads, $generationMode, $schoolClasses,
            &$allocatedCount, &$unassignedCount
        ) {
            // 1. Bersihkan draf jadwal lama tahun ajaran ini
            SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

            // 2. Siapkan penampung memori untuk generator
            // scheduleMap[class][day][period_order] = item_data
            $scheduleMap = [];
            // teacherBusy[teacher_code][busy_key] = true
            $teacherBusy = [];

            // 3. Masukkan kegiatan non-KBM ke setiap kelas
            foreach ($selectedClasses as $cls) {
                $isSiang = in_array($cls, $classesSiang, true);
                $shift = $isSiang ? 'siang' : 'pagi';
                $sc = $schoolClasses[$cls] ?? null;

                foreach ($timeGrid as $d => $dayData) {
                    $daySlots = ($isSiang && ! empty($dayData['slots_siang'])) ? $dayData['slots_siang'] : $dayData['slots'];
                    foreach ($daySlots as $slot) {
                        if ($slot['is_activity']) {
                            SchoolScheduleItem::query()->create([
                                'academic_year_id' => $academicYear->id,
                                'day_of_week' => $d,
                                'shift' => $shift,
                                'period_order' => 0,
                                'time_range' => $slot['time_range'],
                                'class_name' => $cls,
                                'school_class_id' => $sc?->id,
                                'teacher_code' => $slot['activity'],
                                'teacher_name' => $slot['activity'],
                                'subject_name' => null,
                                'is_conflict' => false,
                            ]);
                        }
                    }
                }
            }

            // 4. Jika mode Auto, jalankan algoritma penempatan beban mengajar
            if ($generationMode === 'auto' && ! empty($workloads)) {
                // Pecah workload jadi sesi blok jam
                $sessions = [];
                foreach ($workloads as $w) {
                    $cls = $w['class_name'] ?? '';
                    if (! in_array($cls, $selectedClasses, true)) {
                        continue;
                    }
                    $jp = (int) ($w['jp_count'] ?? 0);
                    if ($jp <= 0) {
                        continue;
                    }

                    $blocks = self::splitJpIntoBlocks($jp);
                    foreach ($blocks as $blockSize) {
                        $sessions[] = array_merge($w, ['block_size' => $blockSize]);
                    }
                }

                // Urutkan Most Constrained First (blok terbesar ditempatkan lebih dahulu)
                usort($sessions, fn ($a, $b) => $b['block_size'] <=> $a['block_size']);

                // Array hari yang tersedia
                $availableDays = array_keys($timeGrid);

                foreach ($sessions as $session) {
                    $c = $session['class_name'];
                    $isClassSiang = in_array($c, $classesSiang, true);
                    $classShift = $isClassSiang ? 'siang' : 'pagi';

                    $tCode = (string) ($session['teacher_code'] ?? '');
                    if ($tCode === '' && ! empty($session['teacher_name'])) {
                        $tCode = substr(strtoupper(preg_replace('/[^a-zA-Z]/', '', $session['teacher_name'])), 0, 4);
                    }
                    $bSize = $session['block_size'];
                    $placed = false;

                    // Acak kandidat hari namun prioritaskan hari yang belum ada mapel ini
                    $dayCandidates = $availableDays;
                    shuffle($dayCandidates);

                    usort($dayCandidates, function ($d1, $d2) use ($scheduleMap, $c, $session) {
                        $hasD1 = false;
                        $hasD2 = false;
                        foreach (($scheduleMap[$c][$d1] ?? []) as $item) {
                            if (($item['subject_name'] ?? '') === ($session['subject_name'] ?? '')) {
                                $hasD1 = true;
                            }
                        }
                        foreach (($scheduleMap[$c][$d2] ?? []) as $item) {
                            if (($item['subject_name'] ?? '') === ($session['subject_name'] ?? '')) {
                                $hasD2 = true;
                            }
                        }
                        return $hasD1 <=> $hasD2;
                    });

                    foreach ($dayCandidates as $d) {
                        $maxJp = (int) ($timeGrid[$d]['total_jp'] ?? 8);

                        for ($pStart = 1; $pStart <= ($maxJp - $bSize + 1); $pStart++) {
                            $canPlace = true;
                            for ($offset = 0; $offset < $bSize; $offset++) {
                                $p = $pStart + $offset;
                                // Cek apakah sel kelas sudah terisi
                                if (isset($scheduleMap[$c][$d][$p])) {
                                    $canPlace = false;
                                    break;
                                }
                                // Cek apakah guru sibuk di kelas lain pada jam dan shift yang sama
                                $busyKey = ($shiftMode === 'double') ? "{$classShift}_{$d}_{$p}" : "{$d}_{$p}";
                                if ($tCode !== '' && isset($teacherBusy[$tCode][$busyKey])) {
                                    $canPlace = false;
                                    break;
                                }
                            }

                            if ($canPlace) {
                                for ($offset = 0; $offset < $bSize; $offset++) {
                                    $p = $pStart + $offset;
                                    $scheduleMap[$c][$d][$p] = [
                                        'teacher_code' => $tCode,
                                        'teacher_name' => $session['teacher_name'] ?? null,
                                        'subject_name' => $session['subject_name'] ?? null,
                                        'user_id' => $session['user_id'] ?? null,
                                        'subject_id' => $session['subject_id'] ?? null,
                                    ];
                                    if ($tCode !== '') {
                                        $busyKey = ($shiftMode === 'double') ? "{$classShift}_{$d}_{$p}" : "{$d}_{$p}";
                                        $teacherBusy[$tCode][$busyKey] = true;
                                    }
                                }
                                $placed = true;
                                $allocatedCount += $bSize;
                                break 2;
                            }
                        }
                    }

                    if (! $placed) {
                        $unassignedCount += $bSize;
                    }
                }
            }

            // 5. Simpan seluruh slot KBM (1 s.d. totalJp) ke database
            foreach ($selectedClasses as $cls) {
                $sc = $schoolClasses[$cls] ?? null;
                $isSiang = in_array($cls, $classesSiang, true);
                $shift = $isSiang ? 'siang' : 'pagi';

                foreach ($timeGrid as $d => $dayData) {
                    $daySlots = ($isSiang && ! empty($dayData['slots_siang'])) ? $dayData['slots_siang'] : $dayData['slots'];
                    $kbmSlots = array_filter($daySlots, fn ($s) => ! $s['is_activity']);
                    foreach ($kbmSlots as $slot) {
                        $jp = $slot['period_order'];
                        $assigned = $scheduleMap[$cls][$d][$jp] ?? null;

                        SchoolScheduleItem::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'day_of_week' => $d,
                            'shift' => $shift,
                            'period_order' => $jp,
                            'time_range' => $slot['time_range'],
                            'class_name' => $cls,
                            'school_class_id' => $sc?->id,
                            'teacher_code' => $assigned['teacher_code'] ?? null,
                            'teacher_name' => $assigned['teacher_name'] ?? null,
                            'subject_name' => $assigned['subject_name'] ?? null,
                            'user_id' => $assigned['user_id'] ?? null,
                            'subject_id' => $assigned['subject_id'] ?? null,
                            'is_conflict' => false,
                        ]);
                    }
                }
            }
        });

        // Deteksi status bentrok final
        $conflicts = $this->detectConflicts($academicYear);

        return [
            'status' => 'success',
            'generation_mode' => $generationMode,
            'total_items' => SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count(),
            'allocated_jp' => $allocatedCount,
            'unassigned_jp' => $unassignedCount,
            'conflicts_count' => count($conflicts),
        ];
    }

    /**
     * Tukar jadwal antar 2 sel (Swap Slot) untuk mempermudah Wakasek mengatasi bentrok
     */
    public function swapSlots(int $itemId1, int $itemId2): bool
    {
        $item1 = SchoolScheduleItem::query()->findOrFail($itemId1);
        $item2 = SchoolScheduleItem::query()->findOrFail($itemId2);

        if ($item1->class_name !== $item2->class_name) {
            throw new \InvalidArgumentException('Penukaran slot hanya diperbolehkan pada kelas yang sama.');
        }

        DB::transaction(function () use ($item1, $item2) {
            $tmpCode = $item1->teacher_code;
            $tmpName = $item1->teacher_name;
            $tmpSubject = $item1->subject_name;
            $tmpUserId = $item1->user_id;
            $tmpSubId = $item1->subject_id;

            $item1->update([
                'teacher_code' => $item2->teacher_code,
                'teacher_name' => $item2->teacher_name,
                'subject_name' => $item2->subject_name,
                'user_id' => $item2->user_id,
                'subject_id' => $item2->subject_id,
            ]);

            $item2->update([
                'teacher_code' => $tmpCode,
                'teacher_name' => $tmpName,
                'subject_name' => $tmpSubject,
                'user_id' => $tmpUserId,
                'subject_id' => $tmpSubId,
            ]);
        });

        $this->detectConflicts($item1->academicYear);

        return true;
    }

    /**
     * Update isi satu sel jadwal dan otomatis sinkronkan metadata guru/mapel dari Master Guru.
     */
    public function updateSlot(int $itemId, array $data): SchoolScheduleItem
    {
        $item = SchoolScheduleItem::query()->findOrFail($itemId);

        $rawCode = trim((string) ($data['teacher_code'] ?? ''));
        $isActivity = $rawCode !== '' && SchoolScheduleItem::isCommonActivityLabel($rawCode);
        $teacherCode = $isActivity ? $rawCode : self::normalizeTeachingCode($rawCode);
        $teacherName = $data['teacher_name'] ?? null;
        $subjectName = $data['subject_name'] ?? null;
        $userId = $data['user_id'] ?? null;
        $subjectId = $item->subject_id;

        if ($teacherCode !== '' && ! $isActivity) {
            $ts = TeacherSubject::query()
                ->with(['user', 'subject'])
                ->where('teaching_code_normalized', $teacherCode)
                ->first();

            if ($ts && $ts->user) {
                $teacherName = $ts->user->name;
                $subjectName = $ts->subject?->name ?? ($ts->role_label ?: $ts->user->default_subject);
                $userId = $ts->user_id;
                $subjectId = $ts->subject_id;
            }
        } elseif ($isActivity) {
            $teacherName = $teacherCode;
            $subjectName = null;
            $userId = null;
            $subjectId = null;
        } else {
            $teacherName = null;
            $subjectName = null;
            $userId = null;
            $subjectId = null;
        }

        $item->update([
            'teacher_code' => $teacherCode !== '' ? $teacherCode : null,
            'teacher_name' => $teacherName,
            'subject_name' => $subjectName,
            'user_id' => $userId,
            'subject_id' => $subjectId,
        ]);

        $this->detectConflicts($item->academicYear);

        return $item->fresh();
    }

    /**
     * Sinkronkan seluruh jadwal dari Master Jadwal ke TeachingClass guru
     */
    public function syncToTeachingClasses(AcademicYear $academicYear): array
    {
        $items = SchoolScheduleItem::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereNotNull('teacher_code')
            ->get()
            ->filter(fn ($item) => ! $item->isCommonActivity());

        $teachers = $this->getMasterTeacherDirectory($academicYear)->keyBy('code');

        $users = User::query()->where('is_active', true)->get();
        $schoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->get();
        $subjects = Subject::query()->where('is_active', true)->get();

        $syncedCount = 0;
        $createdCount = 0;

        // Kelompokkan per (kode guru ternormalisasi, rombel)
        $grouped = $items->groupBy(fn ($i) => self::normalizeTeachingCode($i->teacher_code) . "_{$i->class_name}");

        DB::transaction(function () use ($academicYear, $grouped, $teachers, $users, $schoolClasses, $subjects, &$syncedCount, &$createdCount) {
            foreach ($grouped as $key => $classItems) {
                $first = $classItems->first();
                $normCode = self::normalizeTeachingCode($first->teacher_code);
                $tRecord = $teachers->get($normCode);

                // Cari User yang cocok langsung dari item atau Master Guru
                $user = null;
                if ($first->user_id) {
                    $user = $users->find($first->user_id);
                } elseif ($tRecord?->user_id) {
                    $user = $users->find($tRecord->user_id);
                } elseif ($tRecord) {
                    $user = $users->first(fn ($u) => self::isNameMatch($tRecord->name, $u->name));
                } elseif (! empty($first->teacher_name)) {
                    $user = $users->first(fn ($u) => self::isNameMatch($first->teacher_name, $u->name));
                }

                if (! $user) {
                    continue;
                }

                // Cari SchoolClass
                $clsShort = $first->class_name;
                $gradeNum = substr($clsShort, 0, 1);
                $gradeRoman = match ($gradeNum) {
                    '7' => 'VII',
                    '8' => 'VIII',
                    '9' => 'IX',
                    default => 'VII',
                };
                $formattedClassName = $gradeRoman . '-' . substr($clsShort, 1);

                $sc = $schoolClasses->first(function ($s) use ($formattedClassName, $clsShort) {
                    return $s->name === $formattedClassName || str_replace('-', '', $s->name) === $clsShort;
                });

                // Cari Subject
                $subj = null;
                $subjectText = $first->subject_name ?: ($tRecord?->subject ?: '');
                if ($subjectText !== '') {
                    $subj = $subjects->first(function ($s) use ($subjectText) {
                        return self::isSubjectMatch($subjectText, $s);
                    });
                }
                if (! $subj && ! empty($user->default_subject)) {
                    $subj = $subjects->first(function ($s) use ($user) {
                        return self::isSubjectMatch($user->default_subject, $s);
                    });
                }
                if (! $subj && $user->relationLoaded('teacherSubjects') && $user->teacherSubjects->isNotEmpty()) {
                    $subj = $user->teacherSubjects->first()?->subject;
                }

                // Jangan izinkan fallback buta ke $subjects->first() agar tidak menciptakan kelas dummy Matematika
                if (! $subj) {
                    continue;
                }

                // Hari mengajar unik
                $days = $classItems->pluck('day_of_week')->unique()->sort()->values()->all();

                // Format info jadwal: e.g. "Senin (Jam 1-2 • 07.40 - 08.40) & Kamis (Jam 7-8 • 10.55 - 11.55)"
                $daysGrouped = $classItems->groupBy('day_of_week');
                $scheduleParts = [];
                foreach ($daysGrouped as $dNum => $dItems) {
                    $dName = self::DAYS_MAP[$dNum] ?? 'Hari ' . $dNum;
                    $periods = $dItems->pluck('period_order')->filter(fn ($p) => $p > 0)->sort()->values();
                    $periodStr = $periods->count() > 1
                        ? ('Jam ' . $periods->first() . '-' . $periods->last())
                        : ($periods->count() === 1 ? ('Jam ' . $periods->first()) : '');

                    $times = $dItems->pluck('time_range')->filter()->values();
                    $timeStr = $times->isNotEmpty() ? $times->first() : '';
                    if ($times->count() > 1) {
                        $firstStart = explode('-', $times->first())[0] ?? '';
                        $lastEnd = explode('-', $times->last())[1] ?? '';
                        if ($firstStart && $lastEnd) {
                            $timeStr = trim($firstStart) . ' - ' . trim($lastEnd);
                        }
                    }

                    $scheduleParts[] = trim("{$dName} {$periodStr} ({$timeStr})");
                }
                $scheduleInfoStr = implode(' • ', $scheduleParts);

                // Cari atau buat TeachingClass
                $teachingClassQuery = TeachingClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('teacher_id', $user->id)
                    ->where(function ($q) use ($sc, $formattedClassName) {
                        if ($sc) {
                            $q->where('school_class_id', $sc->id);
                        } else {
                            $q->where('class_name', $formattedClassName);
                        }
                    });

                if ($subj) {
                    $teachingClassQuery->where('subject_id', $subj->id);
                }

                $teachingClass = $teachingClassQuery->first();

                if (! $teachingClass) {
                    TeachingClass::query()->create([
                        'academic_year_id' => $academicYear->id,
                        'teacher_id' => $user->id,
                        'school_class_id' => $sc?->id,
                        'subject_id' => $subj?->id,
                        'grade_level' => $gradeRoman,
                        'class_name' => $formattedClassName,
                        'schedule_info' => $scheduleInfoStr,
                        'teaching_days' => $days,
                        'only_teaching_days' => true,
                        'homeroom_teacher_name' => $sc?->homeroom_teacher_name ?: '-',
                        'homeroom_teacher_nip' => $sc?->homeroom_teacher_nip,
                    ]);
                    $createdCount++;
                } else {
                    $teachingClass->update([
                        'schedule_info' => $scheduleInfoStr,
                        'teaching_days' => $days,
                        'only_teaching_days' => true,
                    ]);
                    $syncedCount++;
                }
            }
        });

        return [
            'synced_classes' => $syncedCount,
            'created_classes' => $createdCount,
            'total_active_classes' => TeachingClass::query()->where('academic_year_id', $academicYear->id)->count(),
        ];
    }

    /**
     * Selesaikan daftar kode mengajar aktif seorang guru dan tautkan item jadwal unlinked yang cocok nama.
     *
     * @return array{codes: list<string>, subject_label: ?string, has_schedule: bool}
     */
    protected function resolveUserScheduleScope(AcademicYear $year, User $user): array
    {
        $teacherSubjects = $user->id
            ? TeacherSubject::query()
                ->with('subject')
                ->where('user_id', $user->id)
                ->get()
            : collect();

        $codes = $teacherSubjects
            ->map(fn (TeacherSubject $ts) => $ts->teaching_code_normalized ?: self::normalizeTeachingCode($ts->teaching_code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subjectNames = $teacherSubjects
            ->map(fn (TeacherSubject $ts) => $ts->subject?->name ?: $ts->role_label)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($user->exists && $user->id) {
            // Tautkan slot jadwal yang belum terhubung user_id bila kode atau namanya cocok
            if (! empty($codes)) {
                SchoolScheduleItem::query()
                    ->where('academic_year_id', $year->id)
                    ->whereNull('user_id')
                    ->whereIn('teacher_code', $codes)
                    ->update(['user_id' => $user->id, 'teacher_name' => $user->name]);
            }

            $unlinkedByName = SchoolScheduleItem::query()
                ->where('academic_year_id', $year->id)
                ->whereNull('user_id')
                ->whereNotNull('teacher_name')
                ->get()
                ->filter(fn (SchoolScheduleItem $item) => ! $item->isCommonActivity() && self::isNameMatch($item->teacher_name, $user->name));

            if ($unlinkedByName->isNotEmpty()) {
                SchoolScheduleItem::query()
                    ->whereIn('id', $unlinkedByName->pluck('id')->all())
                    ->update(['user_id' => $user->id]);

                foreach ($unlinkedByName as $uItem) {
                    $c = self::normalizeTeachingCode($uItem->teacher_code);
                    if ($c !== '' && ! in_array($c, $codes, true)) {
                        $codes[] = $c;
                    }
                    if (! empty($uItem->subject_name) && ! in_array($uItem->subject_name, $subjectNames, true)) {
                        $subjectNames[] = $uItem->subject_name;
                    }
                }
            }
        }

        $hasItems = $user->id
            ? SchoolScheduleItem::query()
                ->where('academic_year_id', $year->id)
                ->where(function ($q) use ($user, $codes) {
                    $q->where('user_id', $user->id);
                    if (! empty($codes)) {
                        $q->orWhereIn('teacher_code', $codes);
                    }
                })
                ->get()
                ->contains(fn (SchoolScheduleItem $item) => ! $item->isCommonActivity())
            : false;

        return [
            'codes' => array_values(array_unique($codes)),
            'subject_label' => ! empty($subjectNames) ? implode(', ', $subjectNames) : ($user->default_subject ?: null),
            'has_schedule' => ! empty($codes) || $hasItems,
        ];
    }

    /**
     * Ambil jadwal pribadi seorang guru (untuk tab "Jadwal Saya" di Jadwal Utama)
     */
    public function getTeacherSchedule(User $user, ?AcademicYear $academicYear = null): array
    {
        $year = $academicYear ?: AcademicYear::active();
        if (! $year) {
            return [];
        }

        $scope = $this->resolveUserScheduleScope($year, $user);
        $codes = $scope['codes'];
        $homeroomSc = $user->homeroomClassForYear($year->id);
        $homeroomLabel = $homeroomSc ? self::normalizeClassName($homeroomSc->name) : '-';
        $piketLabel = $user->piket_days_string ?: '-';

        // Admin murni tanpa kode mengajar maupun jadwal KBM pribadi -> 0 jam
        if ($user->isAdmin() && ! $scope['has_schedule']) {
            $emptyByDay = [];
            for ($d = 1; $d <= 6; $d++) {
                $emptyByDay[$d] = [
                    'day_num' => $d,
                    'day_name' => self::DAYS_MAP[$d] ?? 'Hari ' . $d,
                    'items' => collect(),
                    'count' => 0,
                ];
            }

            return [
                'teacher' => [
                    'code' => '-',
                    'name' => $user->name,
                    'subject' => $user->default_subject ?? 'Administrator Sekolah',
                    'homeroom_class' => $homeroomLabel,
                    'piket_day' => $piketLabel,
                ],
                'by_day' => $emptyByDay,
                'total_hours' => 0,
                'is_admin' => true,
            ];
        }

        $query = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id);

        if ($user->id || ! empty($codes)) {
            $query->where(function ($q) use ($user, $codes) {
                $applied = false;
                if ($user->id) {
                    $q->where('user_id', $user->id);
                    $applied = true;
                }
                if (! empty($codes)) {
                    $applied ? $q->orWhereIn('teacher_code', $codes) : $q->whereIn('teacher_code', $codes);
                }
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $items = $query->get()
            ->filter(fn (SchoolScheduleItem $item) => ! $item->isCommonActivity())
            ->sortBy(fn ($item) => ($item->day_of_week * 10000) + (int) str_replace(':', '', self::extractStartTime($item->time_range)))
            ->values();

        if (empty($codes) && $items->isNotEmpty()) {
            $codes = $items->pluck('teacher_code')->map(fn ($c) => self::normalizeTeachingCode($c))->filter()->unique()->values()->all();
        }

        $byDay = [];
        for ($d = 1; $d <= 6; $d++) {
            $dayItems = $items->where('day_of_week', $d)->sortBy(fn ($item) => self::extractStartTime($item->time_range))->values();
            $byDay[$d] = [
                'day_num' => $d,
                'day_name' => self::DAYS_MAP[$d] ?? 'Hari ' . $d,
                'items' => $dayItems,
                'count' => $dayItems->count(),
            ];
        }

        return [
            'teacher' => [
                'code' => ! empty($codes) ? implode(', ', $codes) : '-',
                'name' => $user->name,
                'subject' => $scope['subject_label'] ?? '-',
                'homeroom_class' => $homeroomLabel,
                'piket_day' => $piketLabel,
            ],
            'by_day' => $byDay,
            'total_hours' => $items->count(),
        ];
    }

    /**
     * Ambil daftar alokasi mengajar unik seorang guru dari Master Jadwal Sekolah untuk tahun ajaran aktif.
     *
     * @return Collection<int, array>
     */
    public function getTeacherClassAllocations(AcademicYear $year, User $user): Collection
    {
        $scope = $this->resolveUserScheduleScope($year, $user);
        $codes = $scope['codes'];

        if (empty($codes) && ! $user->id) {
            return collect();
        }

        $items = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where(function ($q) use ($user, $codes) {
                $applied = false;
                if (! empty($codes)) {
                    $q->whereIn('teacher_code', $codes);
                    $applied = true;
                }
                if ($user->id) {
                    $applied ? $q->orWhere('user_id', $user->id) : $q->where('user_id', $user->id);
                }
            })
            ->get()
            ->filter(fn ($item) => ! $item->isCommonActivity());

        if ($items->isEmpty()) {
            return collect();
        }

        $schoolClasses = SchoolClass::query()
            ->where('academic_year_id', $year->id)
            ->withCount('activeStudents')
            ->get();

        $subjects = Subject::query()->where('is_active', true)->get();

        $existingClasses = TeachingClass::query()
            ->where('academic_year_id', $year->id)
            ->where('teacher_id', $user->id)
            ->get();

        $grouped = $items->groupBy(fn ($i) => $i->class_name . '_' . ($i->subject_name ?: ''));

        $allocations = collect();

        foreach ($grouped as $key => $classItems) {
            $first = $classItems->first();
            $clsShort = $first->class_name;
            $gradeNum = substr($clsShort, 0, 1);
            $gradeRoman = match ($gradeNum) {
                '7' => 'VII',
                '8' => 'VIII',
                '9' => 'IX',
                default => 'VII',
            };
            $formattedClassName = $gradeRoman . '-' . substr($clsShort, 1);

            $sc = $schoolClasses->first(function ($s) use ($formattedClassName, $clsShort) {
                return $s->name === $formattedClassName
                    || str_replace('-', '', $s->name) === $clsShort
                    || str_replace('-', ' ', $s->name) === $formattedClassName;
            });

            $matchedSubject = null;
            if ($first->subject_id) {
                $matchedSubject = $subjects->find($first->subject_id);
            }
            if (! $matchedSubject && $first->subject_name) {
                $matchedSubject = $subjects->first(fn ($s) => self::isSubjectMatch($first->subject_name, $s));
            }
            if (! $matchedSubject && ! empty($scope['subject_label'])) {
                $matchedSubject = $subjects->first(fn ($s) => self::isSubjectMatch($scope['subject_label'], $s));
            }
            if (! $matchedSubject && ! empty($user->default_subject)) {
                $matchedSubject = $subjects->first(fn ($s) => self::isSubjectMatch($user->default_subject, $s));
            }
            if (! $matchedSubject) {
                $user->loadMissing('teacherSubjects.subject');
                $matchedSubject = $user->teacherSubjects->first()?->subject;
            }

            $totalJp = $classItems->count();
            $daysGrouped = $classItems->groupBy('day_of_week');
            $teachingDays = $classItems->pluck('day_of_week')->unique()->sort()->values()->all();

            $scheduleParts = [];
            foreach ($daysGrouped as $dNum => $dItems) {
                $dName = self::DAYS_MAP[$dNum] ?? 'Hari ' . $dNum;
                $periods = $dItems->pluck('period_order')->filter(fn ($p) => $p > 0)->sort()->values();
                $periodStr = $periods->count() > 1
                    ? ('Jam ' . $periods->first() . '-' . $periods->last())
                    : ($periods->count() === 1 ? ('Jam ' . $periods->first()) : '');

                $times = $dItems->pluck('time_range')->filter()->values();
                $timeStr = $times->isNotEmpty() ? $times->first() : '';
                if ($times->count() > 1) {
                    $firstStart = explode('-', $times->first())[0] ?? '';
                    $lastEnd = explode('-', $times->last())[1] ?? '';
                    if ($firstStart && $lastEnd) {
                        $timeStr = trim($firstStart) . ' - ' . trim($lastEnd);
                    }
                }

                $scheduleParts[] = trim("{$dName} {$periodStr} ({$timeStr})");
            }
            $scheduleInfoStr = implode(' • ', $scheduleParts);

            // Cek apakah kelas ini sudah ada di TeachingClass
            $existing = $existingClasses->first(function ($ec) use ($sc, $formattedClassName, $clsShort, $matchedSubject) {
                $classMatches = ($sc && (int) $ec->school_class_id === (int) $sc->id)
                    || $ec->class_name === $formattedClassName
                    || $ec->class_name === $clsShort
                    || str_replace(' ', '-', $ec->class_name) === $formattedClassName;

                if (! $classMatches) {
                    return false;
                }

                if ($matchedSubject && (int) $ec->subject_id !== (int) $matchedSubject->id) {
                    return false;
                }

                return true;
            });

            $allocations->push([
                'key' => md5($user->id . '_' . $clsShort . '_' . ($matchedSubject?->id ?? $first->subject_name)),
                'class_code' => $clsShort,
                'class_name' => $sc ? $sc->name : $formattedClassName,
                'grade_level' => $sc ? $sc->grade_level : $gradeRoman,
                'school_class_id' => $sc?->id,
                'school_class_name' => $sc?->name,
                'active_students_count' => $sc?->active_students_count ?? 0,
                'homeroom_teacher_name' => $sc?->homeroom_teacher_name ?: '-',
                'homeroom_teacher_nip' => $sc?->homeroom_teacher_nip ?: '-',
                'subject_id' => $matchedSubject?->id,
                'subject_code' => $matchedSubject?->code,
                'subject_name' => $matchedSubject?->name ?: ($first->subject_name ?: '-'),
                'total_jp' => $totalJp,
                'teaching_days' => $teachingDays,
                'schedule_info' => $scheduleInfoStr,
                'already_created' => (bool) $existing,
                'existing_teaching_class_id' => $existing?->id,
            ]);
        }

        return $allocations->sortBy('class_name')->values();
    }

    /**
     * Sinkronkan / buat seluruh buku kelas mengajar guru yang belum dibuat dari Master Jadwal Sekolah.
     */
    public function syncTeacherClasses(AcademicYear $year, User $user): array
    {
        $allocations = $this->getTeacherClassAllocations($year, $user);
        $createdCount = 0;
        $syncedCount = 0;

        DB::transaction(function () use ($allocations, $year, $user, &$createdCount, &$syncedCount) {
            foreach ($allocations as $alloc) {
                if ($alloc['already_created']) {
                    // Update schedule_info & teaching_days jika ada perubahan
                    TeachingClass::query()
                        ->where('id', $alloc['existing_teaching_class_id'])
                        ->update([
                            'schedule_info' => $alloc['schedule_info'],
                            'teaching_days' => $alloc['teaching_days'],
                            'only_teaching_days' => true,
                        ]);
                    $syncedCount++;
                    continue;
                }

                // Cari SchoolClass atau buat jika belum ada
                $schoolClass = null;
                if (! empty($alloc['school_class_id'])) {
                    $schoolClass = SchoolClass::query()->find($alloc['school_class_id']);
                }

                if (! $schoolClass) {
                    $schoolClass = SchoolClass::query()->firstOrCreate(
                        [
                            'academic_year_id' => $year->id,
                            'name' => $alloc['class_name'],
                        ],
                        [
                            'grade_level' => $alloc['grade_level'],
                            'homeroom_teacher_name' => $alloc['homeroom_teacher_name'] !== '-' ? $alloc['homeroom_teacher_name'] : '-',
                            'homeroom_teacher_nip' => $alloc['homeroom_teacher_nip'] !== '-' ? $alloc['homeroom_teacher_nip'] : null,
                        ]
                    );
                }

                $cls = TeachingClass::query()->create([
                    'teacher_id' => $user->id,
                    'academic_year_id' => $year->id,
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $alloc['subject_id'],
                    'grade_level' => $alloc['grade_level'],
                    'class_name' => $schoolClass->name,
                    'schedule_info' => $alloc['schedule_info'],
                    'teaching_days' => $alloc['teaching_days'],
                    'only_teaching_days' => true,
                    'homeroom_teacher_name' => $schoolClass->homeroom_teacher_name ?: '-',
                    'homeroom_teacher_nip' => $schoolClass->homeroom_teacher_nip,
                    'homeroom_teacher_signature' => $schoolClass->homeroom_teacher_signature,
                ]);

                $cls->ensureDefaultsInitialized();
                $createdCount++;
            }
        });

        return [
            'created' => $createdCount,
            'updated' => $syncedCount,
            'total' => $allocations->count(),
        ];
    }

    /**
     * Ambil daftar Wali Kelas dinamis langsung dari Master Rombel (`school_classes`) & Master Guru (`users`),
     * terurut rapi per tingkat dan nama kelas tanpa daftar hardcoded.
     *
     * @return Collection<int, array{class_code: string, class_label: string, class_roman: string, grade_level: string, teacher_name: string, teacher_nip: ?string, teacher_code: ?string, teacher_id: ?int, school_class_id: int}>
     */
    public function getHomeroomList(AcademicYear $academicYear): Collection
    {
        $schoolClasses = SchoolClass::query()
            ->with(['homeroomTeacher.teacherSubjects'])
            ->where('academic_year_id', $academicYear->id)
            ->get()
            ->sort(function (SchoolClass $a, SchoolClass $b) {
                $codeA = self::normalizeClassName($a->name) ?: $a->name;
                $codeB = self::normalizeClassName($b->name) ?: $b->name;

                return strnatcasecmp($codeA, $codeB);
            })
            ->values();

        return $schoolClasses->map(function (SchoolClass $sc) {
            $classCode = self::normalizeClassName($sc->name) ?: $sc->name;
            $teacher = $sc->homeroomTeacher;
            $rawSnapshot = trim((string) $sc->homeroom_teacher_name);
            $teacherName = $teacher?->name ?: (($rawSnapshot !== '' && $rawSnapshot !== '-') ? $rawSnapshot : 'Belum Ditentukan');
            $teacherCode = $teacher?->teaching_codes_string ?: null;
            if ($teacherCode === '') {
                $teacherCode = null;
            }

            return [
                'class_code' => $classCode,
                'class_label' => 'Kelas ' . $classCode,
                'class_roman' => $sc->name,
                'grade_level' => $sc->grade_level,
                'teacher_name' => $teacherName,
                'teacher_nip' => $teacher?->nip ?: $sc->homeroom_teacher_nip,
                'teacher_code' => $teacherCode,
                'teacher_id' => $teacher?->id,
                'school_class_id' => $sc->id,
            ];
        })->values();
    }

    /**
     * Ambil struktur matriks lengkap seluruh sekolah untuk render tabel
     */
    public function getMasterMatrix(AcademicYear $academicYear, ?int $dayOfWeek = null, ?array $conflicts = null): array
    {
        // Jika $conflicts belum disediakan, jalankan deteksi bentrok agar status is_conflict di DB selalu segar
        if ($conflicts === null) {
            $conflicts = $this->detectConflicts($academicYear);
        }

        // Peta catatan bentrok untuk in-memory injection jaminan 100% langsung tampil di tabel tanpa perlu di-refresh
        $conflictMap = [];
        foreach ($conflicts as $c) {
            $note = "⚠️ BENTROK: Kode {$c['teacher_code']} ({$c['teacher_name']}) dijadwalkan bersamaan di kelas " . implode(' & ', $c['clashing_classes']);
            foreach ($c['item_ids'] ?? [] as $id) {
                $conflictMap[$id] = $note;
            }
        }

        $query = SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id);

        if ($dayOfWeek) {
            $query->where('day_of_week', $dayOfWeek);
        }

        $items = $query->orderBy('day_of_week')->orderBy('period_order')->get();

        // Terapkan status bentrok ke seluruh item secara in-memory untuk memastikan tampilan instan
        foreach ($items as $item) {
            if (isset($conflictMap[$item->id])) {
                $item->is_conflict = true;
                $item->conflict_notes = $conflictMap[$item->id];
            }
        }

        $shiftClassPairs = SchoolScheduleItem::query()
            ->where('academic_year_id', $academicYear->id)
            ->select(['shift', 'class_name'])
            ->distinct()
            ->get();

        $pagiRawClasses = $shiftClassPairs->where('shift', 'pagi')->pluck('class_name')->filter()->values()->all();
        $siangRawClasses = $shiftClassPairs->where('shift', 'siang')->pluck('class_name')->filter()->values()->all();

        if ($shiftClassPairs->isEmpty()) {
            $fallbackClasses = $this->resolveNormalizedTemplateClassCodes($academicYear);
            $siangRawClasses = array_values(array_filter($fallbackClasses, fn (string $c) => str_starts_with($c, '8')));
            $pagiRawClasses = array_values(array_diff($fallbackClasses, $siangRawClasses));
            if (empty($pagiRawClasses)) {
                $pagiRawClasses = ['7A', '7B', '7C', '7D', '7E', '7F', '7G', '9A', '9B', '9C', '9D', '9E', '9F', '9G'];
                $siangRawClasses = ['8A', '8B', '8C', '8D', '8E', '8F', '8G'];
            }
        }

        $shift1Groups = $this->groupNormalizedClasses($pagiRawClasses);
        $shift2Groups = $this->groupNormalizedClasses($siangRawClasses);
        $isTwoShift = count($shift2Groups['flat_classes']) > 0;
        $totalClasses = count(array_unique(array_merge($shift1Groups['flat_classes'], $shift2Groups['flat_classes'])));

        $teachers = $this->getMasterTeacherDirectory($academicYear)->keyBy('code');

        $days = $dayOfWeek ? [$dayOfWeek] : [1, 2, 3, 4, 5, 6];
        $matrixByDay = [];

        foreach ($days as $d) {
            $dayItems = $items->where('day_of_week', $d);

            $pagiSlots = $this->buildMasterMatrixSlots($dayItems->where('shift', 'pagi'));
            $siangSlots = $this->buildMasterMatrixSlots($dayItems->where('shift', 'siang'));

            $matrixByDay[$d] = [
                'day_num' => $d,
                'day_name' => self::DAYS_MAP[$d] ?? 'Hari ' . $d,
                'pagi_slots' => $pagiSlots,
                'siang_slots' => $siangSlots,
                'pagi_time_range' => $this->summarizeShiftTimeSpan($pagiSlots, '07.00 – 13.55 WIB'),
                'siang_time_range' => $this->summarizeShiftTimeSpan($siangSlots, '12.00 – 16.25 WIB'),
                'conflicts_count' => $dayItems->where('is_conflict', true)->count(),
            ];
        }

        return [
            'matrix_by_day' => $matrixByDay,
            'teachers' => $teachers,
            'total_items' => $items->count(),
            'total_conflicts' => $items->where('is_conflict', true)->count(),
            'is_two_shift' => $isTwoShift,
            'shift_count' => $isTwoShift ? 2 : 1,
            'total_classes' => $totalClasses > 0 ? $totalClasses : 21,
            'shift1_meta' => [
                'key' => 'pagi',
                'title' => $this->formatMatrixShiftTitle($shift1Groups, 'pagi', $isTwoShift),
                'groups' => $shift1Groups['groups'],
                'flat_classes' => $shift1Groups['flat_classes'],
            ],
            'shift2_meta' => [
                'key' => 'siang',
                'title' => $this->formatMatrixShiftTitle($shift2Groups, 'siang', $isTwoShift),
                'groups' => $shift2Groups['groups'],
                'flat_classes' => $shift2Groups['flat_classes'],
            ],
        ];
    }

    /**
     * Kelompokkan item satu hari per slot waktu menjadi baris matriks yang terurut kronologis.
     */
    protected function buildMasterMatrixSlots(Collection $shiftItems): Collection
    {
        return $shiftItems->groupBy('time_range')->map(function (Collection $slotItems) {
            $first = $slotItems->first();
            $byClass = $slotItems->keyBy('class_name');
            $hasActivity = $slotItems->contains(fn ($i) => $i->isCommonActivity());
            $hasRegularTeacher = $slotItems->contains(
                fn ($i) => ! $i->isCommonActivity() && trim((string) $i->teacher_code) !== ''
            );
            $isActivity = $hasActivity && ! $hasRegularTeacher;

            $activityName = null;
            if ($isActivity) {
                $activityNames = $slotItems
                    ->filter(fn ($i) => $i->isCommonActivity())
                    ->pluck('teacher_code')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $activityName = ! empty($activityNames) ? implode(' / ', $activityNames) : $first->teacher_code;
            }

            return [
                'period_order' => $first->period_order,
                'time_range' => $first->time_range,
                'start_time' => self::extractStartTime($first->time_range),
                'is_activity' => $isActivity,
                'activity_name' => $activityName,
                'classes' => $byClass,
            ];
        })->sortBy([
            ['start_time', 'asc'],
            ['period_order', 'asc'],
        ])->values();
    }

    /**
     * Hitung rentang jam belajar aktual pada satu shift (mis. "07.00 – 13.55 WIB").
     */
    protected function summarizeShiftTimeSpan(Collection $slots, string $fallback = '-'): string
    {
        $starts = $slots
            ->map(fn (array $s) => self::extractStartTime($s['time_range'] ?? ''))
            ->filter(fn (string $t) => $t !== '' && $t !== '99:99')
            ->sort()
            ->values();
        $ends = $slots
            ->map(fn (array $s) => self::extractEndTime($s['time_range'] ?? ''))
            ->filter(fn (string $t) => $t !== '')
            ->sort()
            ->values();

        if ($starts->isEmpty() || $ends->isEmpty()) {
            return $fallback;
        }

        return str_replace(':', '.', $starts->first()) . ' – ' . str_replace(':', '.', $ends->last()) . ' WIB';
    }

    /**
     * Susun judul blok shift matriks secara dinamis berdasarkan daftar rombel aktual.
     */
    protected function formatMatrixShiftTitle(array $groupInfo, string $shiftKey, bool $isTwoShift): string
    {
        $parts = [];
        foreach ($groupInfo['groups'] ?? [] as $grp) {
            $classes = $grp['classes'] ?? [];
            if (empty($classes)) {
                continue;
            }
            $firstCode = $classes[0]['code'];
            $lastCode = $classes[count($classes) - 1]['code'];
            $range = $firstCode === $lastCode ? $firstCode : "{$firstCode}–{$lastCode}";
            $parts[] = ucwords(strtolower($grp['grade_label'])) . " ({$range})";
        }

        $classDesc = match (count($parts)) {
            0 => 'Semua Rombel',
            1 => $parts[0],
            2 => implode(' & ', $parts),
            default => implode(', ', $parts),
        };

        if (! $isTwoShift && $shiftKey === 'pagi') {
            return 'Jadwal 1 Shift: ' . $classDesc;
        }

        return ($shiftKey === 'siang' ? 'Shift Siang: ' : 'Shift Pagi: ') . $classDesc;
    }

    /**
     * Dapatkan saran jam pelajaran untuk suatu kelas & tanggal berdasarkan Master Jadwal Sekolah
     */
    public function getLessonHoursForClassDate(TeachingClass $teachingClass, string $date, ?User $user = null): array
    {
        try {
            $carbon = Carbon::parse($date);
        } catch (\Throwable $e) {
            $carbon = now();
        }

        $dayOfWeek = (int) $carbon->dayOfWeekIso; // 1 = Senin, ..., 6 = Sabtu, 7 = Minggu
        $dayName = self::DAYS_MAP[$dayOfWeek] ?? $carbon->locale('id')->isoFormat('dddd');

        if ($dayOfWeek > 6) {
            return [
                'found' => false,
                'lesson_hours' => null,
                'time_range' => null,
                'day_num' => $dayOfWeek,
                'day_name' => $dayName,
                'label' => 'Hari Libur (Minggu)',
                'options' => [],
                'source' => null,
            ];
        }

        $teacherUser = $user ?: ($teachingClass->teacher ?: auth()->user());
        $yearId = $teachingClass->academic_year_id ?: AcademicYear::active()?->id;

        $normalizedClass = self::normalizeClassName($teachingClass->class_name);
        $schoolClassId = $teachingClass->school_class_id;

        // 1. Ambil daftar kode mengajar aktif langsung dari Master Guru (teacher_subjects)
        $teacherCodes = $teacherUser ? $teacherUser->teaching_codes_list : [];

        // 2. Query item jadwal dari school_schedule_items
        $items = collect();
        if ($yearId) {
            $query = SchoolScheduleItem::query()
                ->where('academic_year_id', $yearId)
                ->where('day_of_week', $dayOfWeek)
                ->where('period_order', '>', 0); // KBM saja, bukan pembiasaan/istirahat

            // Cocokkan kelas
            $query->where(function ($q) use ($schoolClassId, $normalizedClass) {
                if ($schoolClassId) {
                    $q->where('school_class_id', $schoolClassId);
                }
                if ($normalizedClass !== '') {
                    $q->orWhere('class_name', $normalizedClass);
                }
            });

            // Cocokkan guru berdasarkan user_id atau daftar kode mengajar Master Guru
            if ($teacherUser || ! empty($teacherCodes)) {
                $query->where(function ($q) use ($teacherUser, $teacherCodes) {
                    $applied = false;
                    if ($teacherUser) {
                        $q->where('user_id', $teacherUser->id);
                        $applied = true;
                    }
                    if (! empty($teacherCodes)) {
                        $applied ? $q->orWhereIn('teacher_code', $teacherCodes) : $q->whereIn('teacher_code', $teacherCodes);
                    }
                });
            }

            $items = $query->orderBy('period_order')->get();
        }

        // 3. Jika ditemukan di tabel school_schedule_items
        if ($items->isNotEmpty()) {
            $periods = $items->pluck('period_order')->sort()->values();
            $times = $items->pluck('time_range')->filter()->values();

            // Bentuk blok periode berurutan (misal [1, 2] -> "1 - 2", [1, 2, 7, 8] -> ["1 - 2", "7 - 8"])
            $blocks = [];
            $currentBlock = [];
            foreach ($periods as $p) {
                if (empty($currentBlock)) {
                    $currentBlock[] = $p;
                } elseif ($p === end($currentBlock) + 1) {
                    $currentBlock[] = $p;
                } else {
                    $blocks[] = $currentBlock;
                    $currentBlock = [$p];
                }
            }
            if (! empty($currentBlock)) {
                $blocks[] = $currentBlock;
            }

            $blockStrings = array_map(function ($b) {
                return count($b) > 1 ? ($b[0] . ' - ' . end($b)) : (string) $b[0];
            }, $blocks);

            $primaryHours = implode(', ', $blockStrings);

            // Rentang waktu total (mulai jam pertama s.d. selesai jam terakhir)
            $startTime = '';
            $endTime = '';
            if ($times->isNotEmpty()) {
                $firstTime = $times->first();
                $lastTime = $times->last();
                $startTime = trim(explode('-', $firstTime)[0] ?? '');
                $endTime = trim(explode('-', $lastTime)[1] ?? '');
            }
            $timeRangeTotal = ($startTime && $endTime) ? "{$startTime} - {$endTime}" : ($times->first() ?? '');

            // Opsi pilihan jika lebih dari 1 blok
            $options = [];
            if (count($blockStrings) > 1) {
                foreach ($blockStrings as $bStr) {
                    $options[] = $bStr;
                }
                $options[] = $primaryHours; // Pilihan gabungan seluruh jam
            } else {
                $options[] = $primaryHours;
            }

            return [
                'found' => true,
                'lesson_hours' => $primaryHours,
                'time_range' => $timeRangeTotal,
                'day_num' => $dayOfWeek,
                'day_name' => $dayName,
                'label' => "Jam {$primaryHours}" . ($timeRangeTotal ? " ({$timeRangeTotal})" : ''),
                'options' => array_values(array_unique($options)),
                'source' => 'master_schedule',
            ];
        }

        // 4. Fallback: coba ekstrak dari schedule_info pada TeachingClass jika ada
        // Contoh format: "Senin Jam 1-2 (07.40 - 08.40) & Kamis Jam 7-8 (10.55 - 11.55)"
        if (! empty($teachingClass->schedule_info)) {
            $info = $teachingClass->schedule_info;
            if (stripos($info, $dayName) !== false) {
                if (preg_match('/' . preg_quote($dayName, '/') . '\s*(?:Jam\s*)?([0-9\s\-]+)(?:\(([0-9\.\s\-:]+)\))?/i', $info, $match)) {
                    $rawHours = trim($match[1]);
                    $rawHours = preg_replace('/\s*-\s*/', ' - ', $rawHours);
                    $rawTimes = trim($match[2] ?? '');

                    return [
                        'found' => true,
                        'lesson_hours' => $rawHours,
                        'time_range' => $rawTimes ?: null,
                        'day_num' => $dayOfWeek,
                        'day_name' => $dayName,
                        'label' => "Jam {$rawHours}" . ($rawTimes ? " ({$rawTimes})" : ''),
                        'options' => [$rawHours],
                        'source' => 'schedule_info',
                    ];
                }
            }
        }

        // 5. Tidak ditemukan jadwal untuk hari ini pada kelas ini
        return [
            'found' => false,
            'lesson_hours' => null,
            'time_range' => null,
            'day_num' => $dayOfWeek,
            'day_name' => $dayName,
            'label' => "Tidak ada jadwal KBM pada hari {$dayName} di Master Jadwal",
            'options' => [],
            'source' => null,
        ];
    }

    /**
     * Urutkan koleksi direktori kode guru secara natural berdasarkan kode (1, 2, ... 14A, 14B, ... 34).
     */
    public function sortScheduleTeachersByCode(Collection $teachers): Collection
    {
        return $teachers->sort(function (object $a, object $b) {
            return strnatcasecmp(trim((string) ($a->code ?? '')), trim((string) ($b->code ?? '')));
        })->values();
    }

    /**
     * Susun paket data lengkap untuk Cetak Poster, Download PDF, maupun Export Excel
     * yang otomatis mendukung mode 1 Shift maupun 2 Shift berdampingan,
     * bersumber 100% dari Master Guru & Master Rombel tunggal.
     */
    public function getMasterExportData(
        AcademicYear $academicYear,
        ?array $conflicts = null,
        bool $forTemplate = false,
        ?string $templateShiftMode = null
    ): array {
        $matrixData = $this->getMasterMatrix($academicYear, null, $conflicts);
        $matrixByDay = $matrixData['matrix_by_day'] ?? [];

        $shift1 = $this->buildShiftExportData($matrixByDay, 'pagi');
        $shift2 = $this->buildShiftExportData($matrixByDay, 'siang');

        if ($forTemplate) {
            if ($templateShiftMode === 'single') {
                $shift2SlotTotalCurrent = collect($shift2['days'])->sum(fn (array $d) => count($d['slots']));
                if (count($shift1['flat_classes']) === 0 || $shift2SlotTotalCurrent > 0) {
                    $shift1 = $this->buildDefaultTemplateShiftData($academicYear, 'pagi');
                }
                $shift2 = [
                    'shift_key' => 'siang',
                    'groups' => [],
                    'flat_classes' => [],
                    'total_class_cols' => 0,
                    'days' => [],
                ];
            } elseif ($templateShiftMode === 'double') {
                $shift2SlotTotalCurrent = collect($shift2['days'])->sum(fn (array $d) => count($d['slots']));
                if (count($shift2['flat_classes']) === 0 || $shift2SlotTotalCurrent === 0) {
                    $allClasses = $this->resolveNormalizedTemplateClassCodes($academicYear);
                    $afternoonClasses = array_values(array_filter($allClasses, fn (string $c) => str_starts_with($c, '8')));
                    $morningClasses = array_values(array_filter($allClasses, fn (string $c) => ! str_starts_with($c, '8')));

                    if (empty($afternoonClasses) && count($allClasses) >= 2) {
                        $half = (int) ceil(count($allClasses) / 2);
                        $morningClasses = array_slice($allClasses, 0, $half);
                        $afternoonClasses = array_slice($allClasses, $half);
                    }

                    $shift1 = $this->buildDefaultTemplateShiftData($academicYear, 'pagi', $morningClasses);
                    $shift2 = $this->buildDefaultTemplateShiftData($academicYear, 'siang', $afternoonClasses);
                }
            } elseif (count($shift1['flat_classes']) === 0) {
                $shift1 = $this->buildDefaultTemplateShiftData($academicYear, 'pagi');
            }
        }

        $shift2SlotTotal = collect($shift2['days'])->sum(fn (array $d) => count($d['slots']));
        $isTwoShift = count($shift2['flat_classes']) > 0 && $shift2SlotTotal > 0;

        $allTeachers = $this->getMasterTeacherDirectory($academicYear);
        $piketList = $this->getPiketScheduleList();
        $homeroomList = $this->getHomeroomList($academicYear);

        return [
            'is_two_shift' => $isTwoShift,
            'shift1' => $shift1,
            'shift2' => $shift2,
            'teachers' => $allTeachers,
            'piket_list' => $piketList,
            'homeroom_list' => $homeroomList,
            'total_items' => $matrixData['total_items'] ?? 0,
            'total_conflicts' => $matrixData['total_conflicts'] ?? 0,
        ];
    }

    protected function buildShiftExportData(array $matrixByDay, string $shiftKey): array
    {
        $groupInfo = $this->discoverShiftClassGroups($matrixByDay, $shiftKey);
        $groups = $groupInfo['groups'];
        $flatClasses = $groupInfo['flat_classes'];

        $days = [];
        foreach (self::DAYS_MAP as $dNum => $dName) {
            $rawSlots = $matrixByDay[$dNum][$shiftKey . '_slots'] ?? collect();
            $dayUpper = match ((int) $dNum) {
                5 => "JUM'AT",
                default => strtoupper($dName),
            };

            $formattedSlots = [];
            foreach ($rawSlots as $slot) {
                $timeRange = trim((string) ($slot['time_range'] ?? ''));
                $classesMap = $slot['classes'] ?? collect();
                $isActivity = (bool) ($slot['is_activity'] ?? false);

                if ($timeRange === '' && ! $isActivity && $classesMap->isEmpty()) {
                    continue;
                }

                $activityName = trim((string) ($slot['activity_name'] ?? ''));
                $periodOrder = (int) ($slot['period_order'] ?? 0);
                $jpLabel = $this->resolveExportJpLabel($periodOrder, $isActivity, $activityName);

                $cells = [];
                foreach ($flatClasses as $clsCode) {
                    $cells[$clsCode] = $classesMap->get($clsCode);
                }

                // Cek per kelompok tingkat apakah memiliki aktivitas bersama (mendukung kasus seperti Sabtu jam ke-4 per tingkat)
                $groupCells = [];
                foreach ($groups as $gIdx => $grp) {
                    $groupActName = null;
                    $allActivityOrEmpty = true;
                    foreach ($grp['classes'] as $cMeta) {
                        $item = $cells[$cMeta['code']] ?? null;
                        if ($item && $item->isCommonActivity()) {
                            $groupActName = trim((string) $item->teacher_code);
                        } elseif ($item && trim((string) $item->teacher_code) !== '') {
                            $allActivityOrEmpty = false;
                        }
                    }

                    $groupCells[$gIdx] = [
                        'is_activity' => $groupActName !== null && $allActivityOrEmpty,
                        'activity_name' => $groupActName ?: $activityName,
                        'is_highlighted' => $groupActName !== null && $this->isHighlightedActivity($groupActName),
                    ];
                }

                $formattedSlots[] = [
                    'period_order' => $periodOrder,
                    'jp_label' => $jpLabel,
                    'time_range' => $timeRange,
                    'is_activity' => $isActivity,
                    'activity_name' => $activityName,
                    'is_highlighted' => $isActivity && $this->isHighlightedActivity($activityName),
                    'cells' => $cells,
                    'group_cells' => $groupCells,
                ];
            }

            if (count($formattedSlots) > 0) {
                $days[] = [
                    'day_num' => $dNum,
                    'day_name' => $dayUpper,
                    'slots' => $formattedSlots,
                ];
            }
        }

        return [
            'shift_key' => $shiftKey,
            'groups' => $groups,
            'flat_classes' => $flatClasses,
            'total_class_cols' => count($flatClasses),
            'days' => $days,
        ];
    }

    /**
     * @return array{groups: list<array{grade_key: string, grade_label: string, classes: list<array{code: string, section: string}>}>, flat_classes: list<string>}
     */
    protected function discoverShiftClassGroups(array $matrixByDay, string $shiftKey): array
    {
        $codes = [];
        foreach ($matrixByDay as $day) {
            $slots = $day[$shiftKey . '_slots'] ?? collect();
            foreach ($slots as $slot) {
                $classesMap = $slot['classes'] ?? collect();
                foreach ($classesMap->keys() as $cCode) {
                    $clean = self::normalizeClassName((string) $cCode);
                    if ($clean !== '') {
                        $codes[$clean] = $clean;
                    }
                }
            }
        }

        return $this->groupNormalizedClasses(array_values($codes));
    }

    /**
     * @param  list<string>  $classCodes
     * @return array{groups: list<array{grade_key: string, grade_label: string, classes: list<array{code: string, section: string}>}>, flat_classes: list<string>}
     */
    protected function groupNormalizedClasses(array $classCodes): array
    {
        $unique = array_values(array_unique(array_filter(array_map(
            fn ($c) => self::normalizeClassName((string) $c),
            $classCodes
        ))));

        usort($unique, fn (string $a, string $b) => strnatcasecmp($a, $b));

        $grouped = [];
        foreach ($unique as $code) {
            if (preg_match('/^(\d+)(.+)$/', $code, $m)) {
                $gradeKey = $m[1];
                $section = strtoupper($m[2]);
            } else {
                $gradeKey = $code;
                $section = $code;
            }

            if (! isset($grouped[$gradeKey])) {
                $grouped[$gradeKey] = [
                    'grade_key' => $gradeKey,
                    'grade_label' => $this->formatGradeRomanLabel($gradeKey),
                    'classes' => [],
                ];
            }

            $grouped[$gradeKey]['classes'][] = [
                'code' => $code,
                'section' => $section,
            ];
        }

        $groups = array_values($grouped);
        $flat = [];
        foreach ($groups as $grp) {
            foreach ($grp['classes'] as $cls) {
                $flat[] = $cls['code'];
            }
        }

        return [
            'groups' => $groups,
            'flat_classes' => $flat,
        ];
    }

    protected function formatGradeRomanLabel(string $gradeKey): string
    {
        return match (trim($gradeKey)) {
            '7' => 'KELAS VII',
            '8' => 'KELAS VIII',
            '9' => 'KELAS IX',
            '10' => 'KELAS X',
            '11' => 'KELAS XI',
            '12' => 'KELAS XII',
            default => 'KELAS ' . strtoupper(trim($gradeKey)),
        };
    }

    protected function resolveExportJpLabel(int $periodOrder, bool $isActivity, string $activityName): string
    {
        if (! $isActivity) {
            return $periodOrder > 0 ? (string) $periodOrder : '';
        }

        $upper = strtoupper($activityName);
        if (str_contains($upper, 'ISTIRAHAT') || str_contains($upper, 'PEMBIASAAN')) {
            return '';
        }

        return $periodOrder >= 0 ? (string) $periodOrder : '';
    }

    protected function isHighlightedActivity(string $activityName): bool
    {
        $upper = strtoupper(trim($activityName));

        return str_contains($upper, 'DHUHA')
            || str_contains($upper, 'ISTIRAHAT')
            || str_contains($upper, 'KEAGAMAAN')
            || str_contains($upper, 'PAGI CERIA')
            || str_contains($upper, 'SABSIH');
    }

    /**
     * Ambil daftar kode rombel ternormalisasi untuk kebutuhan kerangka template Excel.
     *
     * @return list<string>
     */
    protected function resolveNormalizedTemplateClassCodes(AcademicYear $academicYear): array
    {
        $schoolClassCodes = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => self::normalizeClassName((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($schoolClassCodes)) {
            $schoolClassCodes = [
                '7A', '7B', '7C', '7D', '7E', '7F', '7G',
                '8A', '8B', '8C', '8D', '8E', '8F', '8G',
                '9A', '9B', '9C', '9D', '9E', '9F', '9G',
            ];
        }

        return $schoolClassCodes;
    }

    /**
     * @param  ?list<string>  $explicitClassCodes
     */
    protected function buildDefaultTemplateShiftData(
        AcademicYear $academicYear,
        string $shiftKey = 'pagi',
        ?array $explicitClassCodes = null
    ): array {
        $schoolClassCodes = $explicitClassCodes !== null && ! empty($explicitClassCodes)
            ? array_values($explicitClassCodes)
            : $this->resolveNormalizedTemplateClassCodes($academicYear);

        $groupInfo = $this->groupNormalizedClasses($schoolClassCodes);
        $timeGrid = $this->calculateTimeGrid($this->getDefaultTimeGridConfig());
        $slotSourceKey = $shiftKey === 'siang' ? 'slots_siang' : 'slots';
        $days = [];

        foreach ($timeGrid as $dNum => $dData) {
            $dayUpper = $dNum === 5 ? "JUM'AT" : strtoupper(self::DAYS_MAP[$dNum] ?? ('HARI ' . $dNum));
            $slots = [];
            foreach ($dData[$slotSourceKey] ?? [] as $slot) {
                $isAct = (bool) ($slot['is_activity'] ?? false);
                $actName = trim((string) ($slot['activity'] ?? ''));
                $period = (int) ($slot['period_order'] ?? 0);

                $groupCells = [];
                foreach ($groupInfo['groups'] as $gIdx => $grp) {
                    $groupCells[$gIdx] = [
                        'is_activity' => $isAct,
                        'activity_name' => $actName,
                        'is_highlighted' => $isAct && $this->isHighlightedActivity($actName),
                    ];
                }

                $slots[] = [
                    'period_order' => $period,
                    'jp_label' => $this->resolveExportJpLabel($period, $isAct, $actName),
                    'time_range' => (string) ($slot['time_range'] ?? ''),
                    'is_activity' => $isAct,
                    'activity_name' => $actName,
                    'is_highlighted' => $isAct && $this->isHighlightedActivity($actName),
                    'cells' => [],
                    'group_cells' => $groupCells,
                ];
            }

            $days[] = [
                'day_num' => $dNum,
                'day_name' => $dayUpper,
                'slots' => $slots,
            ];
        }

        return [
            'shift_key' => $shiftKey,
            'groups' => $groupInfo['groups'],
            'flat_classes' => $groupInfo['flat_classes'],
            'total_class_cols' => count($groupInfo['flat_classes']),
            'days' => $days,
        ];
    }

    /**
     * Impor Master Jadwal Pelajaran dari file Excel (.xlsx / .xls) atau CSV
     * dengan dukungan otomatis layout 1 Shift maupun 2 Shift berdampingan.
     *
     * @throws \InvalidArgumentException
     */
    public function importMasterScheduleFromFile(
        AcademicYear $academicYear,
        string $filePath,
        bool $syncToClasses = false
    ): array {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $grid = $this->buildExpandedSheetGrid($sheet);

        if (empty($grid)) {
            throw new \InvalidArgumentException('File Excel/CSV kosong atau tidak dapat dibaca.');
        }

        $shiftAnchors = $this->findScheduleShiftAnchors($grid);
        if (empty($shiftAnchors)) {
            throw new \InvalidArgumentException('Format file tidak dikenali: kolom header HARI dan WAKTU tidak ditemukan.');
        }

        $legendAnchors = $this->findLegendTableAnchors($grid);

        $shiftBlocks = [];
        foreach ($shiftAnchors as $idx => $anchor) {
            $nextAnchorCol = $shiftAnchors[$idx + 1]['hari_col'] ?? null;
            $minLegendCol = null;
            foreach ($legendAnchors as $la) {
                $laCol = (int) $la['first_col'];
                if ($laCol > $anchor['waktu_col'] && ($minLegendCol === null || $laCol < $minLegendCol)) {
                    $minLegendCol = $laCol;
                }
            }

            $stopCol = PHP_INT_MAX;
            if ($nextAnchorCol !== null) {
                $stopCol = min($stopCol, (int) $nextAnchorCol);
            }
            if ($minLegendCol !== null) {
                $stopCol = min($stopCol, $minLegendCol);
            }

            $classColMeta = $this->detectShiftClassColumns($grid, $anchor, $stopCol);
            if (! empty($classColMeta['columns'])) {
                $shiftBlocks[] = [
                    'shift' => $idx === 0 ? 'pagi' : 'siang',
                    'anchor' => $anchor,
                    'class_columns' => $classColMeta['columns'],
                    'data_start_row' => $classColMeta['data_start_row'],
                ];
            }
        }

        if (empty($shiftBlocks)) {
            throw new \InvalidArgumentException('Kolom rombel kelas tidak terdeteksi di sebelah kanan kolom WAKTU.');
        }

        $parsedTeachers = $this->parseTeacherLegend($grid, $legendAnchors['teachers'] ?? null);
        $parsedPiket = $this->parsePiketLegend($grid, $legendAnchors['piket'] ?? null);
        $parsedHomerooms = $this->parseHomeroomLegend($grid, $legendAnchors['homeroom'] ?? null);

        $parsedItems = [];
        foreach ($shiftBlocks as $block) {
            $blockItems = $this->parseShiftGridItems(
                $grid,
                $block['shift'],
                $block['anchor'],
                $block['class_columns'],
                $block['data_start_row']
            );
            foreach ($blockItems as $item) {
                $parsedItems[] = $item;
            }
        }

        if (empty($parsedItems)) {
            throw new \InvalidArgumentException('Tidak ditemukan satu pun slot jadwal pada tabel untuk diimpor.');
        }

        DB::transaction(function () use (
            $academicYear,
            $parsedTeachers,
            $parsedPiket,
            $parsedHomerooms,
            $parsedItems
        ) {
            // 1. Validasi anti-bentrok & sinkronkan langsung ke Master Guru, Piket, dan Master Rombel (dilarang buat mapel baru otomatis)
            $codeTeacherMap = $this->syncImportedMasterEntities($academicYear, $parsedTeachers, $parsedPiket, $parsedHomerooms, false);

            // 2. Pastikan seluruh rombel pada matriks tersedia di Master Rombel
            $allClassCodes = collect($parsedItems)->pluck('class_name')->unique()->filter()->values();
            $schoolClassesMap = [];
            foreach ($allClassCodes as $cCode) {
                $formatted = self::formatClassDisplayName($cCode);
                $grade = explode('-', $formatted)[0] ?: 'VII';

                $sc = SchoolClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->get()
                    ->first(fn (SchoolClass $c) => self::normalizeClassName($c->name) === $cCode);

                if (! $sc) {
                    $sc = SchoolClass::query()->firstOrCreate(
                        [
                            'academic_year_id' => $academicYear->id,
                            'name' => $formatted,
                        ],
                        [
                            'grade_level' => $grade,
                            'homeroom_teacher_name' => '-',
                        ]
                    );
                }

                $schoolClassesMap[$cCode] = $sc;
            }

            // 3. Tulis ulang item matriks dengan user_id, teacher_name, dan subject_id tersinkron dari Master Guru
            SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

            foreach ($parsedItems as $row) {
                $tCode = $row['teacher_code'];
                $isAct = (bool) $row['is_activity'];
                $normCode = (! $isAct && $tCode !== null) ? self::normalizeTeachingCode($tCode) : '';
                $tMeta = $normCode !== '' ? ($codeTeacherMap[$normCode] ?? null) : null;
                $sc = $schoolClassesMap[$row['class_name']] ?? null;

                SchoolScheduleItem::query()->create([
                    'academic_year_id' => $academicYear->id,
                    'day_of_week' => $row['day_of_week'],
                    'shift' => $row['shift'],
                    'period_order' => $row['period_order'],
                    'time_range' => $row['time_range'],
                    'class_name' => $row['class_name'],
                    'school_class_id' => $sc?->id,
                    'teacher_code' => $isAct ? $tCode : ($normCode !== '' ? $normCode : null),
                    'teacher_name' => $tMeta['name'] ?? ($isAct ? $tCode : null),
                    'subject_name' => $tMeta['subject'] ?? null,
                    'user_id' => $tMeta['user_id'] ?? null,
                    'subject_id' => $tMeta['subject_id'] ?? null,
                ]);
            }
        });

        $conflicts = $this->detectConflicts($academicYear);
        $syncStats = null;
        if ($syncToClasses) {
            $syncStats = $this->syncToTeachingClasses($academicYear);
        }

        return [
            'teachers_count' => $this->getMasterTeacherDirectory($academicYear)->count(),
            'items_count' => SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count(),
            'conflicts_count' => count($conflicts),
            'shifts_detected' => count($shiftBlocks),
            'synced_classes' => $syncStats['synced_classes'] ?? 0,
            'created_classes' => $syncStats['created_classes'] ?? 0,
        ];
    }

    /**
     * Baca nilai sel Excel/CSV sekaligus menyalin nilai sel merged ke seluruh sel cakupannya di memori.
     *
     * @return list<list<string>>
     */
    protected function buildExpandedSheetGrid(Worksheet $sheet): array
    {
        $raw = $sheet->toArray(null, true, true, false);
        if (empty($raw)) {
            return [];
        }

        $grid = [];
        foreach ($raw as $rIdx => $row) {
            $line = [];
            foreach ($row as $cIdx => $val) {
                $line[(int) $cIdx] = trim((string) ($val ?? ''));
            }
            $grid[(int) $rIdx] = $line;
        }

        foreach ($sheet->getMergeCells() as $mergeRange) {
            if (! str_contains($mergeRange, ':')) {
                continue;
            }
            [$startRef, $endRef] = explode(':', $mergeRange, 2);
            [$startColStr, $startRowNum] = Coordinate::coordinateFromString($startRef);
            [$endColStr, $endRowNum] = Coordinate::coordinateFromString($endRef);

            $c1 = Coordinate::columnIndexFromString($startColStr) - 1;
            $c2 = Coordinate::columnIndexFromString($endColStr) - 1;
            $r1 = ((int) $startRowNum) - 1;
            $r2 = ((int) $endRowNum) - 1;

            $anchorVal = trim((string) ($grid[$r1][$c1] ?? ''));
            if ($anchorVal === '') {
                continue;
            }

            // Abaikan merge judul kop di baris paling atas bila tidak relevan untuk tabel
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if (isset($grid[$r]) && (! isset($grid[$r][$c]) || $grid[$r][$c] === '')) {
                        $grid[$r][$c] = $anchorVal;
                    }
                }
            }
        }

        return $grid;
    }

    /**
     * Temukan posisi kolom HARI, JP KE, dan WAKTU untuk Shift 1 (dan Shift 2 bila ada).
     *
     * @return list<array{row: int, hari_col: int, jp_col: int, waktu_col: int}>
     */
    protected function findScheduleShiftAnchors(array $grid): array
    {
        $maxScanRow = min(25, count($grid) - 1);
        for ($r = 0; $r <= $maxScanRow; $r++) {
            $row = $grid[$r] ?? [];
            $anchors = [];
            $colCount = empty($row) ? 0 : (max(array_keys($row)) + 1);

            for ($c = 0; $c < $colCount; $c++) {
                $val = strtoupper(trim((string) ($row[$c] ?? '')));
                if ($val !== 'HARI') {
                    continue;
                }

                $jpCol = null;
                $waktuCol = null;
                for ($offset = 1; $offset <= 3; $offset++) {
                    $nextVal = strtoupper((string) preg_replace('/\s+/', ' ', trim((string) ($row[$c + $offset] ?? ''))));
                    if (in_array($nextVal, ['JP KE', 'JM KE', 'JM', 'JAM', 'JAM KE'], true) && $jpCol === null) {
                        $jpCol = $c + $offset;
                    } elseif ($nextVal === 'WAKTU') {
                        $waktuCol = $c + $offset;
                        break;
                    }
                }

                if ($waktuCol !== null) {
                    $anchors[] = [
                        'row' => $r,
                        'hari_col' => $c,
                        'jp_col' => $jpCol ?? ($c + 1),
                        'waktu_col' => $waktuCol,
                    ];
                }
            }

            if (! empty($anchors)) {
                return $anchors;
            }
        }

        return [];
    }

    /**
     * Deteksi pasangan indeks kolom -> kode rombel ('7A', '8C', dst.) untuk satu blok shift.
     *
     * @return array{columns: array<int, string>, data_start_row: int}
     */
    protected function detectShiftClassColumns(array $grid, array $anchor, int $stopCol): array
    {
        $hdrRow = $anchor['row'];
        $subRow = $hdrRow + 1;
        $row1 = $grid[$hdrRow] ?? [];
        $row2 = $grid[$subRow] ?? [];

        $hasSubRow = false;
        $maxRow1 = empty($row1) ? $anchor['waktu_col'] : max(array_keys($row1));
        $maxRow2 = empty($row2) ? $anchor['waktu_col'] : max(array_keys($row2));
        $scanLimit = min($stopCol - 1, max($maxRow1, $maxRow2));

        for ($c = $anchor['waktu_col'] + 1; $c <= $scanLimit; $c++) {
            $subVal = trim((string) ($row2[$c] ?? ''));
            if ($subVal !== '' && preg_match('/^(?:[A-Z]|[7-9][A-G]|(?:VII|VIII|IX)[-\s]?[A-G])$/i', $subVal)) {
                $hasSubRow = true;
                break;
            }
        }

        $columns = [];
        for ($c = $anchor['waktu_col'] + 1; $c <= $scanLimit; $c++) {
            $topVal = trim((string) ($row1[$c] ?? ''));
            $botVal = $hasSubRow ? trim((string) ($row2[$c] ?? '')) : '';

            if ($topVal === '' && $botVal === '') {
                if (! empty($columns)) {
                    break;
                }
                continue;
            }

            $resolved = $this->combineGradeAndSectionToClassCode($topVal, $botVal);
            if ($resolved !== null) {
                $columns[$c] = $resolved;
            }
        }

        return [
            'columns' => $columns,
            'data_start_row' => $hasSubRow ? ($subRow + 1) : ($hdrRow + 1),
        ];
    }

    protected function combineGradeAndSectionToClassCode(string $topVal, string $botVal): ?string
    {
        if ($botVal !== '' && preg_match('/^(?:VII|VIII|IX|7|8|9)\s*[-]?\s*[A-Z]$/i', $botVal)) {
            $norm = self::normalizeClassName($botVal);
            return $norm !== '' ? $norm : null;
        }

        if ($botVal === '' && $topVal !== '' && preg_match('/^(?:VII|VIII|IX|7|8|9)\s*[-]?\s*[A-Z]$/i', $topVal)) {
            $norm = self::normalizeClassName($topVal);
            return $norm !== '' ? $norm : null;
        }

        $topClean = strtoupper(trim($topVal));
        $gradeNum = null;
        if (str_contains($topClean, 'VIII') || preg_match('/\b8\b/', $topClean)) {
            $gradeNum = '8';
        } elseif (str_contains($topClean, 'VII') || preg_match('/\b7\b/', $topClean)) {
            $gradeNum = '7';
        } elseif (str_contains($topClean, 'IX') || preg_match('/\b9\b/', $topClean)) {
            $gradeNum = '9';
        } elseif (preg_match('/(\d+)/', $topClean, $m)) {
            $gradeNum = $m[1];
        }

        if ($gradeNum !== null && $botVal !== '') {
            return $gradeNum . strtoupper(trim($botVal));
        }

        $fallback = self::normalizeClassName($topVal . $botVal);
        return $fallback !== '' ? $fallback : null;
    }

    /**
     * Temukan posisi 3 tabel legenda samping kanan: Kode Guru, Guru Piket, dan Wali Kelas.
     *
     * @return array<string, array{row: int, first_col: int, second_col: int, third_col: int}>
     */
    protected function findLegendTableAnchors(array $grid): array
    {
        $anchors = [];

        foreach ($grid as $rIdx => $row) {
            if (empty($row)) {
                continue;
            }
            $maxC = max(array_keys($row));
            for ($c = 0; $c <= $maxC - 2; $c++) {
                $h1 = strtoupper(trim((string) ($row[$c] ?? '')));
                $h2 = strtoupper(trim((string) ($row[$c + 1] ?? '')));
                $h3 = strtoupper(trim((string) ($row[$c + 2] ?? '')));

                if (! isset($anchors['teachers']) && $h1 === 'KODE' && str_contains($h2, 'NAMA GURU')) {
                    $anchors['teachers'] = [
                        'row' => (int) $rIdx,
                        'first_col' => $c,
                        'second_col' => $c + 1,
                        'third_col' => $c + 2,
                    ];
                } elseif (! isset($anchors['piket']) && str_contains($h2, 'GURU PIKET') && $h3 === 'HARI') {
                    $anchors['piket'] = [
                        'row' => (int) $rIdx,
                        'first_col' => $c,
                        'second_col' => $c + 1,
                        'third_col' => $c + 2,
                    ];
                } elseif (! isset($anchors['homeroom']) && str_contains($h2, 'NAMA GURU') && (str_contains($h3, 'WALI KELAS') || str_contains($h3, 'WAL KELAS'))) {
                    $anchors['homeroom'] = [
                        'row' => (int) $rIdx,
                        'first_col' => $c,
                        'second_col' => $c + 1,
                        'third_col' => $c + 2,
                    ];
                }
            }
        }

        return $anchors;
    }

    /**
     * @return list<array{code: string, name: string, subject: string}>
     */
    protected function parseTeacherLegend(array $grid, ?array $anchor): array
    {
        if (! $anchor) {
            return [];
        }

        $result = [];
        $totalRows = count($grid);
        for ($r = $anchor['row'] + 1; $r < $totalRows; $r++) {
            $row = $grid[$r] ?? [];
            $code = trim((string) ($row[$anchor['first_col']] ?? ''));
            $name = trim((string) ($row[$anchor['second_col']] ?? ''));
            $subj = trim((string) ($row[$anchor['third_col']] ?? ''));

            if (str_contains(strtoupper($name), 'NAMA GURU PIKET') || strtoupper($subj) === 'HARI') {
                break;
            }
            if (
                strtoupper($code) === 'KODE'
                || strtoupper($name) === 'NAMA GURU'
                || str_contains(strtoupper($subj), 'MATA PELAJARAN')
            ) {
                continue;
            }
            if ($code === '' || $name === '') {
                continue;
            }

            $result[] = [
                'code' => $code,
                'name' => $name,
                'subject' => $subj,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{day: string, name: string}>
     */
    protected function parsePiketLegend(array $grid, ?array $anchor): array
    {
        if (! $anchor) {
            return [];
        }

        $result = [];
        $totalRows = count($grid);

        for ($r = $anchor['row'] + 1; $r < $totalRows; $r++) {
            $row = $grid[$r] ?? [];
            $name = trim((string) ($row[$anchor['second_col']] ?? ''));
            $rawDay = trim((string) ($row[$anchor['third_col']] ?? ''));

            if (str_contains(strtoupper($rawDay), 'WALI KELAS') || str_contains(strtoupper($rawDay), 'WAL KELAS')) {
                break;
            }
            if (str_contains(strtoupper($name), 'GURU PIKET') || strtoupper($rawDay) === 'HARI') {
                continue;
            }
            $day = User::normalizePiketDay($rawDay);
            if ($name === '' || $day === null) {
                if (! empty($result) && $rawDay !== '' && $day === null) {
                    break;
                }
                continue;
            }

            $result[] = [
                'day' => $day,
                'name' => $name,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected function parseHomeroomLegend(array $grid, ?array $anchor): array
    {
        if (! $anchor) {
            return [];
        }

        $result = [];
        $totalRows = count($grid);

        for ($r = $anchor['row'] + 1; $r < $totalRows; $r++) {
            $row = $grid[$r] ?? [];
            $name = trim((string) ($row[$anchor['second_col']] ?? ''));
            $clsRaw = trim((string) ($row[$anchor['third_col']] ?? ''));

            if ($name === '' && $clsRaw === '') {
                if (! empty($result)) {
                    break;
                }
                continue;
            }

            if (
                strtoupper($name) === 'NAMA GURU'
                || str_contains(strtoupper($clsRaw), 'WALI KELAS')
                || str_contains(strtoupper($clsRaw), 'WAL KELAS')
            ) {
                continue;
            }

            $normCls = self::normalizeClassName($clsRaw);
            if ($name !== '' && $normCls !== '') {
                $result[$normCls] = $name;
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseShiftGridItems(
        array $grid,
        string $shiftKey,
        array $anchor,
        array $classColumns,
        int $dataStartRow
    ): array {
        $items = [];
        $currentDay = 'SENIN';
        $totalRows = count($grid);

        for ($r = $dataStartRow; $r < $totalRows; $r++) {
            $row = $grid[$r] ?? [];
            $rawDay = trim((string) ($row[$anchor['hari_col']] ?? ''));
            $rawJp = trim((string) ($row[$anchor['jp_col']] ?? ''));
            $rawWaktu = trim((string) ($row[$anchor['waktu_col']] ?? ''));

            if ($rawDay !== '') {
                $upperDay = strtoupper($rawDay);
                if (in_array(str_replace("'", '', $upperDay), ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'], true)) {
                    $currentDay = $rawDay;
                }
            }

            if ($rawWaktu === '') {
                continue;
            }

            $dayNum = $this->parseDayOfWeek($currentDay);
            $periodOrder = is_numeric($rawJp) ? (int) $rawJp : 0;

            $rowValues = [];
            $hasAnyActivity = false;
            $hasAnyCellValue = false;

            foreach ($classColumns as $colIdx => $clsCode) {
                $val = trim((string) ($row[$colIdx] ?? ''));
                $rowValues[$clsCode] = $val;
                if ($val !== '' && $val !== '-') {
                    $hasAnyCellValue = true;
                    $tmpItem = new SchoolScheduleItem(['teacher_code' => $val]);
                    if ($tmpItem->isCommonActivity()) {
                        $hasAnyActivity = true;
                    }
                }
            }

            if (! $hasAnyCellValue) {
                continue;
            }

            foreach ($rowValues as $clsCode => $val) {
                if ($hasAnyActivity && ($val === '' || $val === '-')) {
                    continue;
                }

                $teacherCode = ($val === '' || $val === '-') ? null : $val;
                if (! $hasAnyActivity && $teacherCode === null && $periodOrder <= 0) {
                    continue;
                }

                $tmp = new SchoolScheduleItem(['teacher_code' => $teacherCode]);

                $items[] = [
                    'day_of_week' => $dayNum,
                    'shift' => $shiftKey,
                    'period_order' => $periodOrder,
                    'time_range' => $rawWaktu,
                    'class_name' => $clsCode,
                    'teacher_code' => $teacherCode,
                    'is_activity' => $tmp->isCommonActivity(),
                ];
            }
        }

        return $items;
    }
}
