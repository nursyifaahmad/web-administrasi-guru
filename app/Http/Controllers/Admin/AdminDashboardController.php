<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\DailyJournal;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->resolveAcademicYear($request);

        $teacherFilter = $request->integer('teacher_id') ?: null;
        $subjectFilter = $request->integer('subject_id') ?: null;
        $gradeFilter = $request->string('grade_level')->trim()->toString() ?: null;
        $search = $request->string('q')->trim()->toString();

        $classesQuery = TeachingClass::query()
            ->with([
                'teacher',
                'subject.kktps',
                'academicYear',
                'studentGrades',
            ])
            ->withCount([
                'activeStudents as students_count',
                'dailyJournals as journals_count',
                'phColumns as ph_count',
                'ntColumns as nt_count',
            ])
            ->where('academic_year_id', $academicYear->id);

        if ($teacherFilter) {
            $classesQuery->where('teacher_id', $teacherFilter);
        }
        if ($subjectFilter) {
            $classesQuery->where('subject_id', $subjectFilter);
        }
        if ($gradeFilter) {
            $classesQuery->where('grade_level', $gradeFilter);
        }
        if ($search !== '') {
            $classesQuery->where(function ($q) use ($search) {
                $q->where('class_name', 'like', "%{$search}%")
                    ->orWhere('homeroom_teacher_name', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $classes = $classesQuery
            ->orderBy('grade_level')
            ->orderBy('class_name')
            ->get();

        $classIds = $classes->pluck('id');

        $attendanceDaysByClass = Attendance::query()
            ->whereIn('teaching_class_id', $classIds)
            ->selectRaw('teaching_class_id, COUNT(DISTINCT attendance_date) as active_days')
            ->groupBy('teaching_class_id')
            ->pluck('active_days', 'teaching_class_id');

        $lastJournalByClass = DailyJournal::query()
            ->whereIn('teaching_class_id', $classIds)
            ->selectRaw('teaching_class_id, MAX(journal_date) as last_date')
            ->groupBy('teaching_class_id')
            ->pluck('last_date', 'teaching_class_id');

        $monitoringRows = [];
        $totalGradedStudents = 0;
        $totalTuntasStudents = 0;

        foreach ($classes as $cls) {
            $kktp = $cls->effective_kktp;
            $grades = $cls->studentGrades;
            $gradedCount = $grades->count();
            $tuntasCount = $grades->filter(fn ($g) => (float) $g->final_score >= $kktp)->count();
            $avgNa = $gradedCount > 0 ? round($grades->avg('final_score'), 2) : 0.0;

            $totalGradedStudents += $gradedCount;
            $totalTuntasStudents += $tuntasCount;

            $monitoringRows[] = [
                'class' => $cls,
                'kktp' => $kktp,
                'attendance_days' => (int) ($attendanceDaysByClass[$cls->id] ?? 0),
                'last_journal_date' => $lastJournalByClass[$cls->id] ?? null,
                'graded_count' => $gradedCount,
                'tuntas_count' => $tuntasCount,
                'tuntas_rate' => $gradedCount > 0 ? round(($tuntasCount / $gradedCount) * 100, 1) : 0.0,
                'avg_na' => $avgNa,
            ];
        }

        $teachers = User::query()
            ->where('role', 'guru')
            ->withCount(['teachingClasses as active_classes_count' => fn ($q) => $q->where('academic_year_id', $academicYear->id)])
            ->orderBy('name')
            ->get();

        $kpis = [
            'total_teachers' => $teachers->where('is_active', true)->count(),
            'total_classes' => $classes->count(),
            'total_journals' => $classes->sum('journals_count'),
            'total_attendance_days' => $attendanceDaysByClass->sum(),
            'overall_tuntas_rate' => $totalGradedStudents > 0
                ? round(($totalTuntasStudents / $totalGradedStudents) * 100, 1)
                : 0.0,
        ];

        return view('admin.dashboard', [
            'kpis' => $kpis,
            'monitoringRows' => $monitoringRows,
            'teachers' => $teachers,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'filters' => [
                'teacher_id' => $teacherFilter,
                'subject_id' => $subjectFilter,
                'grade_level' => $gradeFilter,
                'q' => $search,
            ],
        ]);
    }
}
