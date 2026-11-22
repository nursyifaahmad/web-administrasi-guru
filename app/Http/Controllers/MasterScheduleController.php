<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Services\SchoolScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterScheduleController extends Controller
{
    public function __construct(
        protected SchoolScheduleService $scheduleService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        if (! $academicYear) {
            $academicYear = AcademicYear::query()->first();
        }

        // Jika data jadwal dan master kode guru masih kosong, muat template resmi Wakasek secara otomatis
        if ($academicYear) {
            $itemCount = \App\Models\SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count();
            $hasCustomCodes = \App\Models\TeacherSubject::query()->whereNotNull('teaching_code_normalized')->exists();
            if ($itemCount === 0 && ! $hasCustomCodes) {
                $this->scheduleService->importFromWakasekCsv($academicYear);
                $this->scheduleService->syncToTeachingClasses($academicYear);
            }
        }

        $defaultTab = $user->isAdmin() ? 'school_matrix' : 'my_schedule';
        $activeTab = $request->query('tab', $defaultTab); // 'my_schedule', 'school_matrix', 'piket'
        $activeDay = (int) ($request->query('day') ?: 1);
        if ($activeDay < 1 || $activeDay > 6) {
            $activeDay = 1;
        }

        // Ambil jadwal pribadi guru
        $mySchedule = $this->scheduleService->getTeacherSchedule($user, $academicYear);

        // Ambil matriks seluruh sekolah
        $matrixData = $this->scheduleService->getMasterMatrix($academicYear, $activeDay);

        // Ambil daftar Wali Kelas, Kode Guru, dan Guru Piket langsung dari Master Data Tunggal
        $homeroomList = $academicYear ? $this->scheduleService->getHomeroomList($academicYear) : collect();
        $allTeachers = $academicYear ? $this->scheduleService->getMasterTeacherDirectory($academicYear) : collect();
        $piketGrouped = $this->scheduleService->getPiketGroupedByDay();

        $today = Carbon::now();
        $todayDayOfWeek = (int) $today->dayOfWeek; // 1 = Senin..6 = Sabtu

        $school = SchoolSetting::current();

        return view('workspace.schedule.master', [
            'user' => $user,
            'academicYear' => $academicYear,
            'activeTab' => $activeTab,
            'activeDay' => $activeDay,
            'mySchedule' => $mySchedule,
            'matrixData' => $matrixData,
            'allTeachers' => $allTeachers,
            'piketGrouped' => $piketGrouped,
            'homeroomList' => $homeroomList,
            'todayDayOfWeek' => $todayDayOfWeek,
            'school' => $school,
        ]);
    }
}
