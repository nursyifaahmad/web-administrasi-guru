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

        $defaultConfig = $this->scheduleService->getDefaultTimeGridConfig();
        $initialGrid = $this->scheduleService->calculateTimeGrid($defaultConfig);
        $defaultClassesSiang = ['8A', '8B', '8C', '8D', '8E', '8F', '8G'];

        $schoolClasses = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('name')
            ->get();

        $teachers = User::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'guru')->orWhereNull('role');
            })
            ->orderBy('name')
            ->get();

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
            'schoolClasses' => $schoolClasses,
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
     * Tukar jadwal antar dua sel (Swap Slot)
     */
    public function swapCells(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'item_id_1' => ['required', 'integer', 'exists:school_schedule_items,id'],
            'item_id_2' => ['required', 'integer', 'exists:school_schedule_items,id'],
        ]);

        $this->scheduleService->swapSlots($validated['item_id_1'], $validated['item_id_2']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Dua slot jadwal berhasil ditukar.');
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
