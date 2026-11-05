<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\TeachingClass;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Akun Anda sedang dinonaktifkan oleh Administrator.',
            ]);
        }

        $schoolSetting = SchoolSetting::current();
        $allAcademicYears = AcademicYear::query()
            ->orderByDesc('year_name')
            ->orderBy('semester')
            ->get();

        $selectedYearId = $request->session()->get('selected_academic_year_id');
        $selectedAcademicYear = ($selectedYearId ? $allAcademicYears->firstWhere('id', (int) $selectedYearId) : null)
            ?? $allAcademicYears->firstWhere('is_active', true)
            ?? AcademicYear::active();

        $quickSwitcherClasses = collect();
        $totalSchoolClassesCount = 0;

        if ($user) {
            // Quick switcher hanya memuat kelas yang diampu oleh pengguna (guru atau admin yang mengajar)
            $quickSwitcherClasses = TeachingClass::query()
                ->with(['subject', 'teacher'])
                ->where('academic_year_id', $selectedAcademicYear->id)
                ->where('teacher_id', $user->id)
                ->orderBy('grade_level')
                ->orderBy('class_name')
                ->get();

            if ($user->isAdmin()) {
                $totalSchoolClassesCount = TeachingClass::query()
                    ->where('academic_year_id', $selectedAcademicYear->id)
                    ->count();
            }
        }

        $request->attributes->set('selectedAcademicYear', $selectedAcademicYear);
        $request->attributes->set('schoolSetting', $schoolSetting);

        View::share('globalSchool', $schoolSetting);
        View::share('globalAcademicYears', $allAcademicYears);
        View::share('selectedAcademicYear', $selectedAcademicYear);
        View::share('quickSwitcherClasses', $quickSwitcherClasses);
        View::share('totalSchoolClassesCount', $totalSchoolClassesCount);

        return $next($request);
    }
}
