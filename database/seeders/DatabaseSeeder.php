<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\SubjectKktp;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\AttendanceJournalSyncService;
use App\Services\GradeCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'gov_header' => 'PEMERINTAH KABUPATEN KARAWANG',
                'department_header' => 'DINAS PENDIDIKAN, PEMUDA DAN OLAHRAGA',
                'school_name' => 'SMP NEGERI 2 LEMAHABANG',
                'npsn' => '20214811',
                'nss' => '201022114042',
                'address' => 'Jl. Raya Lemahabang, Desa Lemahabang, Kec. Lemahabang, Kab. Karawang',
                'postal_code' => '41383',
                'phone' => '(0267) 512345',
                'email' => 'info@smpn2lemahabang.sch.id',
                'website' => 'https://smpn2lemahabang.sch.id',
                'default_city' => 'Lemahabang',
                'headmaster_name' => 'Teti Kusmiyati, S.Pd., M.M.',
                'headmaster_nip' => '19720514 199802 2 003',
                'global_default_kktp' => 75,
                'available_grade_levels' => ['VII', 'VIII', 'IX'],
            ]
        );

        AcademicYear::query()->updateOrCreate(
            ['year_name' => '2025/2026', 'semester' => 'Genap'],
            ['is_active' => false, 'start_date' => '2026-01-05', 'end_date' => '2026-06-20']
        );

        $activeYear = AcademicYear::query()->updateOrCreate(
            ['year_name' => '2026/2027', 'semester' => 'Ganjil'],
            ['is_active' => true, 'start_date' => '2026-07-13', 'end_date' => '2026-12-19']
        );

        AcademicYear::query()->updateOrCreate(
            ['year_name' => '2026/2027', 'semester' => 'Genap'],
            ['is_active' => false, 'start_date' => '2027-01-04', 'end_date' => '2027-06-26']
        );

        $subjectsCatalog = [
            ['code' => 'MTK', 'name' => 'Matematika', 'default' => 75, 'grades' => ['VII' => 75, 'VIII' => 76, 'IX' => 78]],
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default' => 76, 'grades' => ['VII' => 76, 'VIII' => 78, 'IX' => 80]],
            ['code' => 'BIG', 'name' => 'Bahasa Inggris', 'default' => 75, 'grades' => ['VII' => 75, 'VIII' => 76, 'IX' => 78]],
            ['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam', 'default' => 75, 'grades' => ['VII' => 75, 'VIII' => 77, 'IX' => 78]],
            ['code' => 'IPS', 'name' => 'Ilmu Pengetahuan Sosial', 'default' => 76, 'grades' => ['VII' => 76, 'VIII' => 77, 'IX' => 78]],
            ['code' => 'PKN', 'name' => 'Pendidikan Pancasila', 'default' => 78, 'grades' => ['VII' => 78, 'VIII' => 78, 'IX' => 80]],
            ['code' => 'PAI', 'name' => 'Pendidikan Agama Islam & Budi Pekerti', 'default' => 78, 'grades' => ['VII' => 78, 'VIII' => 80, 'IX' => 80]],
            ['code' => 'INF', 'name' => 'Informatika', 'default' => 76, 'grades' => ['VII' => 76, 'VIII' => 77, 'IX' => 78]],
            ['code' => 'PJK', 'name' => 'PJOK', 'default' => 78, 'grades' => ['VII' => 78, 'VIII' => 78, 'IX' => 80]],
            ['code' => 'SBD', 'name' => 'Seni Budaya & Prakarya', 'default' => 78, 'grades' => ['VII' => 78, 'VIII' => 78, 'IX' => 80]],
        ];

        $subjectMap = [];
        foreach ($subjectsCatalog as $item) {
            $subject = Subject::query()->updateOrCreate(
                ['code' => $item['code']],
                ['name' => $item['name'], 'default_kktp' => $item['default'], 'is_active' => true]
            );
            foreach ($item['grades'] as $grade => $val) {
                SubjectKktp::query()->updateOrCreate(
                    ['subject_id' => $subject->id, 'grade_level' => $grade],
                    ['kktp_value' => $val]
                );
            }
            $subjectMap[$item['code']] = $subject;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@sekolah.sch.id'],
            [
                'name' => 'Drs. Hendra Wijaya, M.M.Pd.',
                'nip' => '19800315 200604 1 004',
                'role' => 'admin',
                'phone' => '081210002000',
                'default_subject' => 'Wakasek Kurikulum',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        $guru1 = User::query()->updateOrCreate(
            ['email' => 'guru@sekolah.sch.id'],
            [
                'name' => 'Budi Santoso, S.Pd., Gr.',
                'nip' => '19900412 201903 1 008',
                'role' => 'guru',
                'phone' => '081322334455',
                'default_subject' => 'Matematika',
                'is_active' => true,
                'password' => Hash::make('guru123'),
            ]
        );

        if (isset($subjectMap['MTK'])) {
            \App\Models\TeacherSubject::query()->updateOrCreate(
                ['user_id' => $guru1->id, 'subject_id' => $subjectMap['MTK']->id],
                ['teaching_code' => '7']
            );
        }

        $guru2 = User::query()->updateOrCreate(
            ['email' => 'siti@sekolah.sch.id'],
            [
                'name' => 'Siti Nurhaliza, M.Pd.',
                'nip' => '19880721 201504 2 005',
                'role' => 'guru',
                'phone' => '081388776655',
                'default_subject' => 'Bahasa Indonesia',
                'is_active' => true,
                'password' => Hash::make('guru123'),
            ]
        );

        if (isset($subjectMap['BIN'])) {
            \App\Models\TeacherSubject::query()->updateOrCreate(
                ['user_id' => $guru2->id, 'subject_id' => $subjectMap['BIN']->id],
                ['teaching_code' => '4']
            );
        }

        $irfan = User::query()->updateOrCreate(
            ['email' => 'muhamad.irfan@smpn2lemahabang.sch.id'],
            [
                'name' => 'Muhammad Irfan, S.Pd.',
                'role' => 'admin',
                'phone' => '081377889900',
                'default_subject' => 'Bahasa Inggris',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        if (isset($subjectMap['BIG'])) {
            \App\Models\TeacherSubject::query()->updateOrCreate(
                ['user_id' => $irfan->id, 'subject_id' => $subjectMap['BIG']->id],
                ['teaching_code' => '12']
            );
        }

        $sampleStudents = [
            ['nis' => '009812001', 'full' => 'Aditya Pratama Putra', 'g' => 'L'],
            ['nis' => '009812002', 'full' => 'Aisyah Putri Ramadhani', 'g' => 'P'],
            ['nis' => '009812003', 'full' => 'Bagas Dwi Saputra', 'g' => 'L'],
            ['nis' => '009812004', 'full' => 'Citra Lestari Ningsih', 'g' => 'P'],
            ['nis' => '009812005', 'full' => 'Dimas Arya Nugraha', 'g' => 'L'],
            ['nis' => '009812006', 'full' => 'Fajar Siddiq Maulana', 'g' => 'L'],
            ['nis' => '009812007', 'full' => 'Gita Permata Sari', 'g' => 'P'],
            ['nis' => '009812008', 'full' => 'Hafizurahman Alfarizi', 'g' => 'L'],
            ['nis' => '009812009', 'full' => 'Indah Cahyani Putri', 'g' => 'P'],
            ['nis' => '009812010', 'full' => 'Kevin Julio Firmansyah', 'g' => 'L'],
            ['nis' => '009812011', 'full' => 'Laila Nur Azizah', 'g' => 'P'],
            ['nis' => '009812012', 'full' => 'Muhammad Rizky Fadillah', 'g' => 'L'],
            ['nis' => '009812013', 'full' => 'Nabila Syifa Azzahra', 'g' => 'P'],
            ['nis' => '009812014', 'full' => 'Putri Ayu Wandira', 'g' => 'P'],
            ['nis' => '009812015', 'full' => 'Rafi Ahmad Fauzan', 'g' => 'L'],
            ['nis' => '009812016', 'full' => 'Salma Khairunnisa', 'g' => 'P'],
            ['nis' => '009812017', 'full' => 'Taufik Hidayatullah', 'g' => 'L'],
            ['nis' => '009812018', 'full' => 'Vina Melati Sukma', 'g' => 'P'],
            ['nis' => '009812019', 'full' => 'Yoga Pratama Wijaya', 'g' => 'L'],
            ['nis' => '009812020', 'full' => 'Zahra Aulia Rahma', 'g' => 'P'],
        ];

        $schoolClassesList = [
            ['grade' => 'VII', 'name' => 'VII-A', 'schedule' => 'Senin (1-2) & Kamis (3-4)', 'wk_name' => 'Dra. Ratna Sari Dewi', 'wk_nip' => '19750618 200501 2 007'],
            ['grade' => 'VII', 'name' => 'VII-B', 'schedule' => 'Senin (3-4) & Kamis (5-6)', 'wk_name' => 'Agus Setiawan, S.Pd.', 'wk_nip' => '19840911 201101 1 003'],
            ['grade' => 'VIII', 'name' => 'VIII-A', 'schedule' => 'Selasa (1-2) & Jumat (1-2)', 'wk_name' => 'Rina Marlina, S.Pd.', 'wk_nip' => '19871203 201402 2 002'],
            ['grade' => 'VIII', 'name' => 'VIII-B', 'schedule' => 'Selasa (3-4) & Jumat (3-4)', 'wk_name' => 'Dedi Kurniawan, S.Kom.', 'wk_nip' => '19910522 202012 1 006'],
            ['grade' => 'IX', 'name' => 'IX-A', 'schedule' => 'Rabu (1-3) & Sabtu (1-2)', 'wk_name' => 'Hj. Nenden Hasanah, M.Pd.', 'wk_nip' => '19731108 199903 2 004'],
        ];

        // 1. Buat Master Rombel & Master Peserta Didik Terpusat oleh Admin
        $schoolClassMap = [];
        foreach ($schoolClassesList as $cIdx => $cfg) {
            $sc = SchoolClass::query()->updateOrCreate(
                [
                    'academic_year_id' => $activeYear->id,
                    'name' => $cfg['name'],
                ],
                [
                    'grade_level' => $cfg['grade'],
                    'homeroom_teacher_name' => $cfg['wk_name'],
                    'homeroom_teacher_nip' => $cfg['wk_nip'],
                ]
            );

            foreach ($sampleStudents as $sIdx => $st) {
                ClassStudent::query()->updateOrCreate(
                    [
                        'school_class_id' => $sc->id,
                        'order_number' => $sIdx + 1,
                    ],
                    [
                        'nis_nisn' => substr($st['nis'], 0, 6) . sprintf('%d%02d', $cIdx + 1, $sIdx + 1),
                        'full_name' => $st['full'],
                        'gender' => $st['g'],
                        'is_active' => true,
                    ]
                );
            }

            $schoolClassMap[$cfg['name']] = $sc;
        }

        $syncService = app(AttendanceJournalSyncService::class);
        $gradeService = app(GradeCalculationService::class);

        // 2. Buat Kelas Mengajar Guru 1 (Matematika) yang terhubung ke SchoolClass
        foreach ($schoolClassesList as $cIdx => $cfg) {
            $sc = $schoolClassMap[$cfg['name']];

            $tClass = TeachingClass::query()->updateOrCreate(
                [
                    'teacher_id' => $guru1->id,
                    'academic_year_id' => $activeYear->id,
                    'subject_id' => $subjectMap['MTK']->id,
                    'class_name' => $cfg['name'],
                ],
                [
                    'school_class_id' => $sc->id,
                    'grade_level' => $cfg['grade'],
                    'schedule_info' => $cfg['schedule'],
                    'homeroom_teacher_name' => $cfg['wk_name'],
                    'homeroom_teacher_nip' => $cfg['wk_nip'],
                ]
            );

            $tClass->gradeColumns()->delete();
            $phCols = collect([
                $tClass->gradeColumns()->create(['type' => 'PH', 'label' => 'PH 1', 'topic_note' => 'Bilangan & Aljabar', 'sort_order' => 1]),
                $tClass->gradeColumns()->create(['type' => 'PH', 'label' => 'PH 2', 'topic_note' => 'Persamaan Linear', 'sort_order' => 2]),
                $tClass->gradeColumns()->create(['type' => 'PH', 'label' => 'PH 3', 'topic_note' => 'Keaktifan & Karakter', 'sort_order' => 3]),
            ]);
            $ntCols = collect([
                $tClass->gradeColumns()->create(['type' => 'NT', 'label' => 'NT 1', 'topic_note' => 'LKPD Kelompok', 'sort_order' => 1]),
                $tClass->gradeColumns()->create(['type' => 'NT', 'label' => 'NT 2', 'topic_note' => 'Proyek Mandiri', 'sort_order' => 2]),
            ]);

            $tClass->gradeWeight()->updateOrCreate(
                ['teaching_class_id' => $tClass->id],
                [
                    'weight_ph' => 25,
                    'weight_attendance' => 10,
                    'weight_nt' => 20,
                    'weight_sts' => 20,
                    'weight_sas' => 25,
                    'rounding_precision' => 2,
                    'agreement_note' => 'Kontrak Belajar Semester Ganjil',
                ]
            );

            $sharedStudents = $sc->students()->get();

            if ($cIdx < 3) {
                $meetingDates = [
                    ['date' => '2026-10-05', 'hours' => '1 - 2', 'material' => 'Konsep Operasi Bilangan Bulat & Pecahan dalam Masalah Kontekstual', 'notes' => 'Diskusi kelompok berjalan aktif, 1 siswa dispen lomba OSN.'],
                    ['date' => '2026-10-12', 'hours' => '1 - 2', 'material' => 'Bentuk Aljabar dan Penyederhanaan Suku Sejenis', 'notes' => 'Pemberian latihan terbimbing di papan tulis.'],
                    ['date' => '2026-10-19', 'hours' => '1 - 2', 'material' => 'Penilaian Harian 1 (PH 1) & Refleksi Materi', 'notes' => 'Pelaksanaan PH 1 tertib dan kondusif.'],
                    ['date' => '2026-11-02', 'hours' => '1 - 2', 'material' => 'Persamaan Linear Satu Variabel (PLSV)', 'notes' => 'Presentasi hasil kerja kelompok di depan kelas.'],
                ];

                foreach ($meetingDates as $mIdx => $m) {
                    foreach ($sharedStudents as $sIdx => $student) {
                        $status = '.';
                        if ($mIdx === 0 && $sIdx === 5) {
                            $status = 'd'; // Dispen (tetap dihitung hadir!)
                        } elseif ($mIdx === 0 && $sIdx === 11) {
                            $status = 's';
                        } elseif ($mIdx === 1 && $sIdx === 3) {
                            $status = 'i';
                        } elseif ($mIdx === 2 && $sIdx === 16) {
                            $status = 'a';
                        } elseif ($mIdx === 3 && $sIdx === 7) {
                            $status = 'd';
                        } elseif ($mIdx === 3 && $sIdx === 18) {
                            $status = 's';
                        }

                        Attendance::query()->updateOrCreate(
                            [
                                'teaching_class_id' => $tClass->id,
                                'class_student_id' => $student->id,
                                'attendance_date' => $m['date'],
                            ],
                            ['status' => $status]
                        );
                    }

                    DailyJournal::query()->updateOrCreate(
                        [
                            'teaching_class_id' => $tClass->id,
                            'journal_date' => $m['date'],
                            'lesson_hours' => $m['hours'],
                        ],
                        [
                            'activity_material' => $m['material'],
                            'notes' => $m['notes'],
                            'is_attendance_overridden' => false,
                        ]
                    );

                    $syncService->syncJournalForDate($tClass, $m['date']);
                }

                foreach ($sharedStudents as $sIdx => $student) {
                    $base = 72 + (($sIdx * 7 + $cIdx * 3) % 24);
                    // Buat 2-3 siswa bernilai di bawah KKTP agar indikator remedial terlihat jelas
                    if (in_array($sIdx, [4, 16], true)) {
                        $base = 64 + ($sIdx % 5);
                    }

                    $dyn = [];
                    foreach ($phCols as $pIdx => $col) {
                        $dyn[(string) $col->id] = min(100, max(50, $base + ($pIdx * 2 - 2)));
                    }
                    foreach ($ntCols as $nIdx => $col) {
                        $dyn[(string) $col->id] = min(100, max(55, $base + ($nIdx * 3)));
                    }

                    StudentGrade::query()->updateOrCreate(
                        [
                            'teaching_class_id' => $tClass->id,
                            'class_student_id' => $student->id,
                        ],
                        [
                            'attendance_score' => in_array($sIdx, [16], true) ? 80 : 95,
                            'sts_score' => min(100, max(50, $base - 1)),
                            'sas_score' => min(100, max(50, $base + 2)),
                            'dynamic_scores' => $dyn,
                        ]
                    );
                }

                $gradeService->recalculateClass($tClass);
            }
        }

        // 3. Kelas contoh untuk Guru 2 (Bahasa Indonesia) pada rombel VII-A yang SAMA
        $sc7A = $schoolClassMap['VII-A'];
        $g2Class = TeachingClass::query()->updateOrCreate(
            [
                'teacher_id' => $guru2->id,
                'academic_year_id' => $activeYear->id,
                'subject_id' => $subjectMap['BIN']->id,
                'class_name' => 'VII-A',
            ],
            [
                'school_class_id' => $sc7A->id,
                'grade_level' => 'VII',
                'schedule_info' => 'Selasa (1-3) & Kamis (1-3)',
                'homeroom_teacher_name' => $sc7A->homeroom_teacher_name,
                'homeroom_teacher_nip' => $sc7A->homeroom_teacher_nip,
            ]
        );
        $g2Class->ensureDefaultsInitialized();
    }
}
