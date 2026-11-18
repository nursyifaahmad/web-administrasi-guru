<?php

namespace App\Http\Controllers;

use App\Models\ClassStudent;
use App\Models\SchoolSetting;
use App\Models\TeachingClass;
use App\Services\StudentGradeShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentGradePortalController extends Controller
{
    public function show(Request $request, string $slug, StudentGradeShareService $shareService): Response
    {
        $teachingClass = $this->findClassBySlug($slug);
        $school = SchoolSetting::current();

        if (! $teachingClass) {
            return $this->privacyResponse('public.student-grades-closed', [
                'school' => $school,
                'reason' => 'not_found',
                'teachingClass' => null,
            ], 404);
        }

        if (! (bool) $teachingClass->is_grade_shared_with_students) {
            $request->session()->forget($this->sessionKeys($teachingClass->id));

            return $this->privacyResponse('public.student-grades-closed', [
                'school' => $school,
                'reason' => 'disabled',
                'teachingClass' => $teachingClass,
            ], 403);
        }

        $report = null;
        $studentId = $request->session()->get($this->studentSessionKey($teachingClass->id));

        if ($studentId) {
            $student = ClassStudent::query()
                ->where('id', (int) $studentId)
                ->where('school_class_id', $teachingClass->school_class_id)
                ->where('is_active', true)
                ->first();

            if ($student) {
                $report = $shareService->buildSingleStudentReport($teachingClass, $student);
            }

            if (! $report) {
                $request->session()->forget($this->sessionKeys($teachingClass->id));
            }
        }

        return $this->privacyResponse('public.student-grades', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'slug' => $slug,
            'report' => $report,
            'verifiedAt' => $request->session()->get($this->timeSessionKey($teachingClass->id)),
            'antiScreenshotEnabled' => (bool) ($teachingClass->is_student_share_anti_screenshot ?? true),
        ]);
    }

    public function verify(
        Request $request,
        string $slug,
        StudentGradeShareService $shareService
    ): Response|RedirectResponse {
        $teachingClass = $this->findClassBySlug($slug);
        $school = SchoolSetting::current();

        if (! $teachingClass) {
            return $this->privacyResponse('public.student-grades-closed', [
                'school' => $school,
                'reason' => 'not_found',
                'teachingClass' => null,
            ], 404);
        }

        if (! (bool) $teachingClass->is_grade_shared_with_students) {
            return $this->privacyResponse('public.student-grades-closed', [
                'school' => $school,
                'reason' => 'disabled',
                'teachingClass' => $teachingClass,
            ], 403);
        }

        $validated = $request->validate([
            'nis_nisn' => ['required', 'string', 'max:40'],
        ], [
            'nis_nisn.required' => 'Masukkan NIS atau NISN Anda terlebih dahulu.',
        ]);

        $lookup = $shareService->findActiveStudentByNisNisn($teachingClass, $validated['nis_nisn']);

        if ($lookup['status'] === 'ambiguous') {
            return back()
                ->withErrors(['nis_nisn' => 'Data NIS/NISN terdeteksi ganda pada kelas ini. Hubungi guru pengampu atau Administrator untuk merapikan daftar peserta didik.'])
                ->withInput();
        }

        if ($lookup['status'] !== 'matched' || ! $lookup['student']) {
            return back()
                ->withErrors(['nis_nisn' => 'NIS / NISN tidak ditemukan pada daftar peserta didik aktif kelas ini. Periksa kembali angka Anda atau hubungi guru pengampu.'])
                ->withInput();
        }

        $request->session()->put($this->studentSessionKey($teachingClass->id), (int) $lookup['student']->id);
        $request->session()->put($this->timeSessionKey($teachingClass->id), now()->format('d/m/Y H:i:s'));

        return redirect()->route('student-grades.show', $slug);
    }

    public function logoutStudent(Request $request, string $slug): RedirectResponse
    {
        $teachingClass = $this->findClassBySlug($slug);
        if ($teachingClass) {
            $request->session()->forget($this->sessionKeys($teachingClass->id));
        }

        return redirect()
            ->route('student-grades.show', $slug)
            ->with('status', 'Tampilan nilai telah ditutup. Masukkan kembali NIS/NISN bila ingin memeriksa ulang.');
    }

    protected function findClassBySlug(string $slug): ?TeachingClass
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '') {
            return null;
        }

        return TeachingClass::query()
            ->with(['subject.kktps', 'academicYear', 'teacher'])
            ->where('student_share_slug', $cleanSlug)
            ->first();
    }

    protected function studentSessionKey(int $teachingClassId): string
    {
        return "student_grade_portal.{$teachingClassId}";
    }

    protected function timeSessionKey(int $teachingClassId): string
    {
        return "student_grade_portal_time.{$teachingClassId}";
    }

    /**
     * @return list<string>
     */
    protected function sessionKeys(int $teachingClassId): array
    {
        return [
            $this->studentSessionKey($teachingClassId),
            $this->timeSessionKey($teachingClassId),
        ];
    }

    protected function privacyResponse(string $view, array $data, int $status = 200): HttpResponse
    {
        return response()
            ->view($view, $data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
