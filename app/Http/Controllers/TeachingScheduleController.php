<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\TeachingClass;
use App\Models\User;
use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeachingScheduleController extends Controller
{
    /**
     * Menampilkan Jadwal Mengajar Mingguan (Senin–Sabtu) untuk guru.
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user();

        // Jika admin, izinkan melihat jadwal guru lain via parameter ?teacher_id=
        $targetTeacherId = $currentUser->isAdmin() && $request->filled('teacher_id')
            ? (int) $request->input('teacher_id')
            : $currentUser->id;

        $targetTeacher = User::query()->findOrFail($targetTeacherId);

        // Ambil tahun ajaran aktif dari sesi atau default aktif
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : null;

        if (! $academicYear) {
            $academicYear = AcademicYear::active();
        }

        // Ambil seluruh kelas aktif milik guru tersebut pada tahun ajaran aktif
        $classesQuery = TeachingClass::query()
            ->with(['subject', 'schoolClass', 'activeStudents'])
            ->where('teacher_id', $targetTeacher->id)
            ->where('is_archived', false);

        if ($academicYear) {
            $classesQuery->where('academic_year_id', $academicYear->id);
        }

        $classes = $classesQuery->orderBy('class_name')->get();

        // Pemetaan hari Senin s/d Sabtu
        $daysMap = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        // Kelompokkan kelas berdasarkan hari KBM (hanya untuk kelas yang sudah diatur hari mengajarnya)
        $scheduledByDay = [];
        foreach ($daysMap as $dayNum => $dayName) {
            $scheduledByDay[$dayNum] = $classes->filter(function (TeachingClass $cls) use ($dayNum) {
                if (empty($cls->teaching_days) || ! is_array($cls->teaching_days)) {
                    return false;
                }

                return in_array($dayNum, array_map('intval', $cls->teaching_days), true);
            })->values();
        }

        // Deteksi kelas yang belum diatur jadwal hari mengajarnya
        $unscheduledClasses = $classes->filter(function (TeachingClass $cls) {
            return empty($cls->teaching_days) || ! is_array($cls->teaching_days);
        })->values();

        // Deteksi KBM Hari Ini
        $today = Carbon::now();
        $todayDayOfWeek = (int) $today->dayOfWeek; // 0 = Minggu, 1 = Senin, ..., 6 = Sabtu
        $todayDayName = IndonesianDate::DAYS[$todayDayOfWeek] ?? 'Minggu';
        $todayClasses = ($todayDayOfWeek >= 1 && $todayDayOfWeek <= 6)
            ? ($scheduledByDay[$todayDayOfWeek] ?? collect())
            : collect();

        // Statistik ringkas mingguan
        $activeDaysCount = collect($scheduledByDay)->filter(fn ($dayClasses) => $dayClasses->isNotEmpty())->count();
        $totalStudents = $classes->sum(fn ($cls) => $cls->activeStudents->count());

        // Jika admin, siapkan daftar guru untuk dropdown pemilih
        $allTeachers = $currentUser->isAdmin()
            ? User::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $school = SchoolSetting::current();

        return view('workspace.schedule.index', [
            'teacher' => $targetTeacher,
            'academicYear' => $academicYear,
            'classes' => $classes,
            'scheduledByDay' => $scheduledByDay,
            'daysMap' => $daysMap,
            'today' => $today,
            'todayDayOfWeek' => $todayDayOfWeek,
            'todayDayName' => $todayDayName,
            'todayClasses' => $todayClasses,
            'unscheduledClasses' => $unscheduledClasses,
            'activeDaysCount' => $activeDaysCount,
            'totalStudents' => $totalStudents,
            'allTeachers' => $allTeachers,
            'school' => $school,
        ]);
    }
}
