<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\SubjectKktp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KktpSubjectController extends Controller
{
    public function index(): View
    {
        $school = SchoolSetting::current();
        $gradeLevels = $school->gradeLevels();
        $subjects = Subject::query()
            ->with('kktps')
            ->withCount('teachingClasses')
            ->orderBy('name')
            ->get();

        return view('admin.kktp', [
            'school' => $school,
            'gradeLevels' => $gradeLevels,
            'subjects' => $subjects,
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $school = SchoolSetting::current();
        $gradeLevels = $school->gradeLevels();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:25', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:150'],
            'default_kktp' => ['required', 'integer', 'min:0', 'max:100'],
            'grade_kktp' => ['nullable', 'array'],
            'grade_kktp.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $gradeLevels) {
            $subject = Subject::query()->create([
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'default_kktp' => (int) $validated['default_kktp'],
                'is_active' => true,
            ]);

            foreach ($gradeLevels as $level) {
                $val = $validated['grade_kktp'][$level] ?? $validated['default_kktp'];
                SubjectKktp::query()->updateOrCreate(
                    ['subject_id' => $subject->id, 'grade_level' => $level],
                    ['kktp_value' => (int) $val]
                );
            }
        });

        return back()->with('success', "Mata pelajaran {$validated['name']} beserta standar KKTP berhasil ditambahkan.");
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'global_default_kktp' => ['required', 'integer', 'min:0', 'max:100'],
            'grade_levels_csv' => ['required', 'string', 'max:150'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.name' => ['required', 'string', 'max:150'],
            'subjects.*.code' => ['required', 'string', 'max:25'],
            'subjects.*.default_kktp' => ['required', 'integer', 'min:0', 'max:100'],
            'subjects.*.kktps' => ['nullable', 'array'],
            'subjects.*.kktps.*' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $parsedLevels = collect(explode(',', $validated['grade_levels_csv']))
            ->map(fn ($v) => strtoupper(trim($v)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($parsedLevels)) {
            $parsedLevels = ['VII', 'VIII', 'IX'];
        }

        DB::transaction(function () use ($validated, $parsedLevels) {
            $school = SchoolSetting::current();
            $school->update([
                'global_default_kktp' => (int) $validated['global_default_kktp'],
                'available_grade_levels' => $parsedLevels,
            ]);

            foreach ($validated['subjects'] ?? [] as $subjectId => $data) {
                $subject = Subject::query()->find($subjectId);
                if (! $subject) {
                    continue;
                }

                $codeExists = Subject::query()
                    ->where('code', strtoupper(trim($data['code'])))
                    ->where('id', '!=', $subject->id)
                    ->exists();

                $subject->update([
                    'name' => trim($data['name']),
                    'code' => $codeExists ? $subject->code : strtoupper(trim($data['code'])),
                    'default_kktp' => (int) $data['default_kktp'],
                ]);

                foreach ($parsedLevels as $lvl) {
                    $kVal = $data['kktps'][$lvl] ?? $data['default_kktp'];
                    SubjectKktp::query()->updateOrCreate(
                        ['subject_id' => $subject->id, 'grade_level' => $lvl],
                        ['kktp_value' => (int) $kVal]
                    );
                }
            }
        });

        return back()->with('success', 'Standar KKM / KKTP seluruh Mata Pelajaran & Tingkat Kelas berhasil diperbarui.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        if ($subject->teachingClasses()->exists()) {
            return back()->withErrors([
                'subject' => "Mata pelajaran {$subject->name} tidak dapat dihapus karena sedang digunakan pada kelas aktif.",
            ]);
        }

        $name = $subject->name;
        $subject->delete();

        return back()->with('success', "Mata pelajaran {$name} berhasil dihapus.");
    }
}
