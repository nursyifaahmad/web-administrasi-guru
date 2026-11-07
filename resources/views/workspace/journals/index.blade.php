@extends('layouts.app')

@section('title', 'Jurnal Harian — ' . $teachingClass->class_name)
@section('page_title', 'Menu 1: Jurnal Harian — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', $teachingClass->subject->name . ' • Tersinkronisasi otomatis dengan kehadiran murid pada menu Absensi.')

@section('content')
@include('partials.class-workspace-tabs')

@php
    $isOwner = (int) $teachingClass->teacher_id === (int) auth()->id();
    $isAdminMonitoring = auth()->user()->isAdmin() && ! $isOwner;
@endphp

@if($isAdminMonitoring)
    <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50/80 p-4 shadow-2xs flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-sky-600 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-2xs">
            👁️
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-sm font-bold text-sky-950 flex items-center gap-2">
                Mode Monitoring Supervisi (Hanya Lihat)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-300">
                    Admin / Wakasek
                </span>
            </h3>
            <p class="text-xs text-sky-800 mt-0.5 leading-relaxed">
                Anda sedang memantau jurnal harian kelas ini. Penambahan, pengubahan, dan penghapusan catatan KBM hanya dapat dilakukan oleh guru pengampu (<strong>{{ $teachingClass->teacher->name }}</strong>).
            </p>
        </div>
    </div>
@endif

<div class="space-y-4"
     x-data="journalWorkspace({
        previewUrl: @js(route('journals.attendance-preview', $teachingClass)),
        absentStudentsUrl: @js(route('journals.absent-students', $teachingClass)),
        todayDate: @js(now()->toDateString()),
        todaySchedule: @js($todaySchedule ?? null),
        hasProfileSig: @js(!empty($teachingClass->teacher?->signature_data) || !empty(auth()->user()->signature_data))
     })">

    {{-- Toolbar Filter, Tambah Jurnal & Cetak PDF/Excel --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('journals.index', $teachingClass) }}" class="flex flex-wrap items-center gap-2 text-xs w-full lg:w-auto">
            <select name="month" onchange="this.form.submit()" class="w-full sm:w-auto rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                <option value="">Semua Bulan (1 Semester)</option>
                @foreach(\App\Support\IndonesianDate::MONTHS as $mNum => $mName)
                    <option value="{{ $mNum }}" @selected($filterMonth === $mNum)>{{ $mName }}</option>
                @endforeach
            </select>
            <input type="number" name="year" value="{{ $filterYear }}" min="2020" max="2099"
                   class="w-full sm:w-24 rounded-xl border border-slate-300 px-3 py-2 font-semibold">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari kegiatan / materi / catatan..."
                   class="w-full sm:w-52 rounded-xl border border-slate-300 px-3 py-2">
            <button type="submit" class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 text-center">Filter</button>
            @if($filterMonth || $search)
                <a href="{{ route('journals.index', $teachingClass) }}" class="w-full sm:w-auto text-center px-3 py-2 rounded-xl border border-slate-300 text-slate-600 font-semibold">Reset</a>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-2 text-xs w-full lg:w-auto">
            @if($isOwner)
                <button type="button" @click="openCreate()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition">
                    <span>+ Tambah Jurnal Harian</span>
                </button>
            @endif
            <button type="button" @click="exportModal = true"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition">
                <span>Cetak PDF & Download Excel</span>
            </button>
        </div>
    </div>

    {{-- Tabel Utama Jurnal Harian --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 uppercase text-[11px] border-b border-slate-200">
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-12">No</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200 w-36">Tanggal</th>
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-24">Jam Pelajaran Ke</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200 min-w-[220px]">Kegiatan / Materi</th>
                        <th colspan="2" class="px-3 py-2 text-center border-b border-r border-slate-200 w-40 bg-emerald-50/70 text-emerald-950">Jumlah Murid</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200 min-w-[140px] w-56">Catatan</th>
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-28">Paraf</th>
                        @if($isOwner)
                            <th rowspan="2" class="px-3 py-3 text-right min-w-[140px] w-36">Aksi</th>
                        @endif
                    </tr>
                    <tr class="bg-emerald-50/40 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                        <th class="px-2.5 py-2 text-center border-r border-slate-200 text-emerald-800">Hadir</th>
                        <th class="px-2.5 py-2 text-center border-r border-slate-200 text-rose-800">Tidak Hadir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($journals as $index => $journal)
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-3 py-3 text-center font-bold text-slate-700 border-r border-slate-100">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 border-r border-slate-100">
                                <div class="font-bold text-slate-900">{{ \App\Support\IndonesianDate::dayName($journal->journal_date) }}</div>
                                <div class="text-[11px] text-slate-500">{{ \App\Support\IndonesianDate::formatLong($journal->journal_date) }}</div>
                            </td>
                            <td class="px-3 py-3 text-center font-bold text-indigo-700 border-r border-slate-100">
                                {{ $journal->lesson_hours }}
                            </td>
                            <td class="px-4 py-3 text-slate-800 leading-relaxed border-r border-slate-100 whitespace-pre-line">{{ $journal->activity_material }}</td>
                            <td class="px-3 py-3 text-center font-extrabold text-emerald-700 bg-emerald-50/20 border-r border-slate-100">
                                {{ $journal->present_count }}
                            </td>
                            <td class="px-3 py-3 text-center border-r border-slate-100 {{ $journal->absent_count > 0 ? 'bg-rose-50/30' : '' }}">
                                <div class="flex flex-col items-center justify-center gap-1">
                                    <span class="font-extrabold {{ $journal->absent_count > 0 ? 'text-rose-700 text-sm' : 'text-slate-500' }}">
                                        {{ $journal->absent_count }}
                                    </span>
                                    @if($journal->absent_count > 0)
                                        <button type="button"
                                                @click="openAbsentDetail(@js($journal->journal_date->toDateString()))"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-rose-100 hover:bg-rose-200 text-rose-800 text-[10px] font-bold transition shadow-2xs cursor-pointer"
                                                title="Lihat rincian murid tidak hadir pada tanggal ini">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span>Lihat</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 border-r border-slate-100">
                                <div>{{ $journal->notes ?: '-' }}</div>
                                @if($journal->attendance_breakdown)
                                    <div class="mt-1 inline-block px-2 py-0.5 rounded bg-slate-100 text-[10px] font-semibold text-slate-600">
                                        {{ $journal->attendance_breakdown }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center border-r border-slate-100">
                                @php $paraf = $journal->signature_data ?: $teachingClass->teacher?->signature_data; @endphp
                                @if(!empty($paraf))
                                    <img src="{{ $paraf }}" alt="Paraf" class="max-h-9 mx-auto bg-white border border-slate-200 rounded p-0.5">
                                @else
                                    <span class="text-[10px] text-slate-400 italic">Paraf Basah</span>
                                @endif
                            </td>
                            @if($isOwner)
                                <td class="px-3 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-1 whitespace-nowrap">
                                        <form method="POST" action="{{ route('journals.resync', [$teachingClass, $journal]) }}" title="Tarik ulang jumlah Hadir/Tidak Hadir dari Menu Absensi">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-semibold hover:bg-emerald-100">
                                                Sync Absen
                                            </button>
                                        </form>
                                        <button type="button"
                                                @click="openEdit(@js([
                                                    'journal_date' => $journal->journal_date->toDateString(),
                                                    'lesson_hours' => $journal->lesson_hours,
                                                    'activity_material' => $journal->activity_material,
                                                    'present_count' => $journal->present_count,
                                                    'absent_count' => $journal->absent_count,
                                                    'is_attendance_overridden' => $journal->is_attendance_overridden,
                                                    'notes' => $journal->notes,
                                                    'has_signature' => !empty($journal->signature_data),
                                                    'update_url' => route('journals.update', [$teachingClass, $journal]),
                                                ]))"
                                                class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('journals.destroy', [$teachingClass, $journal]) }}"
                                              onsubmit="return confirm('Hapus baris jurnal tanggal {{ $journal->journal_date->format('d/m/Y') }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOwner ? 9 : 8 }}" class="px-5 py-12 text-center text-slate-500">
                                Belum ada entri Jurnal Harian pada filter ini.
                                @if($isOwner)
                                    Klik <strong>+ Tambah Jurnal Harian</strong> untuk mencatat kegiatan belajar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isOwner)
        {{-- Modal Tambah / Edit Jurnal Harian --}}
        <div x-show="formModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="formModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-xl w-full overflow-hidden max-h-[92vh] flex flex-col">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                    <h3 class="text-sm font-bold" x-text="isEdit ? 'Edit Jurnal Harian' : 'Tambah Jurnal Harian Baru'"></h3>
                    <button type="button" @click="formModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>

                <form method="POST" :action="isEdit ? form.update_url : @js(route('journals.store', $teachingClass))"
                      class="p-5 space-y-3.5 text-xs overflow-y-auto">
                    @csrf
                    <template x-if="isEdit">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Pembelajaran *</label>
                            <input type="date" name="journal_date" x-model="form.journal_date" @change="fetchAttendanceForDate()" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block font-bold text-slate-700">Jam Pelajaran Ke *</label>
                                <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                    Otomatis Master Jadwal
                                </span>
                            </div>
                            <input type="text" name="lesson_hours" x-model="form.lesson_hours" required placeholder="Contoh: 1 - 2 atau 3 - 4"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-indigo-700">

                            {{-- Indikator Master Jadwal Sekolah --}}
                            <template x-if="scheduleInfo && scheduleInfo.found">
                                <div class="mt-1.5 space-y-1">
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold">
                                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span x-text="'Master: ' + (scheduleInfo.label || ('Jam ' + scheduleInfo.lesson_hours))"></span>
                                        </span>
                                        <template x-if="isEdit && form.lesson_hours !== scheduleInfo.lesson_hours">
                                            <button type="button" @click="form.lesson_hours = scheduleInfo.lesson_hours"
                                                    class="px-2 py-0.5 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px] border border-indigo-200 transition cursor-pointer">
                                                Terapkan Jam Master
                                            </button>
                                        </template>
                                    </div>
                                    <template x-if="scheduleInfo.options && scheduleInfo.options.length > 1">
                                        <div class="flex flex-wrap items-center gap-1 text-[10px] text-slate-500 pt-0.5">
                                            <span>Pilih Sesi:</span>
                                            <template x-for="opt in scheduleInfo.options" :key="opt">
                                                <button type="button" @click="form.lesson_hours = opt"
                                                        :class="form.lesson_hours === opt ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold'"
                                                        class="px-2 py-0.5 rounded-md text-[10px] transition cursor-pointer"
                                                        x-text="'Jam ' + opt">
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="scheduleInfo && !scheduleInfo.found">
                                <div class="mt-1.5 flex items-center gap-1 text-[11px] text-slate-500">
                                    <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span x-text="scheduleInfo.label || 'Tidak ada jadwal KBM master pada hari ini (isi manual jika jam tambahan/pengganti)'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kegiatan / Materi Pembelajaran *</label>
                        <textarea name="activity_material" x-model="form.activity_material" rows="3" required
                                  placeholder="Tuliskan materi pokok & kegiatan pembelajaran yang dilaksanakan..."
                                  class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
                    </div>

                    {{-- Panel Sinkronisasi Kehadiran Otomatis dari Menu Absensi (Terkunci) --}}
                    <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 font-bold text-emerald-950">
                                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>Kehadiran Murid (Terkunci & Otomatis dari Absensi)</span>
                            </div>
                            <button type="button" @click="fetchAttendanceForDate()"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-[11px] font-bold hover:bg-emerald-700 shadow-2xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Perbarui dari Absensi</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-2.5 rounded-xl bg-white border border-emerald-300 shadow-2xs">
                                <div class="flex items-center justify-between text-[11px] font-semibold text-emerald-900">
                                    <span>Jumlah Hadir (. + Dispen)</span>
                                    <span class="text-[10px] text-emerald-600 font-bold bg-emerald-100 px-1.5 py-0.5 rounded">Terkunci</span>
                                </div>
                                <div class="textxl font-extrabold text-emerald-700 mt-1" x-text="form.present_count"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white border border-rose-300 shadow-2xs">
                                <div class="flex items-center justify-between text-[11px] font-semibold text-rose-900">
                                    <span>Jumlah Tidak Hadir (S + I + A)</span>
                                    <span class="text-[10px] text-rose-600 font-bold bg-rose-100 px-1.5 py-0.5 rounded">Terkunci</span>
                                </div>
                                <div class="text-xl font-extrabold text-rose-700 mt-1" x-text="form.absent_count"></div>
                            </div>
                        </div>

                        <p class="text-[11px] text-emerald-900 font-medium leading-relaxed" x-text="syncMessage"></p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan Kelas / Kejadian Khusus</label>
                        <textarea name="notes" x-model="form.notes" rows="2"
                                  placeholder="Misal: Siswa tidak hadir, tindak lanjut pembelajaran, siswa dispen..."
                                  class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
                    </div>

                    {{-- Pilihan Paraf Jurnal --}}
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <label class="block font-bold text-slate-800">Paraf Jurnal Harian</label>
                        <div class="flex flex-wrap gap-3">
                            <template x-if="isEdit && form.has_signature">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="signature_mode" value="keep" x-model="form.signature_mode">
                                    <span>Pertahankan Paraf Saat Ini</span>
                                </label>
                            </template>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="signature_mode" value="profile" x-model="form.signature_mode">
                                <span>Gunakan TTD Profil Saya</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="signature_mode" value="canvas" x-model="form.signature_mode" @change="initCanvas()">
                                <span>Goreskan Paraf Baru</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="signature_mode" value="empty" x-model="form.signature_mode">
                                <span>Kosongkan (Paraf Basah)</span>
                            </label>
                        </div>

                        <div x-show="form.signature_mode === 'canvas'" class="pt-2">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] text-slate-600">Goreskan paraf di bawah ini:</span>
                                <button type="button" @click="pad && pad.clear()" class="text-rose-600 font-semibold hover:underline">Bersihkan</button>
                            </div>
                            <canvas x-ref="journalCanvas" width="380" height="100"
                                    class="w-full h-24 rounded-xl border-2 border-dashed border-slate-300 bg-white cursor-crosshair touch-none"></canvas>
                            <input type="hidden" name="signature_data" :value="canvasData">
                        </div>
                    </div>

                    <div class="pt-3 flex flex-col-reverse sm:flex-row justify-end gap-2 border-t border-slate-100 shrink-0">
                        <button type="button" @click="formModal = false" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-300 font-semibold text-center">Batal</button>
                        <button type="submit" class="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 text-center">Simpan Jurnal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Rincian Murid Tidak Hadir --}}
    <div x-show="absentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="absentModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden max-h-[90vh] flex flex-col">
            {{-- Header --}}
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex p-1 rounded-lg bg-rose-500/20 text-rose-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold">Rincian Murid Tidak Hadir</h3>
                    </div>
                    <p class="text-[11px] text-slate-300">
                        <span x-text="absentData?.day_name"></span>, <span x-text="absentData?.formatted_date"></span>
                        • Kelas <span x-text="absentData?.class_name"></span> (<span x-text="absentData?.subject_name"></span>)
                    </p>
                </div>
                <button type="button" @click="absentModal = false" class="text-slate-400 hover:text-white text-2xl leading-none px-1 rounded-lg hover:bg-slate-800 transition">&times;</button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-4 text-xs overflow-y-auto flex-1">
                {{-- Loading State --}}
                <template x-if="absentLoading">
                    <div class="py-12 flex flex-col items-center justify-center gap-3 text-slate-500">
                        <svg class="animate-spin w-6 h-6 text-rose-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="font-medium text-xs">Memuat data absensi murid...</span>
                    </div>
                </template>

                {{-- Content when loaded --}}
                <template x-if="!absentLoading && absentData">
                    <div class="space-y-3.5">
                        {{-- Ringkasan Status --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="font-bold text-slate-700">
                                Total Tidak Hadir: <span class="text-rose-700 font-extrabold text-sm" x-text="absentData.total_absent"></span> orang
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-semibold">
                                <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-200">
                                    Sakit: <strong x-text="absentData.sakit_count"></strong>
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 border border-blue-200">
                                    Izin: <strong x-text="absentData.izin_count"></strong>
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 border border-rose-200">
                                    Alpa: <strong x-text="absentData.alfa_count"></strong>
                                </span>
                                <template x-if="absentData.total_dispen > 0">
                                    <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 border border-purple-200">
                                        Dispen: <strong x-text="absentData.total_dispen"></strong>
                                    </span>
                                </template>
                            </div>
                        </div>

                        {{-- Tabel Siswa Tidak Hadir --}}
                        <template x-if="absentData.students && absentData.students.length > 0">
                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-700 uppercase text-[10px] border-b border-slate-200 font-bold">
                                            <th class="px-2.5 py-2.5 text-center w-10 border-r border-slate-200">No</th>
                                            <th class="px-2.5 py-2.5 text-center w-14 border-r border-slate-200">No Absen</th>
                                            <th class="px-3 py-2.5 border-r border-slate-200">Nama Peserta Didik</th>
                                            <th class="px-2 py-2.5 text-center w-12 border-r border-slate-200">L/P</th>
                                            <th class="px-3 py-2.5 text-center w-28">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(st, idx) in absentData.students" :key="st.id || idx">
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="px-2.5 py-2 text-center text-slate-500 font-medium border-r border-slate-100" x-text="idx + 1"></td>
                                                <td class="px-2.5 py-2 text-center font-bold text-slate-700 border-r border-slate-100" x-text="st.order_number || '-'"></td>
                                                <td class="px-3 py-2 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900" x-text="st.full_name"></div>
                                                    <template x-if="st.nis_nisn && st.nis_nisn !== '-'">
                                                        <div class="text-[10px] text-slate-400 font-mono" x-text="'NIS/NISN: ' + st.nis_nisn"></div>
                                                    </template>
                                                </td>
                                                <td class="px-2 py-2 text-center text-slate-600 font-semibold border-r border-slate-100" x-text="st.gender || '-'"></td>
                                                <td class="px-3 py-2 text-center">
                                                    <span :class="'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold border ' + st.badge_class"
                                                          x-text="st.status_label">
                                                    </span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        {{-- Fallback jika detail belum ada di Menu Absensi (misal diinput manual di jurnal) --}}
                        <template x-if="!absentData.students || absentData.students.length === 0">
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-2 text-center">
                                <div class="font-bold text-xs flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Rincian Absensi Belum Diisi</span>
                                </div>
                                <p class="text-[11px] text-amber-800 leading-relaxed">
                                    Jumlah tidak hadir (<strong x-text="absentData.journal_absent_count || absentData.total_absent"></strong> murid) dicatat pada jurnal harian, namun rincian nama murid pada tanggal ini belum diinput pada menu Absensi.
                                </p>
                                <div class="pt-1">
                                    <a :href="absentData.attendance_url"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold hover:bg-amber-700 transition shadow-2xs">
                                        <span>Buka Menu Absensi</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                <template x-if="absentData?.attendance_url">
                    <a :href="absentData.attendance_url"
                       class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1">
                        <span>Kelola di Menu Absensi</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </template>
                <div class="ml-auto">
                    <button type="button" @click="absentModal = false" class="px-4 py-1.5 rounded-xl border border-slate-300 font-semibold hover:bg-slate-100 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Cetak PDF & Download Excel Jurnal Harian --}}
    @include('partials.export-modal', [
        'modalVar' => 'exportModal',
        'modalTitle' => 'Cetak PDF & Download Excel — Jurnal Harian Kelas ' . $teachingClass->class_name,
        'pdfRoute' => route('exports.journal.pdf', $teachingClass),
        'excelRoute' => route('exports.journal.excel', $teachingClass),
        'defaultOrientation' => 'landscape',
        'extraParams' => ['month' => $filterMonth ?? '', 'year' => $filterYear],
        'isJournalExport' => true,
    ])
</div>

@push('scripts')
<script>
function journalWorkspace(cfg) {
    return {
        formModal: false,
        exportModal: false,
        absentModal: false,
        absentLoading: false,
        absentData: null,
        absentCache: {},
        isEdit: false,
        pad: null,
        canvasData: '',
        syncedRecorded: 0,
        syncMessage: 'Memeriksa data Absensi pada tanggal terpilih...',
        scheduleInfo: cfg.todaySchedule || null,
        form: {
            journal_date: cfg.todayDate,
            lesson_hours: (cfg.todaySchedule && cfg.todaySchedule.found && cfg.todaySchedule.lesson_hours)
                ? cfg.todaySchedule.lesson_hours
                : '1 - 2',
            activity_material: '',
            present_count: 0,
            absent_count: 0,
            is_attendance_overridden: false,
            notes: '',
            signature_mode: 'profile',
            has_signature: false,
            update_url: '',
        },
        async openAbsentDetail(date) {
            if (!date) return;
            this.absentModal = true;
            if (this.absentCache[date]) {
                this.absentData = this.absentCache[date];
                this.absentLoading = false;
                return;
            }
            this.absentLoading = true;
            this.absentData = null;
            try {
                const res = await fetch(`${cfg.absentStudentsUrl}?date=${encodeURIComponent(date)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('Gagal mengambil data');
                const data = await res.json();
                this.absentCache[date] = data;
                this.absentData = data;
            } catch (e) {
                alert('Gagal memuat rincian data murid tidak hadir.');
                this.absentModal = false;
            } finally {
                this.absentLoading = false;
            }
        },
        openCreate() {
            this.isEdit = false;
            const defaultHours = (cfg.todaySchedule && cfg.todaySchedule.found && cfg.todaySchedule.lesson_hours)
                ? cfg.todaySchedule.lesson_hours
                : '1 - 2';

            this.form = {
                journal_date: cfg.todayDate,
                lesson_hours: defaultHours,
                activity_material: '',
                present_count: 0,
                absent_count: 0,
                notes: '',
                signature_mode: cfg.hasProfileSig ? 'profile' : 'canvas',
                has_signature: false,
                update_url: '',
            };
            this.scheduleInfo = cfg.todaySchedule || null;
            this.syncMessage = 'Memeriksa data Absensi pada tanggal terpilih...';
            this.formModal = true;
            this.fetchAttendanceForDate();
            if (this.form.signature_mode === 'canvas') {
                this.initCanvas();
            }
        },
        openEdit(item) {
            this.isEdit = true;
            this.form = {
                journal_date: item.journal_date,
                lesson_hours: item.lesson_hours,
                activity_material: item.activity_material,
                present_count: item.present_count,
                absent_count: item.absent_count,
                notes: item.notes || '',
                signature_mode: item.has_signature ? 'keep' : 'profile',
                has_signature: item.has_signature,
                update_url: item.update_url,
            };
            this.scheduleInfo = null;
            this.syncMessage = (item.present_count > 0 || item.absent_count > 0)
                ? 'Tersinkronisasi otomatis dengan data Absensi.'
                : 'Belum ada isian Absensi pada tanggal ini. Angka kehadiran otomatis tersinkronisasi begitu Anda mengisi data di menu Absensi.';
            this.formModal = true;
            this.fetchAttendanceForDate();
        },
        initCanvas() {
            this.$nextTick(() => {
                this.pad = window.initSignatureCanvas(this.$refs.journalCanvas, (d) => {
                    this.canvasData = d;
                });
            });
        },
        async fetchAttendanceForDate() {
            if (!this.form.journal_date) return;
            try {
                const res = await fetch(`${cfg.previewUrl}?date=${encodeURIComponent(this.form.journal_date)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.syncedRecorded = data.total_recorded || 0;
                this.form.present_count = data.present_count || 0;
                this.form.absent_count = data.absent_count || 0;

                if (data.schedule) {
                    this.scheduleInfo = data.schedule;
                    if (!this.isEdit && data.schedule.found && data.schedule.lesson_hours) {
                        this.form.lesson_hours = data.schedule.lesson_hours;
                    }
                }

                if (data.total_recorded > 0) {
                    this.syncMessage = `Tersinkronisasi otomatis dengan menu Absensi (${data.total_recorded} murid): Hadir ${data.present_count} (termasuk Dispen ${data.dispen_count}), Tidak Hadir ${data.absent_count} (${data.breakdown || 'Lengkap'}).`;
                } else {
                    this.syncMessage = 'Belum ada isian Absensi pada tanggal ini. Angka kehadiran otomatis tersinkronisasi begitu Anda mengisi data di menu Absensi.';
                }
            } catch (e) {
                this.syncMessage = 'Gagal memuat pratinjau absensi.';
            }
        }
    };
}
</script>
@endpush
@endsection
