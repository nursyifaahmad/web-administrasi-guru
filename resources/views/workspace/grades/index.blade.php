@extends('layouts.app')

@section('title', 'Nilai — ' . $teachingClass->class_name)
@section('page_title', 'Menu 3: Nilai — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', 'Kolom PH & NT dinamis, Nilai Kehadiran manual atau otomatis bertoleransi S/I, rumus kontrak belajar fleksibel, & indikator KKTP Admin.')

@section('content')
@include('partials.class-workspace-tabs')

@if((int) $teachingClass->teacher_id === (int) auth()->id())
    {{-- Panel Hak Akses Supervisi Nilai untuk Administrator / Wakasek (Khusus Guru Pengampu) --}}
    <div class="mb-4 rounded-2xl border p-4 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $teachingClass->is_grade_shared_with_admin ? 'bg-emerald-50/70 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
        <div class="flex items-start sm:items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-lg shrink-0 shadow-2xs {{ $teachingClass->is_grade_shared_with_admin ? 'bg-emerald-600 text-white' : 'bg-slate-700 text-white' }}">
                {{ $teachingClass->is_grade_shared_with_admin ? '🔓' : '🔒' }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-900">Hak Akses Supervisi Nilai untuk Admin / Wakasek</span>
                    @if($teachingClass->is_grade_shared_with_admin)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Diizinkan (Hanya Lihat)
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300">
                            Terkunci (Privat Guru)
                        </span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-600 mt-0.5">
                    @if($teachingClass->is_grade_shared_with_admin)
                        Admin / Wakasek dapat melihat daftar nilai kelas ini dalam mode <em>Hanya Lihat (Read-Only)</em> untuk keperluan supervisi akademik atau cetak rapor.
                    @else
                        Secara default rincian nilai siswa bersifat privat dan hanya dapat diakses oleh Anda. Klik tombol di samping jika ingin memberi izin pantau kepada Admin/Wakasek.
                    @endif
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('grades.toggle-admin-access', $teachingClass) }}" class="shrink-0">
            @csrf
            @method('PATCH')
            @if($teachingClass->is_grade_shared_with_admin)
                <button type="submit" class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-900 transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                    <span>🔒 Kunci Kembali (Jadikan Privat)</span>
                </button>
            @else
                <button type="submit" class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                    <span>🔓 Beri Izin Akses ke Admin / Wakasek</span>
                </button>
            @endif
        </form>
    </div>
@endif

@if($isGradeLockedForAdmin)
    {{-- Tampilan Terkunci Khusus Admin / Wakasek saat Belum Diizinkan Guru --}}
    <div class="rounded-2xl border border-amber-200 bg-white p-8 sm:p-12 text-center shadow-2xs max-w-2xl mx-auto my-8 space-y-4">
        <div class="w-16 h-16 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-3xl mx-auto shadow-2xs">
            🔒
        </div>
        <div class="space-y-1.5">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                Memerlukan Izin Guru Pengampu
            </span>
            <h2 class="text-lg font-bold text-slate-900">Buku Nilai Detail Terkunci</h2>
            <p class="text-xs text-slate-600 max-w-lg mx-auto leading-relaxed">
                Guru pengampu (<strong>{{ $teachingClass->teacher->name }}</strong>) belum memberikan izin akses supervisi nilai kelas ini kepada Administrator/Wakasek. Rincian nilai siswa bersifat privat demi menjaga independensi penilaian guru sebelum difinalisasi.
            </p>
        </div>
        <div class="pt-2 flex flex-wrap items-center justify-center gap-2 text-xs">
            <a href="{{ route('attendances.index', $teachingClass) }}" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition">
                Pantau Absensi Siswa
            </a>
            <a href="{{ route('journals.index', $teachingClass) }}" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition">
                Pantau Jurnal Harian
            </a>
        </div>
    </div>
@else

@if(auth()->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) auth()->id())
    {{-- Banner Mode Supervisi Nilai untuk Admin --}}
    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-2xs flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-2xs">
            🔓
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-sm font-bold text-emerald-950 flex items-center gap-2">
                Mode Supervisi Nilai (Hanya Lihat &mdash; Izin Diberikan Guru)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    Admin / Wakasek
                </span>
            </h3>
            <p class="text-xs text-emerald-800 mt-0.5 leading-relaxed">
                Guru pengampu (<strong>{{ $teachingClass->teacher->name }}</strong>) telah membuka akses supervisi untuk buku nilai kelas ini. Anda dapat melihat seluruh rincian nilai siswa dan mencetak/mengekspor laporan (mode baca saja).
            </p>
        </div>
    </div>
@endif

@php
    $w = $gradebook['weight'];
    $phColumns = $gradebook['ph_columns'];
    $ntColumns = $gradebook['nt_columns'];
    $kktp = $gradebook['kktp'];
    $isAutoAtt = $w->isAutoAttendance();
    $tolValue = (int) ($w->sick_leave_tolerance ?? 3);
@endphp

<div class="space-y-4"
     x-data="gradebookSpreadsheet({
        rows: @js($alpineRows),
        phColIds: @js($phColumns->pluck('id')->map(fn($id) => (string)$id)->values()),
        ntColIds: @js($ntColumns->pluck('id')->map(fn($id) => (string)$id)->values()),
        phColumns: @js($phColumns->map(fn($c) => [
            'id' => (string)$c->id,
            'label' => $c->label,
            'topic_note' => $c->topic_note,
            'assessment_date' => $c->assessment_date?->toDateString(),
            'assessment_date_label' => $c->assessment_date_label,
            'update_url' => route('grades.columns.update', [$teachingClass, $c]),
        ])->values()),
        ntColumns: @js($ntColumns->map(fn($c) => ['id' => (string)$c->id, 'label' => $c->label])->values()),
        phAbsentMap: @js($phAbsentMap ?? []),
        classJournalDates: @js($classJournalDates ?? []),
        absentStudentsUrl: @js(route('journals.absent-students', $teachingClass)),
        weights: @js([
            'ph' => (float) $w->weight_ph,
            'attendance' => (float) $w->weight_attendance,
            'nt' => (float) $w->weight_nt,
            'sts' => (float) $w->weight_sts,
            'sas' => (float) $w->weight_sas,
            'precision' => (int) $w->rounding_precision,
        ]),
        isAutoAttendance: @js($isAutoAtt),
        sickLeaveTolerance: @js($tolValue),
        kktp: @js($kktp),
        isAdminReadOnly: @js($isAdminReadOnly),
        saveUrl: @js(route('grades.bulk-save', $teachingClass)),
        csrf: @js(csrf_token())
     })">

    {{-- Banner Rumus Aktif, Statistik Kelas & Tombol Aksi --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-800 border border-indigo-200 font-extrabold">
                    Standar KKM / KKTP Admin: {{ $kktp }}
                </span>
                <div class="px-3 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold">
                    Rumus NA:
                    <strong>PH {{ rtrim(rtrim(number_format($w->weight_ph, 2), '0'), '.') }}%</strong> +
                    <strong>Kehadiran {{ rtrim(rtrim(number_format($w->weight_attendance, 2), '0'), '.') }}%</strong> +
                    <strong>NT {{ rtrim(rtrim(number_format($w->weight_nt, 2), '0'), '.') }}%</strong> +
                    <strong>STS {{ rtrim(rtrim(number_format($w->weight_sts, 2), '0'), '.') }}%</strong> +
                    <strong>SAS {{ rtrim(rtrim(number_format($w->weight_sas, 2), '0'), '.') }}%</strong> = 100%
                </div>
                @if($isAutoAtt)
                    @if(! $isAdminReadOnly)
                        <button type="button" @click="openWeightsModal()"
                                class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold hover:bg-emerald-100 transition cursor-pointer"
                                title="Klik untuk mengubah mode atau batas toleransi Sakit/Izin">
                            Kehadiran: Otomatis Absensi (Toleransi S+I: {{ $tolValue }}x)
                        </button>
                    @else
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">
                            Kehadiran: Otomatis Absensi (Toleransi S+I: {{ $tolValue }}x)
                        </span>
                    @endif
                @else
                    @if(! $isAdminReadOnly)
                        <button type="button" @click="openWeightsModal()"
                                class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 font-bold hover:bg-amber-100 transition cursor-pointer"
                                title="Klik untuk mengaktifkan Nilai Kehadiran otomatis dengan toleransi Sakit/Izin">
                            Kehadiran: Input Manual
                        </button>
                    @else
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 font-bold">
                            Kehadiran: Input Manual
                        </span>
                    @endif
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs w-full lg:w-auto">
                @if(! $isAdminReadOnly)
                    <span x-show="isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 font-bold text-[11px]">
                        Belum disimpan (<span x-text="dirtyCount"></span> nilai / <span x-text="dirtyStudentCount"></span> murid)
                    </span>
                    <span x-show="savedMessage && !isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-semibold text-[11px]" x-text="savedMessage"></span>

                    <button type="button" @click="saveScores()" :disabled="saving" title="Pintasan keyboard: Ctrl + S"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50 shadow-2xs transition text-center cursor-pointer">
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan Nilai'"></span>
                    </button>
                    <button type="button" @click="openWeightsModal()"
                            class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition text-center cursor-pointer">
                        Atur Persentase Rumus NA
                    </button>
                    <button type="button" @click="openQuickPasteModal()"
                            class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold hover:bg-emerald-100 transition text-center cursor-pointer flex items-center justify-center gap-1.5"
                            title="Tempel puluhan nilai dari kolom Excel sekaligus">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Paste Excel</span>
                    </button>
                    <button type="button" @click="importModal = true"
                            class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100 cursor-pointer">
                        Import Excel
                    </button>
                @endif
                <button type="button" @click="openExportModal()"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition text-center cursor-pointer">
                    Cetak PDF & Excel
                </button>
            </div>
        </div>

        {{-- Baris Kontrol Kolom Dinamis PH & NT + Statistik Real-time --}}
        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                @if(! $isAdminReadOnly)
                    <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}" data-skip-dirty-guard="1" @submit.prevent="submitColumnAdd($event)">
                        @csrf
                        <input type="hidden" name="type" value="PH">
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 font-bold hover:bg-sky-100 cursor-pointer">
                            + Tambah Kolom PH ({{ $phColumns->count() }})
                        </button>
                    </form>
                    <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}" data-skip-dirty-guard="1" @submit.prevent="submitColumnAdd($event)">
                        @csrf
                        <input type="hidden" name="type" value="NT">
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-purple-50 text-purple-800 border border-purple-200 font-bold hover:bg-purple-100 cursor-pointer">
                            + Tambah Kolom NT ({{ $ntColumns->count() }})
                        </button>
                    </form>
                    <span class="text-[11px] text-slate-500">Klik judul kolom <strong>PH / NT</strong> untuk ubah/hapus &bull; Tekan <strong>Enter</strong> / <strong>&darr;</strong> untuk murid berikutnya &bull; <strong class="text-emerald-700">Paste (Ctrl+V) di absen 1</strong> untuk mengisi otomatis satu kelas dari Excel.</span>
                @else
                    <span class="text-[11px] text-slate-600 font-semibold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Mode Supervisi Akademik: Seluruh nilai siswa ditampilkan dalam mode Hanya Baca (Read-Only).
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-600">
                <span>Rata-rata Kelas: <strong class="text-slate-900" x-text="summaryStats().avg"></strong></span>
                <span>&bull;</span>
                <span>Tuntas: <strong class="text-emerald-700" x-text="summaryStats().tuntas"></strong></span>
                <span>&bull;</span>
                <span>Belum Tuntas (Remedial): <strong class="text-rose-700" x-text="summaryStats().remedial"></strong></span>
            </div>
        </div>
    </div>

    {{-- Tabel Spreadsheet Buku Nilai Dinamis --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto spreadsheet-scroll">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 uppercase text-[11px] border-b border-slate-300">
                        <th rowspan="2" class="sticky left-0 z-20 bg-slate-100 px-2 py-2.5 text-center border-r border-slate-300 w-9 sm:w-10">No</th>
                        <th rowspan="2" class="sticky left-9 sm:left-10 z-20 bg-slate-100 px-2.5 sm:px-3 py-2.5 text-left border-r border-slate-300 min-w-[130px] sm:min-w-[195px] sticky-col-shadow">Nama Lengkap</th>
                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 w-12">L/P</th>

                        @if($phColumns->count() > 0)
                            <th colspan="{{ $phColumns->count() }}" class="px-2 py-1.5 text-center border-b border-r border-slate-300 bg-sky-100/70 text-sky-950">
                                Nilai Harian / Keaktifan / Karakter ({{ rtrim(rtrim(number_format($w->weight_ph, 2), '0'), '.') }}%)
                            </th>
                        @endif

                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 bg-emerald-50 text-emerald-950 w-28">
                            Nilai Kehadiran<br>
                            <span class="text-[10px] font-normal text-emerald-700">({{ rtrim(rtrim(number_format($w->weight_attendance, 2), '0'), '.') }}%)</span>
                            @if($isAutoAtt)
                                <div class="mt-0.5 text-[9px] font-extrabold text-emerald-800 bg-emerald-100/90 rounded px-1 py-0.25 inline-block normal-case">
                                    Auto &bull; Tol. {{ $tolValue }}x
                                </div>
                            @endif
                        </th>

                        @if($ntColumns->count() > 0)
                            <th colspan="{{ $ntColumns->count() }}" class="px-2 py-1.5 text-center border-b border-r border-slate-300 bg-purple-100/70 text-purple-950">
                                Nilai Tugas ({{ rtrim(rtrim(number_format($w->weight_nt, 2), '0'), '.') }}%)
                            </th>
                        @endif

                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 bg-amber-50/70 text-amber-950 w-20">
                            Nilai STS<br>
                            <span class="text-[10px] font-normal">({{ rtrim(rtrim(number_format($w->weight_sts, 2), '0'), '.') }}%)</span>
                        </th>
                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 bg-amber-50/70 text-amber-950 w-20">
                            Nilai SAS<br>
                            <span class="text-[10px] font-normal">({{ rtrim(rtrim(number_format($w->weight_sas, 2), '0'), '.') }}%)</span>
                        </th>
                        <th rowspan="2" class="px-3 py-2.5 text-center border-r border-slate-300 bg-slate-200/80 text-slate-900 w-20 font-extrabold">
                            NA<br>
                            <span class="text-[10px] font-normal">(Akhir)</span>
                        </th>
                        <th rowspan="2" class="px-3 py-2.5 text-center bg-slate-100 text-slate-800 w-28">
                            Status KKTP ({{ $kktp }})
                        </th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-700 text-[11px] border-b border-slate-300">
                        @foreach($phColumns as $col)
                            <th class="px-1.5 py-1.5 text-center border-r border-slate-200 bg-sky-50/50 min-w-[78px]">
                                @if(! $isAdminReadOnly)
                                    <button type="button"
                                            @click="openEditCol(@js([
                                                'id' => (string) $col->id,
                                                'label' => $col->label,
                                                'topic_note' => $col->topic_note,
                                                'assessment_date' => $col->assessment_date?->toDateString() ?? '',
                                                'assessment_date_label' => $col->assessment_date_label ?? '',
                                                'type' => 'PH',
                                                'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                                'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                            ]))"
                                            class="w-full hover:text-sky-700 group cursor-pointer" title="{{ $col->topic_note ?: 'Klik untuk ubah / hapus kolom' }}">
                                        <div class="font-extrabold flex items-center justify-center gap-0.5">
                                            <span>{{ $col->label }}</span>
                                            <span class="text-[10px] text-slate-400 group-hover:text-sky-600">&#9998;</span>
                                        </div>
                                        @if($col->topic_note)
                                            <div class="text-[9px] font-normal text-slate-500 truncate max-w-[78px] mx-auto">{{ $col->topic_note }}</div>
                                        @endif
                                    </button>
                                    @php
                                        $colDateStr = $col->assessment_date?->toDateString();
                                        $colAbsentInfo = $colDateStr ? ($phAbsentMap[$colDateStr] ?? null) : null;
                                        $colAffected = (int) ($colAbsentInfo['total_affected'] ?? 0);
                                    @endphp
                                    <button type="button"
                                            @click.stop="openAbsentConfirmForColumn('{{ $col->id }}', 'manual')"
                                            :title="getPhPillTitle('{{ $col->id }}')"
                                            class="mt-1 inline-flex items-center justify-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold border transition cursor-pointer max-w-[98px] truncate"
                                            :class="getPhPillClass('{{ $col->id }}')">
                                        <span x-text="getPhPillText('{{ $col->id }}')">
                                            @if($col->assessment_date)
                                                {{ $col->assessment_date_label }}@if($colAffected > 0) &bull; {{ $colAffected }} S/I/A/D @endif
                                            @else
                                                + Tgl PH
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    <div class="w-full py-0.5" title="{{ $col->topic_note ?: $col->label }}">
                                        <div class="font-extrabold text-slate-800">{{ $col->label }}</div>
                                        @if($col->topic_note)
                                            <div class="text-[9px] font-normal text-slate-500 truncate max-w-[78px] mx-auto">{{ $col->topic_note }}</div>
                                        @endif
                                        @if($col->assessment_date)
                                            <div class="mt-0.5 text-[9px] font-semibold text-sky-700">{{ $col->assessment_date_label }}</div>
                                        @endif
                                    </div>
                                @endif
                            </th>
                        @endforeach

                        @foreach($ntColumns as $col)
                            <th class="px-1.5 py-1.5 text-center border-r border-slate-200 bg-purple-50/50 min-w-[68px]">
                                @if(! $isAdminReadOnly)
                                    <button type="button"
                                            @click="openEditCol(@js([
                                                'label' => $col->label,
                                                'topic_note' => $col->topic_note,
                                                'type' => 'NT',
                                                'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                                'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                            ]))"
                                            class="w-full hover:text-purple-700 group cursor-pointer" title="{{ $col->topic_note ?: 'Klik untuk ubah / hapus kolom' }}">
                                        <div class="font-extrabold flex items-center justify-center gap-0.5">
                                            <span>{{ $col->label }}</span>
                                            <span class="text-[10px] text-slate-400 group-hover:text-purple-600">&#9998;</span>
                                        </div>
                                        @if($col->topic_note)
                                            <div class="text-[9px] font-normal text-slate-500 truncate max-w-[72px] mx-auto">{{ $col->topic_note }}</div>
                                        @endif
                                    </button>
                                @else
                                    <div class="w-full py-0.5" title="{{ $col->topic_note ?: $col->label }}">
                                        <div class="font-extrabold text-slate-800">{{ $col->label }}</div>
                                        @if($col->topic_note)
                                            <div class="text-[9px] font-normal text-slate-500 truncate max-w-[72px] mx-auto">{{ $col->topic_note }}</div>
                                        @endif
                                    </div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" @focusout="handleTableFocusOut($event, $el)">
                    <template x-if="rows.length === 0">
                        <tr>
                            <td :colspan="7 + phColIds.length + ntColIds.length" class="px-5 py-10 text-center text-slate-500">
                                Belum ada peserta didik di kelas ini. Tambahkan murid pada tab <strong>Peserta Didik & Wali Kelas</strong>.
                            </td>
                        </tr>
                    </template>
                    <template x-for="(row, rIdx) in rows" :key="row.student_id">
                        <tr :class="activeStudentId === row.student_id ? 'bg-purple-50/35' : 'hover:bg-slate-50/80'">
                            <td class="sticky left-0 z-10 px-2 py-1.5 text-center font-bold border-r border-slate-200 transition-colors"
                                :class="activeStudentId === row.student_id ? 'bg-purple-50 text-purple-900' : 'bg-white text-slate-600'"
                                x-text="row.no"></td>
                            <td class="sticky left-9 sm:left-10 z-10 px-2.5 sm:px-3 py-1.5 font-bold border-r border-slate-200 truncate max-w-[135px] sm:max-w-[220px] sticky-col-shadow transition-colors"
                                :class="activeStudentId === row.student_id ? 'name-cell-active' : 'bg-white text-slate-900'"
                                x-text="row.full_name"></td>
                            <td class="px-2 py-1.5 text-center font-semibold text-slate-700 border-r border-slate-200" x-text="row.gender"></td>

                            {{-- Input Dinamis PH --}}
                            <template x-for="colId in phColIds" :key="'ph-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                           :id="`grade-${rIdx}-ph-${colId}`"
                                           x-model="row.dynamic_scores[colId]"
                                           :readonly="isAdminReadOnly"
                                           :disabled="isAdminReadOnly"
                                           @input="markDirty(); delete confirmedAbsentPhCols[String(colId)]; checkAbsen1Input(rIdx, `ph-${colId}`, $event.target.value)"
                                           @change="handleAbsen1Change(rIdx, `ph-${colId}`, $event.target.value)"
                                           @focus="selectInput($event, row.student_id)"
                                           @keydown="handleGradeKey($event, rIdx, `ph-${colId}`)"
                                           @paste="handleCellPaste($event, rIdx, `ph-${colId}`)"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500 transition-colors"
                                           :class="[
                                               pastedKeys[`${rIdx}-ph-${colId}`] ? 'bg-emerald-100 text-emerald-950 font-black ring-2 ring-emerald-500 z-10' : '',
                                               isAdminReadOnly ? 'pointer-events-none cursor-default bg-slate-50/70 text-slate-700 select-none' : '',
                                               (!isAdminReadOnly && !pastedKeys[`${rIdx}-ph-${colId}`] && (row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '')) ? 'bg-amber-50/35 text-slate-500' : (!isAdminReadOnly && !pastedKeys[`${rIdx}-ph-${colId}`] ? 'bg-white text-slate-900' : '')
                                           ]">
                                </td>
                            </template>

                            {{-- Nilai Kehadiran (Mode Otomatis Bertoleransi atau Input Manual) --}}
                            <td class="p-0 border-r border-slate-200 relative group" :title="attendanceTooltip(row)">
                                <template x-if="isAutoAttendance">
                                    <div class="w-full min-h-8 px-1.5 py-1 flex flex-col items-center justify-center bg-emerald-50/60 select-none">
                                        <span class="text-xs font-extrabold"
                                              :class="row.attendance_score === null ? 'text-slate-400' : (Number(row.attendance_score) >= 100 ? 'text-emerald-800' : 'text-amber-900')"
                                              x-text="row.attendance_score !== null && row.attendance_score !== '' ? row.attendance_score : '-'"></span>
                                        <template x-if="row.attendance_ref && row.attendance_ref.total > 0">
                                            <span class="text-[9px] leading-tight font-semibold"
                                                  :class="(row.attendance_ref.excess_si > 0 || row.attendance_ref.alfa > 0) ? 'text-rose-700' : 'text-emerald-700'"
                                                  x-text="`H:${row.attendance_ref.present} S/I:${row.attendance_ref.sick_leave_total} A:${row.attendance_ref.alfa}`"></span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!isAutoAttendance">
                                    <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                           :id="`grade-${rIdx}-att`"
                                           x-model="row.attendance_score"
                                           :readonly="isAdminReadOnly"
                                           :disabled="isAdminReadOnly"
                                           @input="markDirty(); checkAbsen1Input(rIdx, 'att', $event.target.value)"
                                           @change="handleAbsen1Change(rIdx, 'att', $event.target.value)"
                                           @focus="selectInput($event, row.student_id)"
                                           @keydown="handleGradeKey($event, rIdx, 'att')"
                                           @paste="handleCellPaste($event, rIdx, 'att')"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-bold text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors"
                                           :class="[
                                               pastedKeys[`${rIdx}-att`] ? 'bg-emerald-100 text-emerald-950 font-black ring-2 ring-emerald-500 z-10' : '',
                                               isAdminReadOnly ? 'pointer-events-none cursor-default bg-emerald-50/40 select-none' : (!pastedKeys[`${rIdx}-att`] ? 'bg-emerald-50/25' : '')
                                           ]">
                                </template>
                            </td>

                            {{-- Input Dinamis NT --}}
                            <template x-for="colId in ntColIds" :key="'nt-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                           :id="`grade-${rIdx}-nt-${colId}`"
                                           x-model="row.dynamic_scores[colId]"
                                           :readonly="isAdminReadOnly"
                                           :disabled="isAdminReadOnly"
                                           @input="markDirty(); checkAbsen1Input(rIdx, `nt-${colId}`, $event.target.value)"
                                           @change="handleAbsen1Change(rIdx, `nt-${colId}`, $event.target.value)"
                                           @focus="selectInput($event, row.student_id)"
                                           @keydown="handleGradeKey($event, rIdx, `nt-${colId}`)"
                                           @paste="handleCellPaste($event, rIdx, `nt-${colId}`)"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors"
                                           :class="[
                                               pastedKeys[`${rIdx}-nt-${colId}`] ? 'bg-emerald-100 text-emerald-950 font-black ring-2 ring-emerald-500 z-10' : '',
                                               isAdminReadOnly ? 'pointer-events-none cursor-default bg-slate-50/70 text-slate-700 select-none' : '',
                                               (!isAdminReadOnly && !pastedKeys[`${rIdx}-nt-${colId}`] && (row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '')) ? 'bg-amber-50/35 text-slate-500' : (!isAdminReadOnly && !pastedKeys[`${rIdx}-nt-${colId}`] ? 'bg-white text-slate-900' : '')
                                           ]">
                                </td>
                            </template>

                            {{-- Input STS & SAS --}}
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                       :id="`grade-${rIdx}-sts`"
                                       x-model="row.sts_score"
                                       :readonly="isAdminReadOnly"
                                       :disabled="isAdminReadOnly"
                                       @input="markDirty(); checkAbsen1Input(rIdx, 'sts', $event.target.value)"
                                       @change="handleAbsen1Change(rIdx, 'sts', $event.target.value)"
                                       @focus="selectInput($event, row.student_id)"
                                       @keydown="handleGradeKey($event, rIdx, 'sts')"
                                       @paste="handleCellPaste($event, rIdx, 'sts')"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors"
                                       :class="[
                                           pastedKeys[`${rIdx}-sts`] ? 'bg-emerald-100 text-emerald-950 font-black ring-2 ring-emerald-500 z-10' : '',
                                           isAdminReadOnly ? 'pointer-events-none cursor-default bg-amber-50/20 text-slate-700 select-none' : (!pastedKeys[`${rIdx}-sts`] ? 'bg-amber-50/15' : '')
                                       ]">
                            </td>
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                       :id="`grade-${rIdx}-sas`"
                                       x-model="row.sas_score"
                                       :readonly="isAdminReadOnly"
                                       :disabled="isAdminReadOnly"
                                       @input="markDirty(); checkAbsen1Input(rIdx, 'sas', $event.target.value)"
                                       @change="handleAbsen1Change(rIdx, 'sas', $event.target.value)"
                                       @focus="selectInput($event, row.student_id)"
                                       @keydown="handleGradeKey($event, rIdx, 'sas')"
                                       @paste="handleCellPaste($event, rIdx, 'sas')"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors"
                                       :class="[
                                           pastedKeys[`${rIdx}-sas`] ? 'bg-emerald-100 text-emerald-950 font-black ring-2 ring-emerald-500 z-10' : '',
                                           isAdminReadOnly ? 'pointer-events-none cursor-default bg-amber-50/20 text-slate-700 select-none' : (!pastedKeys[`${rIdx}-sas`] ? 'bg-amber-50/15' : '')
                                       ]">
                            </td>

                            {{-- Nilai Akhir (NA) Real-time --}}
                            <td class="px-2 py-1.5 text-center font-extrabold border-r border-slate-200"
                                :class="calculateNA(row) >= kktp ? 'bg-emerald-50/50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
                                x-text="formatNA(calculateNA(row))">
                            </td>

                            {{-- Badge Ketuntasan KKTP --}}
                            <td class="px-2 py-1.5 text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                                      :class="calculateNA(row) >= kktp ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="calculateNA(row) >= kktp ? 'Tuntas' : 'Perlu Remedial'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    @if(! $isAdminReadOnly)
    {{-- Kartu & Tombol Simpan Panjang di Bawah Tabel Daftar Nilai --}}
    <div x-show="rows.length > 0"
         class="rounded-2xl border p-4 shadow-2xs transition"
         :class="isDirty ? 'bg-amber-50/70 border-amber-300 ring-2 ring-amber-200/70' : 'bg-white border-slate-200'">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs mb-3">
            <div class="flex items-center gap-2">
                <template x-if="isDirty">
                    <span class="inline-flex items-center gap-2 font-bold text-amber-950">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Ada <strong x-text="dirtyCount"></strong> perubahan nilai pada <strong x-text="dirtyStudentCount"></strong> murid yang belum disimpan.</span>
                    </span>
                </template>
                <template x-if="!isDirty">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-800">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="savedMessage || 'Seluruh nilai & Nilai Akhir (NA) pada tabel ini sudah tersimpan'"></span>
                    </span>
                </template>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-600">
                <span>Rata-rata: <strong class="text-slate-900" x-text="summaryStats().avg"></strong></span>
                <span>&bull;</span>
                <span>Tuntas: <strong class="text-emerald-700" x-text="summaryStats().tuntas"></strong></span>
                <span>&bull;</span>
                <span>Remedial: <strong class="text-rose-700" x-text="summaryStats().remedial"></strong></span>
                <span>&bull;</span>
                <span>Pintasan: <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-bold text-slate-700">Ctrl + S</kbd></span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <button type="button"
                    @click="saveScores()"
                    :disabled="saving"
                    class="flex-1 py-3.5 px-6 rounded-xl text-sm font-extrabold text-white shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
                    :class="isDirty ? 'bg-emerald-600 hover:bg-emerald-700 ring-2 ring-emerald-400/50' : 'bg-emerald-600 hover:bg-emerald-700'">
                <svg x-show="!saving" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="saving" x-cloak class="w-5 h-5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                <span x-text="saving ? 'Sedang Menyimpan Daftar Nilai & Menghitung NA...' : (isDirty ? `Simpan Perubahan Daftar Nilai (${dirtyCount} nilai / ${dirtyStudentCount} murid)` : 'Simpan Seluruh Daftar Nilai & Perbarui Nilai Akhir (NA)')"></span>
            </button>

            <button type="button"
                    x-show="isDirty && !saving"
                    x-cloak
                    @click="revertChanges()"
                    class="px-4 py-3.5 rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 text-xs font-bold transition cursor-pointer">
                Urungkan Perubahan
            </button>
        </div>
    </div>

    {{-- Modal Edit / Hapus Kolom Dinamis PH / NT --}}
    <div x-show="editCol !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <template x-if="editCol">
            <div @click.away="editCol = null" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Pengaturan Kolom <span x-text="editCol.label"></span></h3>
                    <button type="button" @click="editCol = null" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>
                <form method="POST" :action="editCol.update_url" class="p-5 space-y-3.5 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Kolom *</label>
                        <input type="text" name="label" x-model="editCol.label" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Keterangan Materi / Topik (Opsional)</label>
                        <input type="text" name="topic_note" x-model="editCol.topic_note" placeholder="Contoh: Bab 2 Aljabar / Keaktifan Diskusi"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <template x-if="editCol.type === 'PH'">
                        <div class="p-3 rounded-xl bg-sky-50/70 border border-sky-200 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="block font-bold text-sky-900">Tanggal Penilaian Harian (Opsional)</label>
                                <button type="button" x-show="editCol.assessment_date" @click="editCol.assessment_date = ''"
                                        class="text-[11px] font-bold text-rose-600 hover:underline cursor-pointer">
                                    Lepas Tanggal
                                </button>
                            </div>
                            <p class="text-[11px] text-sky-800 leading-relaxed">
                                Hubungkan kolom PH ke tanggal Jurnal Harian &amp; Absensi agar sistem otomatis mengonfirmasi nilai murid yang <strong>Sakit / Izin / Alpa / Dispen</strong>.
                            </p>
                            <template x-if="classJournalDates.length > 0">
                                <select @change="if ($event.target.value) { editCol.assessment_date = $event.target.value; }"
                                        :value="editCol.assessment_date || ''"
                                        class="w-full rounded-xl border border-sky-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-800">
                                    <option value="">-- Pilih cepat tanggal dari Jurnal &amp; Absensi --</option>
                                    <template x-for="dOpt in classJournalDates" :key="dOpt.date">
                                        <option :value="dOpt.date" x-text="dOpt.option_label"></option>
                                    </template>
                                </select>
                            </template>
                            <input type="date" name="assessment_date" x-model="editCol.assessment_date"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold">
                            <div x-show="editCol.assessment_date" class="pt-1">
                                <button type="button" @click="openAbsentFromEditCol()"
                                        class="w-full px-3 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-950 border border-amber-300 font-bold text-[11px] transition cursor-pointer">
                                    Konfirmasi Nilai Siswa S/I/A/D Tanggal Ini
                                </button>
                            </div>
                        </div>
                    </template>
                    <div class="pt-3 flex items-center justify-between border-t border-slate-100">
                        <button type="button"
                                @click="if(confirm('Hapus kolom ' + editCol.label + ' beserta seluruh nilai pada kolom ini?')) $refs.deleteColForm.submit()"
                                class="px-3.5 py-2 rounded-xl bg-rose-50 text-rose-700 font-bold hover:bg-rose-100">
                            Hapus Kolom Ini
                        </button>
                        <div class="flex gap-2">
                            <button type="button" @click="editCol = null" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan</button>
                        </div>
                    </div>
                </form>
                <form x-ref="deleteColForm" method="POST" :action="editCol.delete_url" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </template>
    </div>

    {{-- Modal Pengaturan Rumus Persentase Nilai Akhir (Kontrak Belajar) --}}
    <div x-show="weightsModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="weightsModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-xl w-full overflow-hidden max-h-[92vh] flex flex-col"
             x-data="{
                wPh: {{ (float) $w->weight_ph }},
                wAtt: {{ (float) $w->weight_attendance }},
                wNt: {{ (float) $w->weight_nt }},
                wSts: {{ (float) $w->weight_sts }},
                wSas: {{ (float) $w->weight_sas }},
                attMode: @js($w->attendance_mode ?? 'manual'),
                tolSI: {{ $tolValue }},
                totalW() {
                    return Math.round((Number(this.wPh||0) + Number(this.wAtt||0) + Number(this.wNt||0) + Number(this.wSts||0) + Number(this.wSas||0)) * 100) / 100;
                }
             }">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-bold">Pengaturan Persentase Rumus Nilai Akhir (NA)</h3>
                    <p class="text-[11px] text-slate-300">Sesuaikan dengan kontrak belajar bersama peserta didik Kelas {{ $teachingClass->class_name }}</p>
                </div>
                <button type="button" @click="weightsModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="{{ route('grades.weights.update', $teachingClass) }}" class="p-5 space-y-4 text-xs overflow-y-auto">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-sky-900 mb-1">Rata-rata PH (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight_ph" x-model="wPh" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-extrabold">
                    </div>
                    <div>
                        <label class="block font-bold text-emerald-900 mb-1">Nilai Kehadiran (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight_attendance" x-model="wAtt" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-extrabold">
                    </div>
                    <div>
                        <label class="block font-bold text-purple-900 mb-1">Rata-rata Tugas / NT (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight_nt" x-model="wNt" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-extrabold">
                    </div>
                    <div>
                        <label class="block font-bold text-amber-900 mb-1">Nilai STS (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight_sts" x-model="wSts" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-extrabold">
                    </div>
                    <div>
                        <label class="block font-bold text-amber-900 mb-1">Nilai SAS (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight_sas" x-model="wSas" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-extrabold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Pembulatan NA</label>
                        <select name="rounding_precision" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                            <option value="2" @selected((int)$w->rounding_precision === 2)>2 Desimal (82.45)</option>
                            <option value="1" @selected((int)$w->rounding_precision === 1)>1 Desimal (82.5)</option>
                            <option value="0" @selected((int)$w->rounding_precision === 0)>Bulat (82)</option>
                        </select>
                    </div>
                </div>

                <div class="p-3 rounded-xl flex items-center justify-between font-bold"
                     :class="Math.abs(totalW() - 100) < 0.01 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                    <span>Total Persentase Rumus Saat Ini:</span>
                    <span class="text-sm" x-text="totalW() + '% (Wajib 100%)'"></span>
                </div>

                {{-- Pengaturan Mode & Toleransi Sakit/Izin Nilai Kehadiran --}}
                <div class="p-3.5 rounded-xl bg-emerald-50/60 border border-emerald-200 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <div class="font-extrabold text-emerald-950">Sumber &amp; Toleransi Nilai Kehadiran</div>
                            <div class="text-[11px] text-emerald-800">Atur apakah Nilai Kehadiran dihitung otomatis dari Menu Absensi beserta toleransi Sakit/Izin.</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer transition"
                               :class="attMode === 'auto' ? 'bg-white border-emerald-500 ring-1 ring-emerald-500' : 'bg-white/70 border-slate-200 hover:bg-white'">
                            <input type="radio" name="attendance_mode" value="auto" x-model="attMode" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="font-extrabold text-slate-900">Otomatis dari Absensi</div>
                                <div class="text-[10px] text-slate-600">Sinkron otomatis dari Menu Absensi dengan kuota toleransi Sakit + Izin.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer transition"
                               :class="attMode === 'manual' ? 'bg-white border-emerald-500 ring-1 ring-emerald-500' : 'bg-white/70 border-slate-200 hover:bg-white'">
                            <input type="radio" name="attendance_mode" value="manual" x-model="attMode" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="font-extrabold text-slate-900">Input Angka Manual</div>
                                <div class="text-[10px] text-slate-600">Guru mengetik bebas angka Nilai Kehadiran (0 - 100) di tabel nilai.</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-1">
                        <div class="flex-1">
                            <label class="block font-bold text-slate-800">Batas Toleransi Sakit + Izin (kali / semester)</label>
                            <p class="text-[10px] text-slate-600">
                                Hadir (<code>.</code>) &amp; Dispen (<code>d</code>) dihitung 100%. Selama total <strong>S + I &le; <span x-text="tolSI || 0"></span>x</strong> dan <strong>Alfa = 0</strong>, Nilai Kehadiran tetap <strong>100</strong>. Kelebihan S/I &amp; Alfa mengurangi nilai secara proporsional.
                            </p>
                        </div>
                        <div class="w-full sm:w-28 shrink-0">
                            <input type="number" name="sick_leave_tolerance" min="0" max="100" step="1" x-model="tolSI" required
                                   class="w-full rounded-xl border border-emerald-300 bg-white px-3 py-2 text-center font-extrabold text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan Perjanjian / Kontrak Belajar (Opsional)</label>
                    <input type="text" name="agreement_note" value="{{ $w->agreement_note }}"
                           placeholder="Contoh: Disepakati bersama murid pada pertemuan pertama"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>

                @if($siblingClasses->isNotEmpty())
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="font-bold text-slate-800">Salin Rumus Persentase Ini ke Kelas Lainnya (Opsional):</div>
                        <div class="max-h-36 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            @foreach($siblingClasses as $sc)
                                <label class="flex items-center gap-2 cursor-pointer p-1.5 rounded-lg hover:bg-white">
                                    <input type="checkbox" name="copy_to_class_ids[]" value="{{ $sc->id }}" class="rounded border-slate-300 text-emerald-600">
                                    <span><strong>{{ $sc->class_name }}</strong> ({{ $sc->subject->code ?? $sc->subject->name ?? '' }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="pt-3 flex flex-col-reverse sm:flex-row justify-end gap-2 border-t border-slate-100 shrink-0">
                    <button type="button" @click="weightsModal = false" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-300 font-semibold text-center">Batal</button>
                    <button type="submit" :disabled="Math.abs(totalW() - 100) >= 0.01"
                            class="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-40 text-center">
                        Simpan Rumus & Hitung Ulang NA
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Import Nilai Excel --}}
    <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="importModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Import Nilai dari Excel</h3>
                <button type="button" @click="importModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="{{ route('grades.import', $teachingClass) }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 flex items-center justify-between gap-2">
                    <span>Template Excel otomatis mengikuti kolom PH & NT kelas ini:</span>
                    <a href="{{ route('grades.template', $teachingClass) }}"
                       data-skip-dirty-guard="1"
                       class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shrink-0">
                        Unduh Template
                    </a>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Pilih File Excel (.xlsx / .xls / .csv) *</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full text-xs">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="importModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Mulai Import Nilai</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Quick Paste Nilai Excel --}}
    <div x-show="quickPasteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="quickPasteModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-emerald-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <h3 class="text-sm font-bold">Paste Nilai dari Excel</h3>
                </div>
                <button type="button" @click="quickPasteModal = false" class="text-white/80 hover:text-white text-xl cursor-pointer leading-none">&times;</button>
            </div>
            <div class="p-5 space-y-4 text-xs">
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-xs text-emerald-950">
                        <span>💡 Cara Penggunaan Sangat Praktis:</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-emerald-900">
                        1. Di Excel, sorot & salin 1 kolom nilai (misal 38 murid dari atas ke bawah).<br>
                        2. Anda bisa langsung <strong>Paste (Ctrl+V) di tabel pada kotak nomor absen 1</strong>, ATAU tempel di kotak teks di bawah ini lalu klik tombol Terapkan.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Target Kolom Nilai *</label>
                        <select x-model="quickPasteTargetCol" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <template x-for="c in getEditableColumns()" :key="c.key">
                                <option :value="c.key" x-text="c.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mulai dari Murid *</label>
                        <select x-model="quickPasteStartRow" @change="updateQuickPastePreview()" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <template x-for="(r, idx) in rows" :key="r.student_id">
                                <option :value="idx" x-text="`Absen ${r.no} - ${r.full_name}`"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700">Tempelkan Teks Nilai dari Excel di sini *</label>
                        <span class="text-[10px] text-slate-500">Format: 1 nilai per baris</span>
                    </div>
                    <textarea x-model="quickPasteText" @input="updateQuickPastePreview()"
                              rows="6"
                              placeholder="Klik di sini lalu tekan Ctrl + V (Paste)...&#10;Contoh format:&#10;80&#10;70&#10;65&#10;65&#10;45&#10;90"
                              class="w-full rounded-xl border border-slate-300 p-3 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                <div x-show="quickPastePreviewCount > 0" class="p-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 text-xs flex items-center justify-between">
                    <div>
                        Terdeteksi: <strong class="text-emerald-700" x-text="quickPastePreviewCount"></strong> nilai
                        (akan diterapkan ke murid absen <strong x-text="Number(quickPasteStartRow) + 1"></strong> s/d <strong x-text="Math.min(rows.length, Number(quickPasteStartRow) + quickPastePreviewCount)"></strong>)
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="quickPasteModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold cursor-pointer hover:bg-slate-50">Batal</button>
                    <button type="button" @click="applyQuickPaste()" :disabled="quickPastePreviewCount === 0"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50 cursor-pointer shadow-2xs">
                        Terapkan ke Tabel Nilai
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Set Nilai Sama dengan Absen 1 --}}
    <div x-show="sameScorePrompt.show" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.window.escape="if (sameScorePrompt.show) cancelSameScorePrompt()"
         @keydown.window.enter.prevent="if (sameScorePrompt.show && Date.now() - (sameScorePrompt._openedAt || 0) > 250) applySameScoreToAll()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="if (sameScorePrompt.show && Date.now() - (sameScorePrompt._openedAt || 0) > 400) cancelSameScorePrompt()"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm sm:max-w-md w-full overflow-hidden p-6 space-y-4 text-center">
            
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center shadow-xs">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>

            <div>
                <h3 class="text-base font-extrabold text-slate-900">Set Nilai Sama dengan Absen 1?</h3>
                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                    Anda baru saja mengisi nilai <strong class="text-indigo-700 text-sm" x-text="sameScorePrompt.score"></strong> pada murid nomor absen 1 di kolom <strong class="text-slate-800" x-text="sameScorePrompt.colLabel"></strong>.
                </p>
                <div class="mt-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-700 font-semibold">
                    Terapkan nilai <span class="text-emerald-700 font-black" x-text="sameScorePrompt.score"></span> ke seluruh <strong x-text="sameScorePrompt.targetCount"></strong> murid di kelas ini?
                </div>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-2">
                <button type="button" @click="applySameScoreToAll()"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition cursor-pointer text-xs flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Ya, Samakan Semua Murid</span>
                </button>
                <button type="button" @click="cancelSameScorePrompt()"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer text-xs">
                    Tidak, Hanya Absen 1
                </button>
            </div>
            <div class="text-[10px] text-slate-400">
                Pintasan keyboard: Tekan <strong>Enter</strong> untuk Ya &bull; Tekan <strong>Esc</strong> untuk Tidak
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Nilai Siswa S/I/A/D untuk Penilaian Harian --}}
    <div x-show="absentConfirmModal.show" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.window.escape="if (absentConfirmModal.show) closeAbsentConfirm()"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/65 backdrop-blur-xs">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-3xl w-full overflow-hidden max-h-[92vh] flex flex-col">
            {{-- Header Modal --}}
            <div class="px-5 py-4 bg-slate-900 text-white flex items-start justify-between gap-3 shrink-0">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-extrabold">Konfirmasi Nilai Siswa Tidak Hadir &amp; Dispen</h3>
                        <span class="px-2 py-0.5 rounded-full bg-sky-500/20 border border-sky-400/40 text-sky-200 text-[10px] font-bold"
                              x-text="absentConfirmModal.colLabel"></span>
                        <span x-show="absentConfirmModal.queue.length > 0"
                              class="px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-200 text-[10px] font-bold"
                              x-text="`+${absentConfirmModal.queue.length} kolom PH menunggu`"></span>
                    </div>
                    <p class="text-[11px] text-slate-300 mt-1" x-text="getAbsentContextSubtitle()"></p>
                </div>
                <button type="button" @click="closeAbsentConfirm()" class="text-slate-400 hover:text-white text-xl leading-none cursor-pointer">&times;</button>
            </div>

            {{-- Bar Pemilih Tanggal PH & Info Jurnal Harian --}}
            <div class="px-5 py-3 bg-sky-50/80 border-b border-sky-200 text-xs space-y-2 shrink-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-bold text-sky-950">
                        <template x-if="absentConfirmModal.selectedDate">
                            <span>
                                Tanggal PH:
                                <span class="text-sky-700" x-text="absentConfirmModal.formattedDate || absentConfirmModal.selectedDate"></span>
                                <span x-show="absentConfirmModal.dayName" x-text="`(${absentConfirmModal.dayName})`"></span>
                            </span>
                        </template>
                        <template x-if="!absentConfirmModal.selectedDate">
                            <span class="text-amber-800">Kolom PH ini belum dihubungkan ke tanggal Jurnal Harian &amp; Absensi</span>
                        </template>
                    </div>
                    <a x-show="absentConfirmModal.attendanceUrl"
                       :href="absentConfirmModal.attendanceUrl"
                       target="_blank" rel="noopener"
                       class="text-[11px] font-bold text-sky-700 hover:text-sky-900 underline">
                        Buka Absensi Bulan Ini &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                    <div class="sm:col-span-8" x-show="classJournalDates.length > 0">
                        <select :value="absentConfirmModal.selectedDate || ''"
                                :disabled="absentConfirmModal.savingDate || absentConfirmModal.loadingDate"
                                @change="if ($event.target.value !== absentConfirmModal.selectedDate) onModalPhDateChange($event.target.value)"
                                class="w-full rounded-xl border border-sky-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800">
                            <option value="">-- Pilih tanggal dari Jurnal Harian &amp; Absensi kelas ini --</option>
                            <template x-for="dOpt in classJournalDates" :key="dOpt.date">
                                <option :value="dOpt.date" x-text="dOpt.option_label"></option>
                            </template>
                        </select>
                    </div>
                    <div :class="classJournalDates.length > 0 ? 'sm:col-span-4' : 'sm:col-span-12'">
                        <input type="date"
                               :value="absentConfirmModal.selectedDate || ''"
                               :disabled="absentConfirmModal.savingDate || absentConfirmModal.loadingDate"
                               @change="if ($event.target.value !== absentConfirmModal.selectedDate) onModalPhDateChange($event.target.value)"
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800">
                    </div>
                </div>

                <div x-show="absentConfirmModal.journalMaterial"
                     class="text-[11px] text-slate-700 bg-white/80 rounded-lg px-2.5 py-1.5 border border-sky-100">
                    <span class="font-bold text-slate-800">Materi Jurnal:</span>
                    <span x-text="absentConfirmModal.journalMaterial"></span>
                </div>

                <div x-show="absentConfirmModal.dateError"
                     class="text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-2.5 py-1.5"
                     x-text="absentConfirmModal.dateError"></div>
            </div>

            {{-- Isi Utama Modal --}}
            <div class="p-5 space-y-3.5 overflow-y-auto flex-1 text-xs">
                <template x-if="absentConfirmModal.loadingDate || absentConfirmModal.savingDate">
                    <div class="py-10 text-center text-slate-500 font-semibold">
                        Memuat data absensi &amp; menyinkronkan tanggal PH...
                    </div>
                </template>

                <template x-if="!absentConfirmModal.loadingDate && !absentConfirmModal.savingDate && !absentConfirmModal.selectedDate">
                    <div class="p-5 rounded-xl bg-slate-50 border border-slate-200 text-center text-slate-600 space-y-1">
                        <div class="font-bold text-slate-800">Pilih Tanggal Penilaian Harian Terlebih Dahulu</div>
                        <p class="text-[11px]">Pilih salah satu tanggal Jurnal / Absensi di atas untuk melihat daftar murid yang Sakit, Izin, Alpa, atau Dispensasi.</p>
                    </div>
                </template>

                <template x-if="!absentConfirmModal.loadingDate && !absentConfirmModal.savingDate && absentConfirmModal.selectedDate && absentConfirmModal.rows.length === 0">
                    <div class="p-5 rounded-xl bg-emerald-50 border border-emerald-200 text-center text-emerald-900 space-y-1">
                        <div class="font-bold">Seluruh Murid Hadir Penuh pada Tanggal Ini</div>
                        <p class="text-[11px]">Tidak ada murid berstatus Sakit (S), Izin (I), Alpa (A), maupun Dispensasi (D).</p>
                    </div>
                </template>

                <template x-if="!absentConfirmModal.loadingDate && !absentConfirmModal.savingDate && absentConfirmModal.rows.length > 0">
                    <div class="space-y-3">
                        {{-- Ringkasan Jumlah S / I / A / D --}}
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5 font-bold text-[11px]">
                                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-800 border border-slate-200">
                                    Total Terdampak: <strong x-text="absentConfirmModal.counts.affected"></strong>
                                </span>
                                <span x-show="absentConfirmModal.counts.s > 0" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 border border-amber-200">
                                    Sakit: <strong x-text="absentConfirmModal.counts.s"></strong>
                                </span>
                                <span x-show="absentConfirmModal.counts.i > 0" class="px-2.5 py-1 rounded-full bg-sky-100 text-sky-900 border border-sky-200">
                                    Izin: <strong x-text="absentConfirmModal.counts.i"></strong>
                                </span>
                                <span x-show="absentConfirmModal.counts.a > 0" class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-900 border border-rose-200">
                                    Alpa: <strong x-text="absentConfirmModal.counts.a"></strong>
                                </span>
                                <span x-show="absentConfirmModal.counts.d > 0" class="px-2.5 py-1 rounded-full bg-purple-100 text-purple-900 border border-purple-200">
                                    Dispensasi: <strong x-text="absentConfirmModal.counts.d"></strong>
                                </span>
                            </div>
                        </div>

                        {{-- Tombol Aksi Massal --}}
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <div class="text-[11px] font-bold text-slate-700">Aksi Cepat Massal (Terapkan ke Baris di Bawah):</div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button" @click="applyAbsentBulkAction('clear_all')"
                                        class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 font-bold text-[11px] cursor-pointer transition">
                                    Kosongkan Semua (Susulan)
                                </button>
                                <button type="button" x-show="absentConfirmModal.counts.d > 0"
                                        @click="applyAbsentBulkAction('clear_sia_keep_dispen')"
                                        class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-purple-50 text-purple-900 border border-purple-300 font-bold text-[11px] cursor-pointer transition">
                                    Kosongkan S/I/A (Pertahankan Dispen)
                                </button>
                                <button type="button" x-show="absentConfirmModal.counts.a > 0"
                                        @click="applyAbsentBulkAction('zero_alfa')"
                                        class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-rose-50 text-rose-800 border border-rose-300 font-bold text-[11px] cursor-pointer transition">
                                    Isi Alpa = 0
                                </button>
                                <div class="flex items-center gap-1 ml-auto">
                                    <input type="number" inputmode="decimal" step="0.01" min="0" max="100"
                                           x-model="absentConfirmModal.bulkFillScore"
                                           placeholder="0-100"
                                           class="w-20 rounded-lg border border-slate-300 bg-white px-2 py-1 text-center font-bold text-[11px]">
                                    <button type="button" @click="applyAbsentBulkAction('fill_custom')"
                                            class="px-2.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-bold text-[11px] cursor-pointer transition">
                                        Isi Semua
                                    </button>
                                    <button type="button" @click="applyAbsentBulkAction('fill_kktp')"
                                            class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] cursor-pointer transition"
                                            x-text="`Isi KKTP (${kktp})`"></button>
                                </div>
                            </div>
                        </div>

                        {{-- Tabel Per Siswa S/I/A/D --}}
                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <div class="max-h-[38vh] overflow-y-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] sticky top-0 z-10 border-b border-slate-200">
                                        <tr>
                                            <th class="px-2.5 py-2 text-center w-10">No</th>
                                            <th class="px-2.5 py-2 text-left">Nama Siswa</th>
                                            <th class="px-2.5 py-2 text-center w-24">Status</th>
                                            <th class="px-2.5 py-2 text-center w-20">Di Tabel</th>
                                            <th class="px-2.5 py-2 text-center w-32">Nilai PH Ini</th>
                                            <th class="px-2.5 py-2 text-center w-24">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="mRow in absentConfirmModal.rows" :key="mRow.student_id">
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-2.5 py-2 text-center font-bold text-slate-600" x-text="mRow.order_number"></td>
                                                <td class="px-2.5 py-2 font-semibold text-slate-900" x-text="mRow.full_name"></td>
                                                <td class="px-2.5 py-2 text-center">
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                          :class="mRow.badge_class"
                                                          x-text="`${mRow.status_code} - ${mRow.status_label}`"></span>
                                                </td>
                                                <td class="px-2.5 py-2 text-center font-semibold text-slate-500"
                                                    x-text="mRow.current_score === '' ? 'Kosong' : mRow.current_score"></td>
                                                <td class="px-2.5 py-2 text-center">
                                                    <input type="number" inputmode="decimal" step="0.01" min="0" max="100"
                                                           x-model="mRow.new_score"
                                                           placeholder="Kosong"
                                                           class="no-spinner w-24 rounded-lg border px-2 py-1 text-center font-bold text-xs focus:outline-none focus:ring-2 focus:ring-sky-500"
                                                           :class="mRow.new_score === '' || mRow.new_score === null ? 'bg-amber-50/70 border-amber-300 text-slate-500' : 'bg-white border-slate-300 text-slate-900'">
                                                </td>
                                                <td class="px-2.5 py-2 text-center">
                                                    <button type="button" @click="mRow.new_score = ''"
                                                            class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-900 font-bold text-[10px] cursor-pointer transition">
                                                        Kosongkan
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-500 leading-relaxed">
                            Sel yang dibiarkan kosong disimpan sebagai nilai kosong (ditandai kuning lembut di tabel &amp; <code>-</code> di PDF hingga nilai susulan diisi), dan sesuai aturan pembagi PH tetap dihitung setara 0 dalam rata-rata PH.
                        </p>
                    </div>
                </template>
            </div>

            {{-- Footer Aksi --}}
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-end gap-2 shrink-0 text-xs">
                <button type="button" @click="closeAbsentConfirm()"
                        class="px-4 py-2 rounded-xl border border-slate-300 bg-white font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer">
                    Batal
                </button>
                <button type="button"
                        @click="applyAbsentConfirm()"
                        :disabled="absentConfirmModal.loadingDate || absentConfirmModal.savingDate"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold shadow-2xs disabled:opacity-50 cursor-pointer transition">
                    <span x-text="absentConfirmModal.queue.length > 0 ? 'Terapkan & Lanjut Kolom Berikutnya' : (absentConfirmModal.context === 'save' ? 'Terapkan & Simpan Nilai' : 'Terapkan ke Kolom PH')"></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Toast Notifikasi Berhasil Paste dari Excel --}}
    <div x-show="pasteToast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="fixed bottom-6 right-6 z-50 max-w-sm sm:max-w-md bg-slate-900 text-white rounded-2xl shadow-2xl p-4 border border-slate-700 flex items-start gap-3">
        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 text-xs">
            <div class="font-bold text-sm text-white" x-text="pasteToast.title"></div>
            <div class="text-slate-300 mt-1 leading-relaxed" x-text="pasteToast.message"></div>
            <div class="mt-3 flex items-center gap-2">
                <button type="button" @click="saveScores()" :disabled="saving"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg cursor-pointer transition shadow-2xs">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Sekarang (Ctrl+S)'"></span>
                </button>
                <button type="button" @click="pasteToast.show = false" class="px-2.5 py-1.5 text-slate-400 hover:text-white font-semibold cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Cetak PDF & Download Excel Daftar Nilai --}}
    @include('partials.export-modal', [
        'modalVar' => 'exportModal',
        'modalTitle' => 'Cetak PDF & Download Excel — Daftar Nilai Kelas ' . $teachingClass->class_name,
        'pdfRoute' => route('exports.gradebook.pdf', $teachingClass),
        'excelRoute' => route('exports.gradebook.excel', $teachingClass),
        'defaultOrientation' => 'landscape',
    ])
</div>
@endif

@push('scripts')
<script>
function gradebookSpreadsheet(cfg) {
    return {
        rows: cfg.rows,
        savedSnapshot: [],
        phColIds: cfg.phColIds,
        ntColIds: cfg.ntColIds,
        phColumnsMeta: cfg.phColumns || [],
        phAbsentMap: cfg.phAbsentMap || {},
        classJournalDates: cfg.classJournalDates || [],
        absentStudentsUrl: cfg.absentStudentsUrl || '',
        confirmedAbsentPhCols: {},
        _savePromiseResolve: null,
        weights: cfg.weights,
        isAutoAttendance: Boolean(cfg.isAutoAttendance),
        sickLeaveTolerance: Number(cfg.sickLeaveTolerance || 0),
        kktp: cfg.kktp,
        isAdminReadOnly: Boolean(cfg.isAdminReadOnly),
        isDirty: false,
        dirtyCount: 0,
        dirtyStudentCount: 0,
        saving: false,
        justSaved: false,
        _savedTimer: null,
        savedMessage: '',
        editCol: null,
        weightsModal: false,
        importModal: false,
        exportModal: false,
        quickPasteModal: false,
        quickPasteTargetCol: '',
        quickPasteStartRow: 0,
        quickPasteText: '',
        quickPastePreviewCount: 0,
        pastedKeys: {},
        _pastedTimer: null,
        pasteToast: {
            show: false,
            title: '',
            message: '',
            _timer: null,
        },
        sameScorePrompt: {
            show: false,
            colKey: '',
            colLabel: '',
            score: null,
            targetCount: 0,
            _openedAt: 0,
        },
        absentConfirmModal: {
            show: false,
            colId: '',
            colLabel: '',
            context: 'manual',
            queue: [],
            selectedDate: '',
            formattedDate: '',
            dayName: '',
            journalMaterial: '',
            attendanceUrl: '',
            loadingDate: false,
            savingDate: false,
            dateError: '',
            counts: { s: 0, i: 0, a: 0, d: 0, absent: 0, dispen: 0, affected: 0 },
            rows: [],
            bulkFillScore: '',
        },
        _absen1InputTimer: null,
        activeStudentId: null,

        init() {
            this.savedSnapshot = this.cloneRows(this.rows);
            if (!this.isAdminReadOnly) {
                window.__gradeDirty = false;
                window.__workspaceDirty = false;
                window.__workspaceDirtyLabel = 'Daftar Nilai Peserta Didik';
                window.__workspaceSaveFn = () => this.saveScores();

                const editable = this.getEditableColumns();
                if (editable.length > 0) {
                    this.quickPasteTargetCol = editable[0].key;
                }

                window.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) {
                        e.preventDefault();
                        if (!this.saving && !this.absentConfirmModal.show) {
                            this.saveScores();
                        }
                    }
                });
            }
        },

        cloneRows(src) {
            return JSON.parse(JSON.stringify(src || []));
        },

        normalizeScoreVal(v) {
            if (v === null || v === undefined || String(v).trim() === '') return '';
            const n = Number(v);
            return Number.isNaN(n) ? String(v).trim() : String(Math.round(n * 100) / 100);
        },

        recalculateDirty() {
            if (this.isAdminReadOnly) {
                this.dirtyCount = 0;
                this.dirtyStudentCount = 0;
                this.isDirty = false;
                return;
            }
            const origMap = {};
            for (let i = 0; i < this.savedSnapshot.length; i++) {
                origMap[String(this.savedSnapshot[i].student_id)] = this.savedSnapshot[i];
            }

            let cellChanges = 0;
            let rowChanges = 0;

            for (let i = 0; i < this.rows.length; i++) {
                const curr = this.rows[i];
                const orig = origMap[String(curr.student_id)] || {};
                const currDyn = curr.dynamic_scores || {};
                const origDyn = orig.dynamic_scores || {};
                let rowDiff = 0;

                for (let p = 0; p < this.phColIds.length; p++) {
                    const cid = this.phColIds[p];
                    if (this.normalizeScoreVal(currDyn[cid]) !== this.normalizeScoreVal(origDyn[cid])) {
                        rowDiff++;
                    }
                }
                for (let n = 0; n < this.ntColIds.length; n++) {
                    const cid = this.ntColIds[n];
                    if (this.normalizeScoreVal(currDyn[cid]) !== this.normalizeScoreVal(origDyn[cid])) {
                        rowDiff++;
                    }
                }
                if (!this.isAutoAttendance) {
                    if (this.normalizeScoreVal(curr.attendance_score) !== this.normalizeScoreVal(orig.attendance_score)) {
                        rowDiff++;
                    }
                }
                if (this.normalizeScoreVal(curr.sts_score) !== this.normalizeScoreVal(orig.sts_score)) {
                    rowDiff++;
                }
                if (this.normalizeScoreVal(curr.sas_score) !== this.normalizeScoreVal(orig.sas_score)) {
                    rowDiff++;
                }

                if (rowDiff > 0) {
                    cellChanges += rowDiff;
                    rowChanges++;
                }
            }

            this.dirtyCount = cellChanges;
            this.dirtyStudentCount = rowChanges;
            this.isDirty = cellChanges > 0;
            window.__gradeDirty = this.isDirty;
            window.__workspaceDirty = this.isDirty;
        },

        markDirty() {
            if (this.isAdminReadOnly) return;
            this.justSaved = false;
            this.savedMessage = '';
            this.recalculateDirty();
        },

        revertChanges() {
            if (this.isAdminReadOnly || !this.isDirty) return;
            if (!window.confirm(`Batalkan ${this.dirtyCount} perubahan nilai pada ${this.dirtyStudentCount} murid dan kembalikan ke nilai tersimpan terakhir?`)) {
                return;
            }
            this.rows = this.cloneRows(this.savedSnapshot);
            this.recalculateDirty();
            this.savedMessage = 'Perubahan nilai dibatalkan';
        },

        async ensureSavedBeforeAction(actionLabel) {
            if (!this.isDirty) return true;
            const saveNow = window.confirm(
                `Ada ${this.dirtyCount} perubahan nilai (${this.dirtyStudentCount} murid) yang belum disimpan.\n\n` +
                `Klik "OK" untuk MENYIMPAN nilai terlebih dahulu sebelum ${actionLabel}, atau klik "Batal" untuk kembali ke tabel.`
            );
            if (!saveNow) return false;
            return await this.saveScores();
        },

        async openExportModal() {
            if (!this.isAdminReadOnly) {
                if (!(await this.ensureSavedBeforeAction('membuka Cetak PDF & Excel'))) return;
            }
            this.exportModal = true;
        },

        async openWeightsModal() {
            if (!(await this.ensureSavedBeforeAction('mengatur rumus persentase NA'))) return;
            this.weightsModal = true;
        },

        async openEditCol(colData) {
            if (!(await this.ensureSavedBeforeAction(`mengubah pengaturan kolom ${colData.label}`))) return;
            const liveMeta = (colData.type === 'PH' && colData.id) ? this.getPhMeta(colData.id) : null;
            this.editCol = liveMeta
                ? {
                    ...colData,
                    label: liveMeta.label,
                    topic_note: liveMeta.topic_note ?? '',
                    assessment_date: liveMeta.assessment_date ?? '',
                    assessment_date_label: liveMeta.assessment_date_label ?? '',
                }
                : colData;
        },

        async submitColumnAdd(e) {
            if (!(await this.ensureSavedBeforeAction('menambah kolom nilai baru'))) return;
            e.target.submit();
        },
        openQuickPasteModal() {
            if (this.isAdminReadOnly) return;
            const editable = this.getEditableColumns();
            if (editable.length > 0 && !this.quickPasteTargetCol) {
                this.quickPasteTargetCol = editable[0].key;
            }
            this.quickPasteModal = true;
        },
        getEditableColumns() {
            const cols = [];
            const phMap = {};
            (cfg.phColumns || []).forEach(c => { phMap[String(c.id)] = c.label; });
            const ntMap = {};
            (cfg.ntColumns || []).forEach(c => { ntMap[String(c.id)] = c.label; });

            (this.phColIds || []).forEach(cid => {
                cols.push({
                    key: `ph-${cid}`,
                    type: 'ph',
                    colId: String(cid),
                    label: phMap[String(cid)] || `PH ${cid}`
                });
            });

            if (!this.isAutoAttendance) {
                cols.push({
                    key: 'att',
                    type: 'att',
                    label: 'Nilai Kehadiran (Manual)'
                });
            }

            (this.ntColIds || []).forEach(cid => {
                cols.push({
                    key: `nt-${cid}`,
                    type: 'nt',
                    colId: String(cid),
                    label: ntMap[String(cid)] || `NT ${cid}`
                });
            });

            cols.push({ key: 'sts', type: 'sts', label: 'Nilai STS' });
            cols.push({ key: 'sas', type: 'sas', label: 'Nilai SAS' });

            return cols;
        },
        getColumnLabel(colKey) {
            const found = this.getEditableColumns().find(c => c.key === colKey);
            return found ? found.label : colKey;
        },
        flashPastedCells(cellKeys) {
            const map = {};
            cellKeys.forEach(k => { map[k] = true; });
            this.pastedKeys = map;
            clearTimeout(this._pastedTimer);
            this._pastedTimer = setTimeout(() => {
                this.pastedKeys = {};
            }, 3500);
        },
        showPasteToast(title, message) {
            this.pasteToast.title = title;
            this.pasteToast.message = message;
            this.pasteToast.show = true;
            clearTimeout(this.pasteToast._timer);
            this.pasteToast._timer = setTimeout(() => {
                this.pasteToast.show = false;
            }, 7000);
        },
        setCellValue(rowIdx, colKey, val) {
            if (rowIdx < 0 || rowIdx >= this.rows.length) return;
            const row = this.rows[rowIdx];
            if (colKey.startsWith('ph-')) {
                const colId = colKey.substring(3);
                if (!row.dynamic_scores) row.dynamic_scores = {};
                row.dynamic_scores[colId] = val;
            } else if (colKey.startsWith('nt-')) {
                const colId = colKey.substring(3);
                if (!row.dynamic_scores) row.dynamic_scores = {};
                row.dynamic_scores[colId] = val;
            } else if (colKey === 'att') {
                row.attendance_score = val;
            } else if (colKey === 'sts') {
                row.sts_score = val;
            } else if (colKey === 'sas') {
                row.sas_score = val;
            }
        },
        handleCellPaste(e, startRowIdx, startColKey) {
            if (this.isAdminReadOnly) return;
            const clipData = (e.clipboardData || window.clipboardData);
            if (!clipData) return;
            const rawText = clipData.getData('text');
            if (!rawText || rawText.trim() === '') return;

            const hasMultipleRows = /[\r\n]/.test(rawText.trim());
            const hasMultipleCols = /\t/.test(rawText);

            if (!hasMultipleRows && !hasMultipleCols) {
                let val = rawText.trim().replace(',', '.');
                const num = parseFloat(val);
                if (!isNaN(num)) {
                    e.preventDefault();
                    const cleanVal = Math.min(100, Math.max(0, Math.round(num * 100) / 100));
                    this.setCellValue(startRowIdx, startColKey, cleanVal);
                    this.markDirty();
                    this.flashPastedCells([`${startRowIdx}-${startColKey}`]);
                }
                return;
            }

            e.preventDefault();
            this.processPastedText(rawText, startRowIdx, startColKey);
        },
        processPastedText(rawText, startRowIdx, startColKey) {
            if (!rawText) return;
            const lines = rawText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            if (lines.length > 0 && lines[lines.length - 1].trim() === '') {
                lines.pop();
            }
            if (lines.length === 0) return;

            const editableCols = this.getEditableColumns();
            const startColIndex = editableCols.findIndex(c => c.key === startColKey);
            if (startColIndex === -1) return;

            let appliedCount = 0;
            let studentCount = 0;
            const affectedCellKeys = [];
            const touchedPhSet = new Set();

            for (let r = 0; r < lines.length; r++) {
                const targetRowIdx = Number(startRowIdx) + r;
                if (targetRowIdx >= this.rows.length) break;

                const line = lines[r];
                const cells = line.split('\t');
                let rowModified = false;

                for (let c = 0; c < cells.length; c++) {
                    const targetColIdx = startColIndex + c;
                    if (targetColIdx >= editableCols.length) break;

                    const col = editableCols[targetColIdx];
                    let cellText = cells[c].trim().replace(',', '.');

                    if (cellText === '' || cellText === '-') {
                        this.setCellValue(targetRowIdx, col.key, null);
                        affectedCellKeys.push(`${targetRowIdx}-${col.key}`);
                        if (col.type === 'ph' && col.colId) touchedPhSet.add(String(col.colId));
                        appliedCount++;
                        rowModified = true;
                    } else {
                        const num = parseFloat(cellText);
                        if (!isNaN(num)) {
                            const cleanVal = Math.min(100, Math.max(0, Math.round(num * 100) / 100));
                            this.setCellValue(targetRowIdx, col.key, cleanVal);
                            affectedCellKeys.push(`${targetRowIdx}-${col.key}`);
                            if (col.type === 'ph' && col.colId) touchedPhSet.add(String(col.colId));
                            appliedCount++;
                            rowModified = true;
                        }
                    }
                }

                if (rowModified) {
                    studentCount++;
                }
            }

            if (appliedCount > 0) {
                const touchedPhIds = Array.from(touchedPhSet);
                touchedPhIds.forEach((cid) => {
                    delete this.confirmedAbsentPhCols[cid];
                });

                this.markDirty();
                this.flashPastedCells(affectedCellKeys);
                const colLabel = this.getColumnLabel(startColKey);
                this.showPasteToast(
                    'Berhasil Menempel Nilai dari Excel',
                    `Berhasil memasukkan ${appliedCount} nilai untuk ${studentCount} murid ke kolom "${colLabel}". Nilai Akhir & KKTP langsung diperbarui.`
                );

                this.triggerAbsentQueueForPhCols(touchedPhIds, 'paste');
            }
        },
        updateQuickPastePreview() {
            if (!this.quickPasteText || this.quickPasteText.trim() === '') {
                this.quickPastePreviewCount = 0;
                return;
            }
            const lines = this.quickPasteText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            if (lines.length > 0 && lines[lines.length - 1].trim() === '') {
                lines.pop();
            }
            this.quickPastePreviewCount = lines.length;
        },
        applyQuickPaste() {
            if (this.quickPastePreviewCount === 0) return;
            const targetCol = this.quickPasteTargetCol || (this.getEditableColumns()[0] ? this.getEditableColumns()[0].key : 'sts');
            const pasteText = this.quickPasteText;
            const startRow = Number(this.quickPasteStartRow);
            this.quickPasteModal = false;
            this.quickPasteText = '';
            this.quickPastePreviewCount = 0;
            this.processPastedText(pasteText, startRow, targetCol);
        },
        setActiveStudent(studentId) {
            if (this.isAdminReadOnly) return;
            this.activeStudentId = studentId;
        },
        handleTableFocusOut(e, tbodyEl) {
            if ((this.sameScorePrompt && this.sameScorePrompt.show) || (this.absentConfirmModal && this.absentConfirmModal.show)) {
                return;
            }
            const nextTarget = e && e.relatedTarget ? e.relatedTarget : null;
            if (nextTarget && tbodyEl && tbodyEl.contains(nextTarget)) {
                return;
            }
            this.activeStudentId = null;
        },
        selectInput(e, studentId = null) {
            if (studentId !== null && studentId !== undefined) {
                this.setActiveStudent(studentId);
            }
            if (e && e.target && typeof e.target.select === 'function') {
                try { e.target.select(); } catch (_) {}
            }
        },
        focusGradeCell(rIdx, colKey) {
            if (rIdx < 0 || rIdx >= this.rows.length) return;
            const row = this.rows[rIdx];
            if (row) {
                this.setActiveStudent(row.student_id);
            }
            const el = document.getElementById(`grade-${rIdx}-${colKey}`);
            if (el) {
                el.focus();
                if (typeof el.select === 'function') {
                    try { el.select(); } catch (_) {}
                }
                if (typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }
        },
        handleGradeKey(e, rIdx, colKey) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                clearTimeout(this._absen1InputTimer);
                if (Number(rIdx) === 0 && !this.isAdminReadOnly && this.rows.length > 1) {
                    const rawVal = e.target ? e.target.value : null;
                    if (rawVal !== null && rawVal !== undefined && String(rawVal).trim() !== '') {
                        const num = parseFloat(String(rawVal).trim().replace(',', '.'));
                        if (!isNaN(num) && num >= 0 && num <= 100) {
                            this.triggerSameScorePrompt(colKey, num);
                            return;
                        }
                    }
                }
                this.focusGradeCell(e.shiftKey ? rIdx - 1 : rIdx + 1, colKey);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusGradeCell(rIdx + 1, colKey);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusGradeCell(rIdx - 1, colKey);
            }
        },
        checkAbsen1Input(rIdx, colKey, val) {
            if (this.isAdminReadOnly || Number(rIdx) !== 0 || this.rows.length <= 1) return;
            if (val === null || val === undefined || String(val).trim() === '') return;

            clearTimeout(this._absen1InputTimer);
            this._absen1InputTimer = setTimeout(() => {
                let cleanText = String(val).trim().replace(',', '.');
                const num = parseFloat(cleanText);
                if (isNaN(num) || num < 0 || num > 100) return;
                this.triggerSameScorePrompt(colKey, num);
            }, 600);
        },
        handleAbsen1Change(rIdx, colKey, val) {
            if (this.isAdminReadOnly || Number(rIdx) !== 0 || this.rows.length <= 1) return;
            clearTimeout(this._absen1InputTimer);
            if (val === null || val === undefined || String(val).trim() === '') return;

            let cleanText = String(val).trim().replace(',', '.');
            const num = parseFloat(cleanText);
            if (isNaN(num) || num < 0 || num > 100) return;

            this.triggerSameScorePrompt(colKey, num);
        },
        triggerSameScorePrompt(colKey, score) {
            if (this.isAdminReadOnly || this.rows.length <= 1) return;
            const cleanScore = Math.min(100, Math.max(0, Math.round(score * 100) / 100));

            this.setCellValue(0, colKey, cleanScore);

            if (this.sameScorePrompt.show && this.sameScorePrompt.colKey === colKey && this.sameScorePrompt.score === cleanScore) {
                return;
            }

            this.sameScorePrompt = {
                show: true,
                colKey: colKey,
                colLabel: this.getColumnLabel(colKey),
                score: cleanScore,
                targetCount: this.rows.length,
                _openedAt: Date.now(),
            };
        },
        applySameScoreToAll() {
            if (!this.sameScorePrompt.show) return;
            const colKey = this.sameScorePrompt.colKey;
            const score = this.sameScorePrompt.score;
            const colLabel = this.sameScorePrompt.colLabel;
            const affectedKeys = [];

            for (let i = 0; i < this.rows.length; i++) {
                this.setCellValue(i, colKey, score);
                affectedKeys.push(`${i}-${colKey}`);
            }

            this.markDirty();
            this.flashPastedCells(affectedKeys);
            this.sameScorePrompt.show = false;
            this.activeStudentId = null;

            this.showPasteToast(
                'Nilai Disamakan untuk Seluruh Murid',
                `Nilai ${score} berhasil diterapkan ke seluruh ${this.rows.length} murid pada kolom "${colLabel}". Tekan Ctrl+S untuk menyimpan.`
            );

            if (colKey.startsWith('ph-')) {
                const phColId = colKey.substring(3);
                delete this.confirmedAbsentPhCols[String(phColId)];
                if (this.shouldTriggerAbsentModalForColId(phColId)) {
                    this.openAbsentConfirmForColumn(phColId, 'same_score', []);
                }
            }
        },
        cancelSameScorePrompt() {
            if (!this.sameScorePrompt.show) return;
            const colKey = this.sameScorePrompt.colKey;
            this.sameScorePrompt.show = false;
            this.$nextTick(() => {
                this.focusGradeCell(1, colKey);
            });
        },
        attendanceTooltip(row) {
            const ref = row.attendance_ref || {};
            if (!ref.total || ref.total <= 0) {
                return this.isAutoAttendance
                    ? 'Mode Otomatis aktif: Belum ada pertemuan tercatat pada Menu Absensi kelas ini.'
                    : 'Input manual Nilai Kehadiran (0-100). Belum ada rekap dari Menu Absensi.';
            }
            const excessInfo = ref.excess_si > 0 ? `, Melebihi: ${ref.excess_si}x` : '';
            const prefix = this.isAutoAttendance ? 'Hitung Otomatis Absensi' : 'Referensi Menu Absensi';
            return `${prefix}: Hadir ${ref.present}/${ref.total} (Dispen: ${ref.dispen || 0}) | Sakit: ${ref.sakit || 0}, Izin: ${ref.izin || 0} (Toleransi S+I: ${ref.tolerance}x, Ditoleransi: ${ref.tolerated_si}x${excessInfo}) | Alfa: ${ref.alfa || 0} => Nilai Bertoleransi: ${ref.auto_score}`;
        },
        numVal(v) {
            if (v === null || v === '' || v === undefined) return 0;
            const n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        },
        calculateNA(row) {
            let phAvg = 0;
            if (this.phColIds.length > 0) {
                let sum = 0;
                this.phColIds.forEach((cid) => { sum += this.numVal(row.dynamic_scores[cid]); });
                phAvg = sum / this.phColIds.length;
            }
            let ntAvg = 0;
            if (this.ntColIds.length > 0) {
                let sum = 0;
                this.ntColIds.forEach((cid) => { sum += this.numVal(row.dynamic_scores[cid]); });
                ntAvg = sum / this.ntColIds.length;
            }
            const att = this.numVal(row.attendance_score);
            const sts = this.numVal(row.sts_score);
            const sas = this.numVal(row.sas_score);

            const raw = (phAvg * (this.weights.ph / 100))
                + (att * (this.weights.attendance / 100))
                + (ntAvg * (this.weights.nt / 100))
                + (sts * (this.weights.sts / 100))
                + (sas * (this.weights.sas / 100));

            const factor = Math.pow(10, this.weights.precision);
            return Math.round(raw * factor) / factor;
        },
        formatNA(val) {
            return Number(val).toFixed(this.weights.precision);
        },
        summaryStats() {
            if (this.rows.length === 0) return { avg: '0.00', tuntas: 0, remedial: 0 };
            let sum = 0, tuntas = 0, remedial = 0;
            this.rows.forEach((r) => {
                const na = this.calculateNA(r);
                sum += na;
                if (na >= this.kktp) tuntas++;
                else remedial++;
            });
            return {
                avg: (sum / this.rows.length).toFixed(2),
                tuntas,
                remedial,
            };
        },
        getPhMeta(colId) {
            return (this.phColumnsMeta || []).find((c) => String(c.id) === String(colId)) || null;
        },
        getAbsentInfoForCol(colId) {
            const meta = this.getPhMeta(colId);
            if (!meta || !meta.assessment_date) return null;
            return (this.phAbsentMap && this.phAbsentMap[meta.assessment_date]) ? this.phAbsentMap[meta.assessment_date] : null;
        },
        getPhPillText(colId) {
            const meta = this.getPhMeta(colId);
            if (!meta || !meta.assessment_date) return '+ Tgl PH';
            const dateLabel = meta.assessment_date_label || meta.assessment_date;
            const info = this.getAbsentInfoForCol(colId);
            if (!info) return dateLabel;
            const affected = Number(info.total_affected || 0);
            return affected > 0 ? `${dateLabel} • ${affected} S/I/A/D` : `${dateLabel} ✓`;
        },
        getPhPillTitle(colId) {
            const meta = this.getPhMeta(colId);
            if (!meta || !meta.assessment_date) {
                return 'Klik untuk menghubungkan tanggal Penilaian Harian dengan Jurnal Harian & Absensi S/I/A/D';
            }
            const info = this.getAbsentInfoForCol(colId);
            if (!info) {
                return `Tanggal PH: ${meta.assessment_date_label || meta.assessment_date} (Klik untuk konfirmasi siswa S/I/A/D)`;
            }
            const base = `${info.formatted_date || meta.assessment_date} • S:${info.sakit_count || 0} I:${info.izin_count || 0} A:${info.alfa_count || 0} D:${info.total_dispen || 0}`;
            return info.journal_material ? `${base} — ${info.journal_material}` : base;
        },
        getPhPillClass(colId) {
            const meta = this.getPhMeta(colId);
            if (!meta || !meta.assessment_date) {
                return 'bg-white/80 text-slate-500 border-slate-300 hover:border-sky-400 hover:text-sky-700';
            }
            const info = this.getAbsentInfoForCol(colId);
            if (info && Number(info.total_affected || 0) > 0) {
                return 'bg-amber-100 text-amber-900 border-amber-300 hover:bg-amber-200';
            }
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';
        },
        getAbsentContextSubtitle() {
            const ctx = this.absentConfirmModal.context;
            if (ctx === 'same_score') return 'Setelah menyamakan nilai semua murid, tentukan nilai untuk murid Sakit, Izin, Alpa, atau Dispensasi.';
            if (ctx === 'paste') return 'Setelah menempel nilai dari Excel, konfirmasi murid Sakit, Izin, Alpa, atau Dispensasi.';
            if (ctx === 'save') return 'Kolom PH bertanggal ini diubah. Konfirmasi nilai murid S/I/A/D sebelum menyimpan ke database.';
            return 'Kelola nilai Penilaian Harian khusus untuk murid berstatus Sakit (S), Izin (I), Alpa (A), atau Dispensasi (D).';
        },
        isPhColumnDirty(colId) {
            const origMap = {};
            for (let i = 0; i < this.savedSnapshot.length; i++) {
                origMap[String(this.savedSnapshot[i].student_id)] = this.savedSnapshot[i];
            }
            for (let i = 0; i < this.rows.length; i++) {
                const curr = this.rows[i];
                const orig = origMap[String(curr.student_id)] || {};
                const currVal = this.normalizeScoreVal((curr.dynamic_scores || {})[colId]);
                const origVal = this.normalizeScoreVal((orig.dynamic_scores || {})[colId]);
                if (currVal !== origVal) return true;
            }
            return false;
        },
        shouldTriggerAbsentModalForColId(colId) {
            const info = this.getAbsentInfoForCol(colId);
            return Boolean(info && Number(info.total_affected || 0) > 0);
        },
        findUnconfirmedModifiedPhColumns() {
            const result = [];
            (this.phColumnsMeta || []).forEach((c) => {
                const cid = String(c.id);
                if (c.assessment_date && this.shouldTriggerAbsentModalForColId(cid) && this.isPhColumnDirty(cid) && !this.confirmedAbsentPhCols[cid]) {
                    result.push(cid);
                }
            });
            return result;
        },
        triggerAbsentQueueForPhCols(colIds, context) {
            const filtered = [];
            const seen = {};
            (colIds || []).forEach((cid) => {
                const s = String(cid);
                if (!seen[s] && this.shouldTriggerAbsentModalForColId(s)) {
                    seen[s] = true;
                    filtered.push(s);
                }
            });
            if (filtered.length > 0) {
                this.openAbsentConfirmForColumn(filtered[0], context, filtered.slice(1));
            }
        },
        async fetchAbsentDetailForDate(date) {
            if (!date) return null;
            if (this.phAbsentMap && this.phAbsentMap[date]) {
                return this.phAbsentMap[date];
            }
            const res = await fetch(`${this.absentStudentsUrl}?date=${encodeURIComponent(date)}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) {
                throw new Error('Gagal memuat data absensi S/I/A/D untuk tanggal tersebut.');
            }
            const data = await res.json();
            this.phAbsentMap[date] = data;
            return data;
        },
        async assignDateToPhColumn(colId, newDate, overrides = null) {
            const meta = this.getPhMeta(colId);
            if (!meta || !meta.update_url) return null;

            const payload = {
                label: (overrides && overrides.label !== undefined) ? overrides.label : meta.label,
                topic_note: (overrides && overrides.topic_note !== undefined) ? overrides.topic_note : (meta.topic_note || ''),
                assessment_date: newDate || null,
            };

            const res = await fetch(meta.update_url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrf,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Gagal memperbarui tanggal kolom PH.');
            }
            if (data.column) {
                meta.label = data.column.label;
                meta.topic_note = data.column.topic_note;
                meta.assessment_date = data.column.assessment_date || '';
                meta.assessment_date_label = data.column.assessment_date_label || '';
            }
            delete this.confirmedAbsentPhCols[String(colId)];
            if (meta.assessment_date) {
                await this.fetchAbsentDetailForDate(meta.assessment_date);
            }
            return meta;
        },
        async openAbsentFromEditCol() {
            if (this.isAdminReadOnly || !this.editCol || this.editCol.type !== 'PH' || !this.editCol.id) return;
            const colId = String(this.editCol.id);
            const targetDate = this.editCol.assessment_date || '';
            const overrides = {
                label: this.editCol.label,
                topic_note: this.editCol.topic_note || '',
            };
            try {
                await this.assignDateToPhColumn(colId, targetDate, overrides);
                this.editCol = null;
                await this.openAbsentConfirmForColumn(colId, 'manual', []);
            } catch (e) {
                alert(e.message || 'Gagal memperbarui pengaturan tanggal PH.');
            }
        },
        async openAbsentConfirmForColumn(colId, context = 'manual', queue = []) {
            if (this.isAdminReadOnly) return;
            const meta = this.getPhMeta(colId);
            const selectedDate = (meta && meta.assessment_date) ? meta.assessment_date : '';
            this.pasteToast.show = false;

            this.absentConfirmModal = {
                show: true,
                colId: String(colId),
                colLabel: meta ? meta.label : `PH ${colId}`,
                context: context,
                queue: Array.isArray(queue) ? queue : [],
                selectedDate: selectedDate,
                formattedDate: '',
                dayName: '',
                journalMaterial: '',
                attendanceUrl: '',
                loadingDate: Boolean(selectedDate),
                savingDate: false,
                dateError: '',
                counts: { s: 0, i: 0, a: 0, d: 0, absent: 0, dispen: 0, affected: 0 },
                rows: [],
                bulkFillScore: '',
            };

            if (selectedDate) {
                await this.loadAbsentDateIntoModal(selectedDate);
            }
        },
        async onModalPhDateChange(newDate) {
            const colId = this.absentConfirmModal.colId;
            if (!colId) return;
            this.absentConfirmModal.dateError = '';
            this.absentConfirmModal.savingDate = true;
            try {
                await this.assignDateToPhColumn(colId, newDate || '');
                this.absentConfirmModal.selectedDate = newDate || '';
                if (newDate) {
                    await this.loadAbsentDateIntoModal(newDate);
                } else {
                    this.absentConfirmModal.formattedDate = '';
                    this.absentConfirmModal.dayName = '';
                    this.absentConfirmModal.journalMaterial = '';
                    this.absentConfirmModal.attendanceUrl = '';
                    this.absentConfirmModal.counts = { s: 0, i: 0, a: 0, d: 0, absent: 0, dispen: 0, affected: 0 };
                    this.absentConfirmModal.rows = [];
                }
            } catch (e) {
                this.absentConfirmModal.dateError = e.message || 'Gagal menghubungkan tanggal Penilaian Harian.';
            } finally {
                this.absentConfirmModal.savingDate = false;
            }
        },
        async loadAbsentDateIntoModal(date) {
            if (!date) return;
            this.absentConfirmModal.loadingDate = true;
            this.absentConfirmModal.dateError = '';
            try {
                const detail = await this.fetchAbsentDetailForDate(date);
                const colId = this.absentConfirmModal.colId;
                const rowByStudent = {};
                for (let i = 0; i < this.rows.length; i++) {
                    rowByStudent[String(this.rows[i].student_id)] = this.rows[i];
                }

                this.absentConfirmModal.formattedDate = detail.formatted_date || date;
                this.absentConfirmModal.dayName = detail.day_name || '';
                this.absentConfirmModal.journalMaterial = detail.journal_material || '';
                this.absentConfirmModal.attendanceUrl = detail.attendance_url || '';
                this.absentConfirmModal.counts = {
                    s: Number(detail.sakit_count || 0),
                    i: Number(detail.izin_count || 0),
                    a: Number(detail.alfa_count || 0),
                    d: Number(detail.total_dispen || 0),
                    absent: Number(detail.total_absent || 0),
                    dispen: Number(detail.total_dispen || 0),
                    affected: Number(detail.total_affected || 0),
                };

                this.absentConfirmModal.rows = (detail.students || []).map((st) => {
                    const tableRow = rowByStudent[String(st.id)];
                    const rawVal = (tableRow && tableRow.dynamic_scores) ? tableRow.dynamic_scores[colId] : null;
                    const strVal = (rawVal === null || rawVal === undefined || String(rawVal).trim() === '') ? '' : String(rawVal);
                    return {
                        student_id: String(st.id),
                        order_number: st.order_number ?? '-',
                        full_name: st.full_name || '',
                        status_code: st.status_code || '',
                        status_label: st.status_label || '',
                        badge_class: st.badge_class || 'bg-slate-100 text-slate-800 border-slate-200',
                        current_score: strVal,
                        new_score: strVal,
                    };
                });
            } catch (e) {
                this.absentConfirmModal.dateError = e.message || 'Gagal memuat daftar siswa S/I/A/D.';
                this.absentConfirmModal.rows = [];
            } finally {
                this.absentConfirmModal.loadingDate = false;
            }
        },
        normalizeModalScore(val) {
            if (val === null || val === undefined || String(val).trim() === '') return null;
            const num = parseFloat(String(val).trim().replace(',', '.'));
            if (isNaN(num)) return null;
            return Math.min(100, Math.max(0, Math.round(num * 100) / 100));
        },
        applyAbsentBulkAction(mode) {
            const modalRows = this.absentConfirmModal.rows || [];
            if (mode === 'clear_all') {
                modalRows.forEach((r) => { r.new_score = ''; });
            } else if (mode === 'clear_sia_keep_dispen') {
                modalRows.forEach((r) => {
                    if (r.status_code !== 'D') {
                        r.new_score = '';
                    }
                });
            } else if (mode === 'zero_alfa') {
                modalRows.forEach((r) => {
                    if (r.status_code === 'A') {
                        r.new_score = '0';
                    }
                });
            } else if (mode === 'fill_kktp') {
                const targetVal = String(this.kktp ?? 0);
                this.absentConfirmModal.bulkFillScore = targetVal;
                modalRows.forEach((r) => { r.new_score = targetVal; });
            } else if (mode === 'fill_custom') {
                const parsed = this.normalizeModalScore(this.absentConfirmModal.bulkFillScore);
                if (parsed === null) {
                    alert('Masukkan angka nilai 0 - 100 terlebih dahulu untuk mengisi seluruh siswa di daftar ini.');
                    return;
                }
                const targetVal = String(parsed);
                this.absentConfirmModal.bulkFillScore = targetVal;
                modalRows.forEach((r) => { r.new_score = targetVal; });
            }
        },
        async applyAbsentConfirm() {
            const colId = String(this.absentConfirmModal.colId || '');
            const ctx = this.absentConfirmModal.context || 'manual';
            const colLabel = this.absentConfirmModal.colLabel || `PH ${colId}`;
            const queue = Array.isArray(this.absentConfirmModal.queue) ? [...this.absentConfirmModal.queue] : [];
            const modalRows = this.absentConfirmModal.rows || [];

            const rowIndexMap = {};
            for (let i = 0; i < this.rows.length; i++) {
                rowIndexMap[String(this.rows[i].student_id)] = i;
            }

            const affectedKeys = [];
            let filledCount = 0;
            let clearedCount = 0;

            modalRows.forEach((mr) => {
                const idx = rowIndexMap[String(mr.student_id)];
                if (idx !== undefined) {
                    const cleanVal = this.normalizeModalScore(mr.new_score);
                    this.setCellValue(idx, `ph-${colId}`, cleanVal);
                    affectedKeys.push(`${idx}-ph-${colId}`);
                    if (cleanVal === null) clearedCount++;
                    else filledCount++;
                }
            });

            if (modalRows.length > 0) {
                this.markDirty();
                this.flashPastedCells(affectedKeys);
            }
            if (colId) {
                this.confirmedAbsentPhCols[colId] = true;
            }

            if (queue.length > 0) {
                const nextColId = queue[0];
                const restQueue = queue.slice(1);
                await this.openAbsentConfirmForColumn(nextColId, ctx, restQueue);
                return;
            }

            this.absentConfirmModal.show = false;

            if (ctx === 'save') {
                const ok = await this._performSaveScores();
                if (this._savePromiseResolve) {
                    this._savePromiseResolve(ok);
                    this._savePromiseResolve = null;
                }
            } else if (modalRows.length > 0) {
                this.showPasteToast(
                    'Nilai Siswa S/I/A/D Diterapkan',
                    `Kolom "${colLabel}" diperbarui: ${filledCount} siswa diisi nilainya, ${clearedCount} siswa dikosongkan.`
                );
            }
        },
        closeAbsentConfirm() {
            const wasSave = this.absentConfirmModal.context === 'save';
            this.absentConfirmModal.show = false;
            this.absentConfirmModal.queue = [];
            if (wasSave && this._savePromiseResolve) {
                this._savePromiseResolve(false);
                this._savePromiseResolve = null;
            }
        },
        async saveScores() {
            if (this.isAdminReadOnly || this.saving) return false;
            const pendingCols = this.findUnconfirmedModifiedPhColumns();
            if (pendingCols.length > 0) {
                if (this._savePromiseResolve) {
                    this._savePromiseResolve(false);
                    this._savePromiseResolve = null;
                }
                const promise = new Promise((resolve) => {
                    this._savePromiseResolve = resolve;
                });
                await this.openAbsentConfirmForColumn(pendingCols[0], 'save', pendingCols.slice(1));
                return promise;
            }
            return await this._performSaveScores();
        },
        async _performSaveScores() {
            if (this.isAdminReadOnly || this.saving) return false;
            this.saving = true;
            this.savedMessage = '';
            try {
                const res = await fetch(cfg.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrf,
                    },
                    body: JSON.stringify({ rows: this.rows }),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Gagal menyimpan nilai. Pastikan seluruh angka berada pada rentang 0 - 100.');
                    return false;
                } else {
                    if (this.isAutoAttendance && data.attendance_scores) {
                        this.rows.forEach((r) => {
                            const key = String(r.student_id);
                            if (Object.prototype.hasOwnProperty.call(data.attendance_scores, key)) {
                                r.attendance_score = data.attendance_scores[key];
                            }
                        });
                    }
                    this.savedSnapshot = this.cloneRows(this.rows);
                    this.confirmedAbsentPhCols = {};
                    this.recalculateDirty();
                    this.savedMessage = `Nilai tersimpan (${data.saved_at})`;
                    this.justSaved = true;
                    clearTimeout(this._savedTimer);
                    this._savedTimer = setTimeout(() => { this.justSaved = false; }, 3500);
                    return true;
                }
            } catch (e) {
                alert('Terjadi kesalahan jaringan saat menyimpan nilai.');
                return false;
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endpush
@endsection
