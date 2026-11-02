<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolAndYearController extends Controller
{
    public function index(): View
    {
        return view('admin.settings', [
            'school' => SchoolSetting::current(),
            'academicYears' => AcademicYear::query()
                ->withCount('teachingClasses')
                ->orderByDesc('year_name')
                ->orderBy('semester')
                ->get(),
        ]);
    }

    public function updateSchool(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gov_header' => ['nullable', 'string', 'max:180'],
            'department_header' => ['nullable', 'string', 'max:180'],
            'school_name' => ['required', 'string', 'max:180'],
            'npsn' => ['nullable', 'string', 'max:30'],
            'nss' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:15'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:120'],
            'default_city' => ['required', 'string', 'max:80'],
            'headmaster_name' => ['required', 'string', 'max:180'],
            'headmaster_nip' => ['nullable', 'string', 'max:40'],
            'logo_left_file' => ['nullable', 'image', 'max:2048'],
            'logo_right_file' => ['nullable', 'image', 'max:2048'],
            'headmaster_signature_file' => ['nullable', 'image', 'max:2048'],
            'headmaster_signature_data' => ['nullable', 'string'],
            'clear_logo_left' => ['nullable', 'boolean'],
            'clear_logo_right' => ['nullable', 'boolean'],
            'clear_headmaster_signature' => ['nullable', 'boolean'],
        ]);

        $school = SchoolSetting::current();

        $logoLeft = $school->logo_left_data;
        if ($request->boolean('clear_logo_left')) {
            $logoLeft = null;
        } elseif ($request->hasFile('logo_left_file')) {
            $logoLeft = $this->uploadedImageToDataUri($request->file('logo_left_file'));
        }

        $logoRight = $school->logo_right_data;
        if ($request->boolean('clear_logo_right')) {
            $logoRight = null;
        } elseif ($request->hasFile('logo_right_file')) {
            $logoRight = $this->uploadedImageToDataUri($request->file('logo_right_file'));
        }

        $hmSig = $school->headmaster_signature;
        if ($request->boolean('clear_headmaster_signature')) {
            $hmSig = null;
        } elseif ($request->hasFile('headmaster_signature_file')) {
            $hmSig = $this->uploadedImageToDataUri($request->file('headmaster_signature_file'));
        } elseif (! empty($validated['headmaster_signature_data']) && str_starts_with($validated['headmaster_signature_data'], 'data:image/')) {
            $hmSig = $validated['headmaster_signature_data'];
        }

        $school->update([
            'gov_header' => $validated['gov_header'] ?? '',
            'department_header' => $validated['department_header'] ?? '',
            'school_name' => $validated['school_name'],
            'npsn' => $validated['npsn'] ?? '',
            'nss' => $validated['nss'] ?? null,
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'default_city' => $validated['default_city'],
            'headmaster_name' => $validated['headmaster_name'],
            'headmaster_nip' => $validated['headmaster_nip'] ?? null,
            'logo_left_data' => $logoLeft,
            'logo_right_data' => $logoRight,
            'headmaster_signature' => $hmSig,
        ]);

        return back()->with('success', 'Profil Sekolah, Kop Surat Resmi, & Data Kepala Sekolah berhasil disimpan.');
    }

    public function storeAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_name' => ['required', 'string', 'max:30'],
            'semester' => ['required', Rule::in(['Ganjil', 'Genap'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'set_active' => ['nullable', 'boolean'],
        ]);

        $exists = AcademicYear::query()
            ->where('year_name', trim($validated['year_name']))
            ->where('semester', $validated['semester'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['year_name' => 'Tahun Ajaran dan Semester tersebut sudah terdaftar.']);
        }

        DB::transaction(function () use ($validated, $request) {
            $setActive = $request->boolean('set_active');
            if ($setActive) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            $created = AcademicYear::query()->create([
                'year_name' => trim($validated['year_name']),
                'semester' => $validated['semester'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'is_active' => $setActive,
            ]);

            if ($setActive) {
                $request->session()->put('selected_academic_year_id', $created->id);
            }
        });

        return back()->with('success', 'Tahun Ajaran & Semester baru berhasil ditambahkan.');
    }

    public function activateAcademicYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear, $request) {
            AcademicYear::query()->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
            $request->session()->put('selected_academic_year_id', $academicYear->id);
        });

        return back()->with('success', "{$academicYear->full_label} ditetapkan sebagai Semester Aktif.");
    }

    public function destroyAcademicYear(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->withErrors(['year' => 'Semester yang sedang aktif tidak dapat dihapus.']);
        }
        if ($academicYear->teachingClasses()->exists()) {
            return back()->withErrors(['year' => 'Periode ini memiliki arsip kelas administrasi sehingga tidak dapat dihapus.']);
        }

        $academicYear->delete();

        return back()->with('success', 'Periode Tahun Ajaran berhasil dihapus.');
    }
}
