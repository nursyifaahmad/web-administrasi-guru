<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Konversi string waktu seperti "07.40 - 08.10" menjadi format jam mulai "07:40"
     * untuk keperluan pengurutan kronologis
     */
    public static function extractStartTime(?string $timeRange): string
    {
        if (empty($timeRange)) {
            return '99:99';
        }
        $parts = explode('-', $timeRange);
        $start = trim($parts[0] ?? '');
        $start = str_replace('.', ':', $start);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $start, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        return $start;
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
     * Memuat jadwal resmi Wakasek SMPN 2 Lemahabang (21 Rombel, 36 Guru, 2 Shift)
     */
    public function importFromWakasekCsv(AcademicYear $academicYear, ?string $csvContent = null): array
    {
        $content = $csvContent ?: self::RAW_WAKASEK_CSV;
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        // 1. Ekstrak guru, wali kelas, dan guru piket dari kolom kanan
        $teachersData = [];
        $homeroomMap = [];
        $piketMap = [];

        foreach ($lines as $idx => $line) {
            if ($idx < 5) {
                continue;
            }
            $row = str_getcsv($line);
            $code = trim($row[27] ?? '');
            $name = trim($row[28] ?? '');
            $subject = trim($row[29] ?? '');

            if ($code !== '' && $name !== '') {
                $teachersData[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'subject' => $subject,
                ];
            }

            // Wali Kelas
            $wkName = trim($row[31] ?? '');
            $wkClass = trim($row[32] ?? '');
            if ($wkName !== '' && $wkClass !== '') {
                $homeroomMap[$wkClass] = $wkName;
            }

            // Guru Piket
            $piketGuru = trim($row[31] ?? '');
            $piketHari = trim($row[32] ?? '');
            if ($idx >= 24 && $idx <= 30 && $piketGuru !== '' && $piketHari !== '') {
                $piketMap[$piketHari] = $piketGuru;
            }
        }

        // Hubungkan kode guru ke akun users bila ada kecocokan nama (hanya role guru)
        $users = User::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'guru')->orWhereNull('role');
            })
            ->get();
        $subjects = Subject::query()->get();

        DB::transaction(function () use ($academicYear, $teachersData, $homeroomMap, $piketMap, $users, $subjects, $lines) {
            // Simpan / update guru jadwal
            foreach ($teachersData as $code => $t) {
                $cleanT = self::cleanAlphaString($t['name']);
                $pureT = self::cleanAlphaString(explode(',', $t['name'])[0]);

                $matchedUser = null;
                if (strlen($cleanT) >= 4 && strlen($pureT) >= 4) {
                    $matchedUser = $users->first(function ($u) use ($cleanT, $pureT) {
                        if ($u->isAdmin()) {
                            return false;
                        }
                        $cleanU = self::cleanAlphaString($u->name);
                        $pureU = self::cleanAlphaString(explode(',', $u->name)[0]);

                        if (strlen($cleanU) < 4 || strlen($pureU) < 4) {
                            return false;
                        }

                        if ($cleanU === $cleanT || $pureU === $pureT) {
                            return true;
                        }

                        return (strlen($pureU) >= 4 && strlen($pureT) >= 4)
                            && ($pureU === $pureT || str_contains($pureT, $pureU) || str_contains($pureU, $pureT));
                    });
                }

                $matchedSubject = $subjects->first(function ($s) use ($t) {
                    $cleanS = self::cleanAlphaString($s->name);
                    $cleanSubj = self::cleanAlphaString($t['subject']);
                    return str_contains($cleanS, $cleanSubj) || str_contains($cleanSubj, $cleanS);
                });

                // Cek penugasan wali kelas
                $assignedWk = null;
                foreach ($homeroomMap as $cName => $wName) {
                    $cleanW = self::cleanAlphaString($wName);
                    if (str_contains($cleanW, $cleanT) || str_contains($cleanT, $cleanW)) {
                        $assignedWk = $cName;
                        break;
                    }
                }

                // Cek guru piket
                $assignedPiket = null;
                foreach ($piketMap as $dayName => $pName) {
                    $cleanP = self::cleanAlphaString($pName);
                    if (str_contains($cleanP, $cleanT) || str_contains($cleanT, $cleanP)) {
                        $assignedPiket = $dayName;
                        break;
                    }
                }

                SchoolScheduleTeacher::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $t['name'],
                        'subject' => $t['subject'],
                        'user_id' => $matchedUser?->id,
                        'homeroom_class' => $assignedWk,
                        'piket_day' => $assignedPiket,
                    ]
                );
            }

            // 2. Pastikan 21 Rombel tersedia di SchoolClass
            $allClassNames = [
                '7A' => 'VII', '7B' => 'VII', '7C' => 'VII', '7D' => 'VII', '7E' => 'VII', '7F' => 'VII', '7G' => 'VII',
                '8A' => 'VIII', '8B' => 'VIII', '8C' => 'VIII', '8D' => 'VIII', '8E' => 'VIII', '8F' => 'VIII', '8G' => 'VIII',
                '9A' => 'IX', '9B' => 'IX', '9C' => 'IX', '9D' => 'IX', '9E' => 'IX', '9F' => 'IX', '9G' => 'IX',
            ];

            $schoolClassesMap = [];
            foreach ($allClassNames as $cName => $grade) {
                $wkName = $homeroomMap[$cName] ?? null;
                $formattedName = $grade . '-' . substr($cName, -1); // e.g. VII-A

                $sc = SchoolClass::query()->firstOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $formattedName,
                    ],
                    [
                        'grade_level' => $grade,
                        'homeroom_teacher_name' => $wkName ?: 'Wali Kelas ' . $formattedName,
                    ]
                );
                $schoolClassesMap[$cName] = $sc;
                $schoolClassesMap[$formattedName] = $sc;
            }

            // 3. Bersihkan items lama pada tahun ajaran ini
            SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

            // 4. Parse grid jadwal
            $currentDayM = 'SENIN';
            $currentDayA = 'SENIN';

            $scheduleTeachers = SchoolScheduleTeacher::query()->where('academic_year_id', $academicYear->id)->get()->keyBy('code');

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

                // Shift Pagi (Kelas 7 dan Kelas 9)
                if ($waktuM !== '') {
                    $dayNum = $this->parseDayOfWeek($currentDayM);
                    $periodOrder = is_numeric($jamM) ? (int) $jamM : 0;

                    $pagiColumns = [
                        3 => '7A', 4 => '7B', 5 => '7C', 6 => '7D', 7 => '7E', 8 => '7F', 9 => '7G',
                        10 => '9A', 11 => '9B', 12 => '9C', 13 => '9D', 14 => '9E', 15 => '9F', 16 => '9G',
                    ];

                    foreach ($pagiColumns as $colIdx => $clsShort) {
                        $rawVal = trim($row[$colIdx] ?? '');
                        if ($rawVal === '') {
                            continue;
                        }

                        $sc = $schoolClassesMap[$clsShort] ?? null;
                        $tRecord = $scheduleTeachers->get($rawVal);

                        SchoolScheduleItem::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'day_of_week' => $dayNum,
                            'shift' => 'pagi',
                            'period_order' => $periodOrder,
                            'time_range' => $waktuM,
                            'class_name' => $clsShort,
                            'school_class_id' => $sc?->id,
                            'teacher_code' => $rawVal,
                            'teacher_name' => $tRecord?->name,
                            'subject_name' => $tRecord?->subject,
                            'user_id' => $tRecord?->user_id,
                        ]);
                    }
                }

                // Shift Siang (Kelas 8)
                if ($waktuA !== '') {
                    $dayNum = $this->parseDayOfWeek($currentDayA);
                    $periodOrder = is_numeric($jamA) ? (int) $jamA : 0;

                    $siangColumns = [
                        20 => '8A', 21 => '8B', 22 => '8C', 23 => '8D', 24 => '8E', 25 => '8F', 26 => '8G',
                    ];

                    foreach ($siangColumns as $colIdx => $clsShort) {
                        $rawVal = trim($row[$colIdx] ?? '');
                        if ($rawVal === '') {
                            continue;
                        }

                        $sc = $schoolClassesMap[$clsShort] ?? null;
                        $tRecord = $scheduleTeachers->get($rawVal);

                        SchoolScheduleItem::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'day_of_week' => $dayNum,
                            'shift' => 'siang',
                            'period_order' => $periodOrder,
                            'time_range' => $waktuA,
                            'class_name' => $clsShort,
                            'school_class_id' => $sc?->id,
                            'teacher_code' => $rawVal,
                            'teacher_name' => $tRecord?->name,
                            'subject_name' => $tRecord?->subject,
                            'user_id' => $tRecord?->user_id,
                        ]);
                    }
                }
            }
        });

        // 5. Jalankan deteksi bentrok otomatis
        $conflicts = $this->detectConflicts($academicYear);

        return [
            'teachers_count' => SchoolScheduleTeacher::query()->where('academic_year_id', $academicYear->id)->count(),
            'items_count' => SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count(),
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];
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
            // Kelompokkan per kode guru
            $teacherGroups = $slotItems->groupBy(fn ($item) => strtoupper(trim((string) $item->teacher_code)));

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

        // Tentukan rombel
        $classesPagi = ['7A', '7B', '7C', '7D', '7E', '7F', '7G', '9A', '9B', '9C', '9D', '9E', '9F', '9G'];
        $classesSiang = ['8A', '8B', '8C', '8D', '8E', '8F', '8G'];

        if ($shiftMode === 'single') {
            $classesPagi = array_merge($classesPagi, $classesSiang);
            $classesSiang = [];
        }

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
            return str_replace('-', '', $sc->name); // 'VIIA' -> '7A' or similar
        });

        DB::transaction(function () use ($academicYear, $daysCount, $classesPagi, $classesSiang, $timeSlotsPagi, $timeSlotsSiang) {
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
     * Tukar jadwal antar 2 sel (Swap Slot) untuk mempermudah Wakasek mengatasi bentrok
     */
    public function swapSlots(int $itemId1, int $itemId2): bool
    {
        $item1 = SchoolScheduleItem::query()->findOrFail($itemId1);
        $item2 = SchoolScheduleItem::query()->findOrFail($itemId2);

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
     * Update isi satu sel jadwal
     */
    public function updateSlot(int $itemId, array $data): SchoolScheduleItem
    {
        $item = SchoolScheduleItem::query()->findOrFail($itemId);

        $teacherCode = trim($data['teacher_code'] ?? '');
        $teacherName = $data['teacher_name'] ?? null;
        $subjectName = $data['subject_name'] ?? null;
        $userId = $data['user_id'] ?? null;

        if ($teacherCode !== '') {
            $scheduleTeacher = SchoolScheduleTeacher::query()
                ->where('academic_year_id', $item->academic_year_id)
                ->where('code', $teacherCode)
                ->first();

            if ($scheduleTeacher) {
                $teacherName = $scheduleTeacher->name;
                $subjectName = $scheduleTeacher->subject;
                $userId = $scheduleTeacher->user_id;
            }
        }

        $item->update([
            'teacher_code' => $teacherCode ?: null,
            'teacher_name' => $teacherName,
            'subject_name' => $subjectName,
            'user_id' => $userId,
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

        $teachers = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $academicYear->id)
            ->get()
            ->keyBy('code');

        $users = User::query()->where('is_active', true)->get();
        $schoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->get();
        $subjects = Subject::query()->where('is_active', true)->get();

        $syncedCount = 0;
        $createdCount = 0;

        // Kelompokkan per (guru, rombel)
        $grouped = $items->groupBy(fn ($i) => "{$i->teacher_code}_{$i->class_name}");

        DB::transaction(function () use ($academicYear, $grouped, $teachers, $users, $schoolClasses, $subjects, &$syncedCount, &$createdCount) {
            foreach ($grouped as $key => $classItems) {
                $first = $classItems->first();
                $tRecord = $teachers->get($first->teacher_code);

                // Cari User yang cocok
                $user = null;
                if ($first->user_id) {
                    $user = $users->find($first->user_id);
                } elseif ($tRecord?->user_id) {
                    $user = $users->find($tRecord->user_id);
                } elseif ($tRecord) {
                    $cleanT = self::cleanAlphaString($tRecord->name);
                    $pureT = self::cleanAlphaString(explode(',', $tRecord->name)[0]);
                    $user = $users->first(function ($u) use ($cleanT, $pureT) {
                        $cleanU = self::cleanAlphaString($u->name);
                        $pureU = self::cleanAlphaString(explode(',', $u->name)[0]);
                        return $cleanU === $cleanT
                            || $pureU === $pureT
                            || str_contains($cleanU, $cleanT)
                            || str_contains($cleanT, $cleanU)
                            || (strlen($pureU) >= 4 && (str_contains($pureT, $pureU) || str_contains($pureU, $pureT)));
                    });
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
                    $cleanSubj = self::cleanAlphaString($subjectText);
                    $subj = $subjects->first(function ($s) use ($cleanSubj) {
                        $cleanName = self::cleanAlphaString($s->name);
                        return str_contains($cleanName, $cleanSubj) || str_contains($cleanSubj, $cleanName);
                    });
                }
                if (! $subj) {
                    $subj = $subjects->first();
                }

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
                $teachingClass = TeachingClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('teacher_id', $user->id)
                    ->where(function ($q) use ($sc, $formattedClassName) {
                        if ($sc) {
                            $q->where('school_class_id', $sc->id);
                        } else {
                            $q->where('class_name', $formattedClassName);
                        }
                    })
                    ->first();

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
     * Ambil jadwal pribadi seorang guru (untuk tab "Jadwal Saya" di Jadwal Utama)
     */
    public function getTeacherSchedule(User $user, ?AcademicYear $academicYear = null): array
    {
        $year = $academicYear ?: AcademicYear::active();
        if (! $year) {
            return [];
        }

        // 1. Cari kode guru yang sudah tertaut langsung via user_id
        $scheduleTeacher = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $year->id)
            ->where('user_id', $user->id)
            ->first();

        // 2. Jika akun adalah admin dan belum tertaut kode guru mengajar secara eksplisit:
        // Admin tidak memiliki jadwal KBM pribadi di kelas -> tampilkan 0 jam
        if ($user->isAdmin() && ! $scheduleTeacher) {
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
                    'homeroom_class' => '-',
                    'piket_day' => '-',
                ],
                'by_day' => $emptyByDay,
                'total_hours' => 0,
                'is_admin' => true,
            ];
        }

        // 3. Jika guru (non-admin) belum tertaut user_id, cocokkan via nama akun guru dengan nama di jadwal
        if (! $scheduleTeacher && ! $user->isAdmin()) {
            $allTeachers = SchoolScheduleTeacher::query()
                ->where('academic_year_id', $year->id)
                ->get();

            $cleanU = self::cleanAlphaString($user->name);
            $pureNameU = self::cleanAlphaString(explode(',', $user->name)[0]);

            if (strlen($cleanU) >= 4 && strlen($pureNameU) >= 4) {
                $scheduleTeacher = $allTeachers->first(function ($t) use ($cleanU, $pureNameU) {
                    $cleanT = self::cleanAlphaString($t->name);
                    $pureNameT = self::cleanAlphaString(explode(',', $t->name)[0]);

                    if (strlen($cleanT) < 4 || strlen($pureNameT) < 4) {
                        return false;
                    }

                    if ($cleanT === $cleanU || $pureNameT === $pureNameU) {
                        return true;
                    }

                    return (strlen($pureNameU) >= 4 && strlen($pureNameT) >= 4)
                        && ($pureNameT === $pureNameU || str_contains($pureNameT, $pureNameU) || str_contains($pureNameU, $pureNameT));
                });
            }

            // Tautkan user_id ke guru dan seluruh slot jadwal terkait secara otomatis
            if ($scheduleTeacher) {
                $scheduleTeacher->update(['user_id' => $user->id]);
                SchoolScheduleItem::query()
                    ->where('academic_year_id', $year->id)
                    ->where('teacher_code', $scheduleTeacher->code)
                    ->update(['user_id' => $user->id]);
            }
        }

        $query = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id);

        if ($scheduleTeacher) {
            $query->where('teacher_code', $scheduleTeacher->code);
        } else {
            $query->where('user_id', $user->id);
        }

        $items = $query->get()->sortBy(function ($item) {
            return ($item->day_of_week * 10000) + (int) str_replace(':', '', self::extractStartTime($item->time_range));
        })->values();

        $byDay = [];
        for ($d = 1; $d <= 6; $d++) {
            $dayItems = $items->where('day_of_week', $d)->sortBy(function ($item) {
                return self::extractStartTime($item->time_range);
            })->values();
            $byDay[$d] = [
                'day_num' => $d,
                'day_name' => self::DAYS_MAP[$d] ?? 'Hari ' . $d,
                'items' => $dayItems,
                'count' => $dayItems->count(),
            ];
        }

        return [
            'teacher' => $scheduleTeacher ?: [
                'code' => '-',
                'name' => $user->name,
                'subject' => $user->default_subject ?? '-',
                'homeroom_class' => '-',
                'piket_day' => '-',
            ],
            'by_day' => $byDay,
            'total_hours' => $items->count(),
        ];
    }

    /**
     * Ambil struktur matriks lengkap seluruh sekolah untuk render tabel
     */
    public function getMasterMatrix(AcademicYear $academicYear, ?int $dayOfWeek = null): array
    {
        $query = SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id);

        if ($dayOfWeek) {
            $query->where('day_of_week', $dayOfWeek);
        }

        $items = $query->orderBy('day_of_week')->orderBy('period_order')->get();
        $teachers = SchoolScheduleTeacher::query()->where('academic_year_id', $academicYear->id)->get()->keyBy('code');

        $days = $dayOfWeek ? [$dayOfWeek] : [1, 2, 3, 4, 5, 6];
        $matrixByDay = [];

        foreach ($days as $d) {
            $dayItems = $items->where('day_of_week', $d);

            // Shift Pagi (Kelas 7 & 9)
            $pagiItems = $dayItems->where('shift', 'pagi');
            $pagiSlots = $pagiItems->groupBy('time_range')->map(function ($slotItems) {
                $first = $slotItems->first();
                $byClass = $slotItems->keyBy('class_name');
                return [
                    'period_order' => $first->period_order,
                    'time_range' => $first->time_range,
                    'start_time' => self::extractStartTime($first->time_range),
                    'is_activity' => $first->isCommonActivity(),
                    'activity_name' => $first->isCommonActivity() ? $first->teacher_code : null,
                    'classes' => $byClass,
                ];
            })->sortBy('start_time')->values();

            // Shift Siang (Kelas 8)
            $siangItems = $dayItems->where('shift', 'siang');
            $siangSlots = $siangItems->groupBy('time_range')->map(function ($slotItems) {
                $first = $slotItems->first();
                $byClass = $slotItems->keyBy('class_name');
                return [
                    'period_order' => $first->period_order,
                    'time_range' => $first->time_range,
                    'start_time' => self::extractStartTime($first->time_range),
                    'is_activity' => $first->isCommonActivity(),
                    'activity_name' => $first->isCommonActivity() ? $first->teacher_code : null,
                    'classes' => $byClass,
                ];
            })->sortBy('start_time')->values();

            $matrixByDay[$d] = [
                'day_num' => $d,
                'day_name' => self::DAYS_MAP[$d] ?? 'Hari ' . $d,
                'pagi_slots' => $pagiSlots,
                'siang_slots' => $siangSlots,
                'conflicts_count' => $dayItems->where('is_conflict', true)->count(),
            ];
        }

        return [
            'matrix_by_day' => $matrixByDay,
            'teachers' => $teachers,
            'total_items' => $items->count(),
            'total_conflicts' => $items->where('is_conflict', true)->count(),
        ];
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

        // 1. Cari kode guru dari SchoolScheduleTeacher jika ada
        $teacherCode = null;
        if ($yearId && $teacherUser) {
            $scheduleTeacher = SchoolScheduleTeacher::query()
                ->where('academic_year_id', $yearId)
                ->where('user_id', $teacherUser->id)
                ->first();

            if (! $scheduleTeacher) {
                $cleanU = self::cleanAlphaString($teacherUser->name);
                $pureU = self::cleanAlphaString(explode(',', $teacherUser->name)[0]);

                $scheduleTeacher = SchoolScheduleTeacher::query()
                    ->where('academic_year_id', $yearId)
                    ->get()
                    ->first(function ($t) use ($cleanU, $pureU) {
                        $cleanT = self::cleanAlphaString($t->name);
                        $pureT = self::cleanAlphaString(explode(',', $t->name)[0]);
                        return $cleanT === $cleanU || $pureT === $pureU || str_contains($cleanT, $cleanU) || str_contains($cleanU, $cleanT);
                    });
            }
            $teacherCode = $scheduleTeacher?->code;
        }

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

            // Cocokkan guru
            if ($teacherUser || $teacherCode) {
                $query->where(function ($q) use ($teacherUser, $teacherCode) {
                    if ($teacherUser) {
                        $q->where('user_id', $teacherUser->id);
                    }
                    if ($teacherCode) {
                        $q->orWhere('teacher_code', $teacherCode);
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
}
