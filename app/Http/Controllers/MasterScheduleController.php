<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolScheduleTeacher;
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

        $activeTab = $request->query('tab', 'my_schedule'); // 'my_schedule', 'school_matrix', 'piket'
        $activeDay = (int) ($request->query('day') ?: 1);
        if ($activeDay < 1 || $activeDay > 6) {
            $activeDay = 1;
        }

        // Ambil jadwal pribadi guru
        $mySchedule = $this->scheduleService->getTeacherSchedule($user, $academicYear);

        // Ambil matriks seluruh sekolah
        $matrixData = $this->scheduleService->getMasterMatrix($academicYear, $activeDay);

        // Ambil daftar guru piket & wali kelas
        $allTeachers = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('code')
            ->get();

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
            'todayDayOfWeek' => $todayDayOfWeek,
            'school' => $school,
        ]);
    }
}
