<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolScheduleController extends Controller
{
    public function __construct(
        protected SchoolScheduleService $scheduleService
    ) {}

    public function index(Request $request): View
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        if (! $academicYear) {
            $academicYear = AcademicYear::query()->first();
        }

        // Jika data jadwal masih kosong, muat template resmi Wakasek secara otomatis
        if ($academicYear) {
            $itemCount = SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->count();
            if ($itemCount === 0) {
                $this->scheduleService->importFromWakasekCsv($academicYear);
                $this->scheduleService->syncToTeachingClasses($academicYear);
            }
        }

        $activeDay = (int) ($request->query('day') ?: 1); // 1 = Senin s.d. 6 = Sabtu
        if ($activeDay < 1 || $activeDay > 6) {
            $activeDay = 1;
        }

        $activeShift = $request->query('shift', 'all'); // 'all', 'pagi', 'siang'

        // Ambil data matriks
        $matrixData = $this->scheduleService->getMasterMatrix($academicYear, $activeDay);
        $conflicts = $this->scheduleService->detectConflicts($academicYear);
        $prerequisites = $this->scheduleService->checkMasterPrerequisites($academicYear);

        $teachers = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('code')
            ->get();

        $allUsers = User::query()->where('is_active', true)->orderBy('name')->get();
        $allSubjects = Subject::query()->where('is_active', true)->orderBy('name')->get();
        $allSchoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->orderBy('name')->get();

        $school = SchoolSetting::current();

        return view('admin.schedules.index', [
            'academicYear' => $academicYear,
            'activeDay' => $activeDay,
            'activeShift' => $activeShift,
            'matrixData' => $matrixData,
            'conflicts' => $conflicts,
            'prerequisites' => $prerequisites,
            'teachers' => $teachers,
            'allUsers' => $allUsers,
            'allSubjects' => $allSubjects,
            'allSchoolClasses' => $allSchoolClasses,
            'school' => $school,
        ]);
    }

    /**
     * Halaman Multi-Step Timetable Builder Wizard (Buat Jadwal Baru dari Awal)
     */
    public function builder(Request $request): View
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        if (! $academicYear) {
            $academicYear = AcademicYear::query()->first();
        }

        $prerequisites = $this->scheduleService->checkMasterPrerequisites($academicYear);

        $defaultConfig = $this->scheduleService->getDefaultTimeGridConfig();
        $initialGrid = $this->scheduleService->calculateTimeGrid($defaultConfig);

        $schoolClasses = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->withCount(['students', 'activeStudents'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        // Kunci daftar rombel dari Master Rombel
        $lockedClasses = $schoolClasses->map(function ($sc) {
            $code = SchoolScheduleService::normalizeClassName($sc->name);
            return [
                'id' => $sc->id,
                'name' => $sc->name,
                'code' => $code,
                'grade_level' => $sc->grade_level,
                'students_count' => $sc->active_students_count ?: $sc->students_count,
                'homeroom_teacher' => $sc->homeroom_teacher_name,
            ];
        })->values();

        // Default kelas siang: kelas dengan grade_level VIII atau berawalan 8
        $defaultClassesSiang = $lockedClasses->filter(function ($c) {
            return $c['grade_level'] === 'VIII' || str_starts_with($c['code'], '8');
        })->pluck('code')->values()->all();

        // Data Guru dari Master Guru beserta mata pelajaran dan kode mengajar yang sah
        $teachers = User::query()
            ->with(['teacherSubjects.subject'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'guru')->orWhereHas('teacherSubjects');
            })
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $subjects = $u->teacherSubjects->map(function ($ts) {
                    return [
                        'subject_id' => $ts->subject_id,
                        'subject_name' => $ts->subject?->name,
                        'subject_code' => $ts->subject?->code,
                        'teaching_code' => $ts->teaching_code,
                    ];
                })->values()->all();

                if (empty($subjects) && ! empty($u->default_subject)) {
                    $subjects = [[
                        'subject_id' => null,
                        'subject_name' => $u->default_subject,
                        'subject_code' => null,
                        'teaching_code' => $u->teaching_codes_list[0] ?? null,
                    ]];
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'nip' => $u->nip,
                    'role' => $u->role,
                    'default_subject' => $u->default_subject,
                    'teaching_codes_list' => $u->teaching_codes_list,
                    'teaching_codes_string' => $u->teaching_codes_string,
                    'subject_codes_list' => $u->subject_codes_list,
                    'subject_codes_string' => $u->subject_codes_string,
                    'subjects' => $subjects,
                    'has_complete_data' => ! empty($subjects) && ! empty($u->teaching_codes_list),
                ];
            });

        $scheduleTeachers = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('code')
            ->get();

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $school = SchoolSetting::current();

        return view('admin.schedules.builder', [
            'academicYear' => $academicYear,
            'prerequisites' => $prerequisites,
            'schoolClasses' => $schoolClasses,
            'lockedClasses' => $lockedClasses,
            'teachers' => $teachers,
            'scheduleTeachers' => $scheduleTeachers,
            'subjects' => $subjects,
            'defaultConfig' => $defaultConfig,
            'defaultClassesSiang' => $defaultClassesSiang,
            'initialGrid' => $initialGrid,
            'school' => $school,
        ]);
    }

    /**
     * AJAX Endpoint untuk kalkulasi grid waktu real-time
     */
    public function calculateSlotsAjax(Request $request): JsonResponse
    {
        $config = $request->all();
        $grid = $this->scheduleService->calculateTimeGrid($config);
        return response()->json([
            'success' => true,
            'grid' => $grid,
        ]);
    }

    /**
     * Eksekusi pembuatan jadwal dari builder (Manual atau Auto Generate)
     */
    public function saveBuilderSchedule(Request $request): RedirectResponse
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        if (! $academicYear) {
            $academicYear = AcademicYear::query()->first();
        }

        // Validasi Kesiapan 2 Pilar Master Data
        $prerequisites = $this->scheduleService->checkMasterPrerequisites($academicYear);
        if (! $prerequisites['classes']['is_ready']) {
            return redirect()->route('admin.classes.index')
                ->with('error', 'Belum ada rombel kelas yang terdaftar di Master Rombel & Murid untuk tahun ajaran ini. Silakan buat rombel kelas terlebih dahulu.');
        }

        if ($prerequisites['teachers']['total'] === 0) {
            return redirect()->route('admin.teachers.index')
                ->with('error', 'Belum ada data guru di Master Guru. Silakan lengkapi data guru terlebih dahulu.');
        }

        $payload = $request->all();

        // Decode JSON jika dikirim sebagai stringified JSON
        if (is_string($request->input('time_config'))) {
            $payload['time_config'] = json_decode($request->input('time_config'), true) ?: [];
        }
        if (is_string($request->input('workloads'))) {
            $payload['workloads'] = json_decode($request->input('workloads'), true) ?: [];
        }
        if (is_string($request->input('selected_classes'))) {
            $payload['selected_classes'] = json_decode($request->input('selected_classes'), true) ?: [];
        }
        if (is_string($request->input('classes_siang'))) {
            $payload['classes_siang'] = json_decode($request->input('classes_siang'), true) ?: [];
        }

        $result = $this->scheduleService->buildScheduleFromWizard($academicYear, $payload);

        $modeLabel = ($result['generation_mode'] === 'auto')
            ? "Otomatis Anti-Bentrok ({$result['allocated_jp']} JP teralokasi, {$result['conflicts_count']} bentrok)"
            : "Kerangka Kosong (siap diisi guru & jam)";

        return redirect()->route('admin.schedules.master.index')
            ->with('status', "Jadwal baru berhasil dibuat dengan mode {$modeLabel}. Total {$result['total_items']} sel slot pelajaran tersedia.");
    }

    /**
     * Wizard "Buat Jadwal Baru dari Awal"
     */
    public function createScratch(Request $request): RedirectResponse
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        $shiftMode = $request->input('shift_mode', 'double');
        $daysCount = (int) $request->input('days_count', 6);
        $sourceMode = $request->input('source_mode', 'empty');

        $result = $this->scheduleService->initializeSchedule($academicYear, [
            'shift_mode' => $shiftMode,
            'days_count' => $daysCount,
            'source_mode' => $sourceMode,
        ]);

        return redirect()->route('admin.schedules.master.index')
            ->with('status', $sourceMode === 'wakasek_template'
                ? "Jadwal resmi Wakasek SMPN 2 Lemahabang berhasil dimuat ({$result['items_count']} slot KBM, {$result['conflicts_count']} peringatan bentrok terdeteksi untuk ditinjau)."
                : 'Kerangka matriks jadwal baru berhasil dibuat dari awal. Silakan isi jam pelajaran.');
    }

    /**
     * Muat Template Resmi Wakasek SMPN 2 Lemahabang
     */
    public function loadWakasekTemplate(Request $request): RedirectResponse
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        $result = $this->scheduleService->importFromWakasekCsv($academicYear);

        return redirect()->route('admin.schedules.master.index')
            ->with('status', "Berhasil memuat Jadwal Wakasek SMPN 2 Lemahabang: {$result['teachers_count']} kode guru terdata, {$result['items_count']} slot terisi, dan {$result['conflicts_count']} titik jadwal bentrok ditandai untuk disesuaikan.");
    }

    /**
     * Update isi satu sel jadwal (AJAX / Form)
     */
    public function updateCell(Request $request, SchoolScheduleItem $item): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'teacher_code' => ['nullable', 'string', 'max:50'],
            'teacher_name' => ['nullable', 'string', 'max:100'],
            'subject_name' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updated = $this->scheduleService->updateSlot($item->id, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'item' => $updated,
                'is_conflict' => $updated->is_conflict,
                'conflict_notes' => $updated->conflict_notes,
            ]);
        }

        return redirect()->back()->with('status', 'Jadwal sel berhasil diperbarui.');
    }

    /**
     * Ambil seluruh slot target penukaran untuk kelas yang sama (bisa berbeda hari)
     */
    public function swapTargets(Request $request): JsonResponse
    {
        $itemId = (int) $request->query('item_id');
        $sourceItem = SchoolScheduleItem::query()->find($itemId);

        if (!$sourceItem) {
            return response()->json(['success' => false, 'message' => 'Slot sumber tidak ditemukan.'], 404);
        }

        $targets = SchoolScheduleItem::query()
            ->where('academic_year_id', $sourceItem->academic_year_id)
            ->where('class_name', $sourceItem->class_name)
            ->where('id', '!=', $sourceItem->id)
            ->orderBy('day_of_week')
            ->orderBy('period_order')
            ->get();

        // Saring slot kegiatan umum sekolah (istirahat/upacara)
        $validTargets = $targets->filter(function (SchoolScheduleItem $item) {
            return !$item->isCommonActivity();
        })->map(function (SchoolScheduleItem $item) {
            $teacherLabel = $item->teacher_code
                ? "Kode {$item->teacher_code} ({$item->teacher_name} - {$item->subject_name})"
                : '⚪ Slot Kosong';

            return [
                'id' => $item->id,
                'day_of_week' => $item->day_of_week,
                'day_name' => $item->day_name,
                'period_order' => $item->period_order,
                'time_range' => $item->time_range,
                'teacher_code' => $item->teacher_code,
                'teacher_name' => $item->teacher_name,
                'subject_name' => $item->subject_name,
                'is_empty' => empty($item->teacher_code),
                'label' => "{$item->day_name} • Jam {$item->period_order} ({$item->time_range}) — {$teacherLabel}",
            ];
        })->values();

        return response()->json([
            'success' => true,
            'source' => [
                'id' => $sourceItem->id,
                'class_name' => $sourceItem->class_name,
                'day_of_week' => $sourceItem->day_of_week,
                'day_name' => $sourceItem->day_name,
                'period_order' => $sourceItem->period_order,
                'time_range' => $sourceItem->time_range,
                'teacher_code' => $sourceItem->teacher_code,
                'teacher_name' => $sourceItem->teacher_name,
                'subject_name' => $sourceItem->subject_name,
            ],
            'slots' => $validTargets,
        ]);
    }

    /**
     * Tukar jadwal antar dua sel (Swap Slot) khusus dalam rombel kelas yang sama
     */
    public function swapCells(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'item_id_1' => ['required', 'integer', 'exists:school_schedule_items,id'],
            'item_id_2' => ['required', 'integer', 'exists:school_schedule_items,id'],
        ]);

        $item1 = SchoolScheduleItem::query()->findOrFail($validated['item_id_1']);
        $item2 = SchoolScheduleItem::query()->findOrFail($validated['item_id_2']);

        // Jaminan integritas: Rombel kelas harus sama agar total JP kelas terjaga
        if ($item1->class_name !== $item2->class_name) {
            $msg = 'Penukaran jadwal hanya dapat dilakukan pada rombel kelas yang sama (' . $item1->class_name . ') agar jumlah JP kelas tetap terjaga.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        if ($item1->academic_year_id !== $item2->academic_year_id) {
            $msg = 'Slot jadwal harus berada pada tahun ajaran yang sama.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        if ($item1->isCommonActivity() || $item2->isCommonActivity()) {
            $msg = 'Kegiatan non-KBM sekolah (seperti Istirahat/Upacara/Sholat Dhuha) tidak dapat ditukar.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        $this->scheduleService->swapSlots($validated['item_id_1'], $validated['item_id_2']);

        $successMsg = "Dua slot jadwal di Kelas {$item1->class_name} berhasil ditukar: ({$item1->day_name} Jam {$item1->period_order}) ⇄ ({$item2->day_name} Jam {$item2->period_order}). Total JP kelas tetap sama.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
            ]);
        }

        return redirect()->back()->with('status', $successMsg);
    }

    /**
     * Sinkronkan seluruh jadwal dari Master Jadwal ke TeachingClass guru
     */
    public function syncClasses(Request $request): RedirectResponse
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        $stats = $this->scheduleService->syncToTeachingClasses($academicYear);

        return redirect()->back()->with('status', "Sinkronisasi Berhasil: {$stats['synced_classes']} kelas berhasil diperbarui jadwalnya, {$stats['created_classes']} kelas baru otomatis dibuatkan untuk guru pengampu.");
    }

    /**
     * Reset / Hapus seluruh isi jadwal
     */
    public function clearSchedule(Request $request): RedirectResponse
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        SchoolScheduleItem::query()->where('academic_year_id', $academicYear->id)->delete();

        return redirect()->route('admin.schedules.master.index')
            ->with('status', 'Seluruh isi matriks jadwal pelajaran berhasil dibersihkan.');
    }

    /**
     * Ekspor & Cetak Jadwal Master Resmi
     */
    public function export(Request $request): View
    {
        $selectedYearId = $request->session()->get('active_academic_year_id');
        $academicYear = $selectedYearId
            ? AcademicYear::query()->find($selectedYearId)
            : AcademicYear::active();

        $matrixData = $this->scheduleService->getMasterMatrix($academicYear);
        $school = SchoolSetting::current();

        return view('admin.schedules.export', [
            'academicYear' => $academicYear,
            'matrixData' => $matrixData,
            'school' => $school,
        ]);
    }
}
