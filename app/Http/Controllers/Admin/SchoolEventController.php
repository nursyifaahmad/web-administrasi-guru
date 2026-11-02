<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolEventController extends Controller
{
    public function index(Request $request): View
    {
        $yearId = $request->integer('academic_year_id');
        $activeYear = $yearId
            ? AcademicYear::query()->find($yearId) ?? AcademicYear::active()
            : AcademicYear::active();

        $allYears = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('year_name')->get();

        $events = SchoolEvent::query()
            ->where('academic_year_id', $activeYear->id)
            ->orderBy('event_date')
            ->get();

        return view('admin.events.index', [
            'activeYear' => $activeYear,
            'allYears' => $allYears,
            'events' => $events,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'event_date' => ['required', 'date'],
            'event_end_date' => ['nullable', 'date', 'after_or_equal:event_date'],
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:libur_nasional,kegiatan_sekolah,ujian_asesmen,libur_semester'],
            'is_holiday' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_holiday'] = $request->boolean('is_holiday', true);

        SchoolEvent::query()->create($validated);

        return back()->with('success', "Agenda / Hari Libur \"{$validated['title']}\" berhasil ditambahkan.");
    }

    public function destroy(SchoolEvent $schoolEvent): RedirectResponse
    {
        $title = $schoolEvent->title;
        $schoolEvent->delete();

        return back()->with('success', "Agenda / Hari Libur \"{$title}\" berhasil dihapus.");
    }
}
