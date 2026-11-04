@extends('layouts.app')

@section('title', 'Nilai — ' . $teachingClass->class_name)
@section('page_title', 'Menu 3: Nilai — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', 'Kolom PH & NT dinamis, Nilai Kehadiran manual atau otomatis bertoleransi S/I, rumus kontrak belajar fleksibel, & indikator KKTP Admin.')

@section('content')
@include('partials.class-workspace-tabs')

@php
    $w = $gradebook['weight'];
    $phColumns = $gradebook['ph_columns'];
    $ntColumns = $gradebook['nt_columns'];
    $kktp = $gradebook['kktp'];
    $isAutoAtt = $w->isAutoAttendance();
    $tolValue = (int) ($w->sick_leave_tolerance ?? 3);
@endphp

<div class="space-y-4"
     :class="(isDirty || saving || justSaved) ? 'pb-24' : ''"
     x-data="gradebookSpreadsheet({
        rows: @js($alpineRows),
        phColIds: @js($phColumns->pluck('id')->map(fn($id) => (string)$id)->values()),
        ntColIds: @js($ntColumns->pluck('id')->map(fn($id) => (string)$id)->values()),
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
                    <button type="button" @click="openWeightsModal()"
                            class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold hover:bg-emerald-100 transition"
                            title="Klik untuk mengubah mode atau batas toleransi Sakit/Izin">
                        Kehadiran: Otomatis Absensi (Toleransi S+I: {{ $tolValue }}x)
                    </button>
                @else
                    <button type="button" @click="openWeightsModal()"
                            class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 font-bold hover:bg-amber-100 transition"
                            title="Klik untuk mengaktifkan Nilai Kehadiran otomatis dengan toleransi Sakit/Izin">
                        Kehadiran: Input Manual
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs w-full lg:w-auto">
                <span x-show="isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 font-bold text-[11px]">
                    Belum disimpan (<span x-text="dirtyCount"></span> nilai / <span x-text="dirtyStudentCount"></span> murid)
                </span>
                <span x-show="savedMessage && !isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-semibold text-[11px]" x-text="savedMessage"></span>

                <button type="button" @click="saveScores()" :disabled="saving" title="Pintasan keyboard: Ctrl + S"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50 shadow-2xs transition text-center">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Nilai'"></span>
                </button>
                <button type="button" @click="openWeightsModal()"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition text-center">
                    Atur Persentase Rumus NA
                </button>
                <button type="button" @click="importModal = true"
                        class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100">
                    Import Excel
                </button>
                <button type="button" @click="openExportModal()"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition text-center">
                    Cetak PDF & Excel
                </button>
            </div>
        </div>

        {{-- Baris Kontrol Kolom Dinamis PH & NT + Statistik Real-time --}}
        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}" data-skip-dirty-guard="1" @submit.prevent="submitColumnAdd($event)">
                    @csrf
                    <input type="hidden" name="type" value="PH">
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 font-bold hover:bg-sky-100">
                        + Tambah Kolom PH ({{ $phColumns->count() }})
                    </button>
                </form>
                <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}" data-skip-dirty-guard="1" @submit.prevent="submitColumnAdd($event)">
                    @csrf
                    <input type="hidden" name="type" value="NT">
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg bg-purple-50 text-purple-800 border border-purple-200 font-bold hover:bg-purple-100">
                        + Tambah Kolom NT ({{ $ntColumns->count() }})
                    </button>
                </form>
                <span class="text-[11px] text-slate-500">Klik judul kolom <strong>PH / NT</strong> untuk ubah/hapus &bull; Tekan <strong>Enter</strong> / <strong>&darr;</strong> saat isi nilai untuk pindah ke murid berikutnya.</span>
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
                            <th class="px-1.5 py-1.5 text-center border-r border-slate-200 bg-sky-50/50 min-w-[68px]">
                                <button type="button"
                                        @click="openEditCol(@js([
                                            'label' => $col->label,
                                            'topic_note' => $col->topic_note,
                                            'type' => 'PH',
                                            'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                            'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                        ]))"
                                        class="w-full hover:text-sky-700 group" title="{{ $col->topic_note ?: 'Klik untuk ubah / hapus kolom' }}">
                                    <div class="font-extrabold flex items-center justify-center gap-0.5">
                                        <span>{{ $col->label }}</span>
                                        <span class="text-[10px] text-slate-400 group-hover:text-sky-600">&#9998;</span>
                                    </div>
                                    @if($col->topic_note)
                                        <div class="text-[9px] font-normal text-slate-500 truncate max-w-[72px] mx-auto">{{ $col->topic_note }}</div>
                                    @endif
                                </button>
                            </th>
                        @endforeach

                        @foreach($ntColumns as $col)
                            <th class="px-1.5 py-1.5 text-center border-r border-slate-200 bg-purple-50/50 min-w-[68px]">
                                <button type="button"
                                        @click="openEditCol(@js([
                                            'label' => $col->label,
                                            'topic_note' => $col->topic_note,
                                            'type' => 'NT',
                                            'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                            'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                        ]))"
                                        class="w-full hover:text-purple-700 group" title="{{ $col->topic_note ?: 'Klik untuk ubah / hapus kolom' }}">
                                    <div class="font-extrabold flex items-center justify-center gap-0.5">
                                        <span>{{ $col->label }}</span>
                                        <span class="text-[10px] text-slate-400 group-hover:text-purple-600">&#9998;</span>
                                    </div>
                                    @if($col->topic_note)
                                        <div class="text-[9px] font-normal text-slate-500 truncate max-w-[72px] mx-auto">{{ $col->topic_note }}</div>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-if="rows.length === 0">
                        <tr>
                            <td :colspan="7 + phColIds.length + ntColIds.length" class="px-5 py-10 text-center text-slate-500">
                                Belum ada peserta didik di kelas ini. Tambahkan murid pada tab <strong>Peserta Didik & Wali Kelas</strong>.
                            </td>
                        </tr>
                    </template>
                    <template x-for="(row, rIdx) in rows" :key="row.student_id">
                        <tr class="hover:bg-slate-50/80">
                            <td class="sticky left-0 z-10 bg-white px-2 py-1.5 text-center font-bold text-slate-600 border-r border-slate-200" x-text="row.no"></td>
                            <td class="sticky left-9 sm:left-10 z-10 bg-white px-2.5 sm:px-3 py-1.5 font-bold text-slate-900 border-r border-slate-200 truncate max-w-[135px] sm:max-w-[220px] sticky-col-shadow" x-text="row.full_name"></td>
                            <td class="px-2 py-1.5 text-center font-semibold text-slate-700 border-r border-slate-200" x-text="row.gender"></td>

                            {{-- Input Dinamis PH --}}
                            <template x-for="colId in phColIds" :key="'ph-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                           :id="`grade-${rIdx}-ph-${colId}`"
                                           x-model="row.dynamic_scores[colId]"
                                           @input="markDirty()"
                                           @focus="selectInput($event)"
                                           @keydown="handleGradeKey($event, rIdx, `ph-${colId}`)"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500"
                                           :class="(row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '') ? 'bg-amber-50/35 text-slate-500' : 'bg-white text-slate-900'">
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
                                           @input="markDirty()"
                                           @focus="selectInput($event)"
                                           @keydown="handleGradeKey($event, rIdx, 'att')"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-bold text-emerald-900 bg-emerald-50/25 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                </template>
                            </td>

                            {{-- Input Dinamis NT --}}
                            <template x-for="colId in ntColIds" :key="'nt-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                           :id="`grade-${rIdx}-nt-${colId}`"
                                           x-model="row.dynamic_scores[colId]"
                                           @input="markDirty()"
                                           @focus="selectInput($event)"
                                           @keydown="handleGradeKey($event, rIdx, `nt-${colId}`)"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
                                           :class="(row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '') ? 'bg-amber-50/35 text-slate-500' : 'bg-white text-slate-900'">
                                </td>
                            </template>

                            {{-- Input STS & SAS --}}
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                       :id="`grade-${rIdx}-sts`"
                                       x-model="row.sts_score"
                                       @input="markDirty()"
                                       @focus="selectInput($event)"
                                       @keydown="handleGradeKey($event, rIdx, 'sts')"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold bg-amber-50/15 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </td>
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" inputmode="decimal" min="0" max="100" step="0.01" enterkeyhint="next"
                                       :id="`grade-${rIdx}-sas`"
                                       x-model="row.sas_score"
                                       @input="markDirty()"
                                       @focus="selectInput($event)"
                                       @keydown="handleGradeKey($event, rIdx, 'sas')"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold bg-amber-50/15 focus:outline-none focus:ring-2 focus:ring-amber-500">
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

    {{-- Bar Simpan Melayang (Sticky Floating Action Bar) --}}
    <div x-show="isDirty || saving || justSaved"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-1.5rem)] max-w-2xl">
        <div class="rounded-2xl bg-slate-900/95 text-white border shadow-2xl px-4 py-3 backdrop-blur-md flex flex-wrap items-center justify-between gap-3 text-xs"
             :class="isDirty ? 'border-amber-400/80' : 'border-emerald-500/70'">
            <template x-if="saving">
                <div class="flex items-center gap-2.5 font-bold text-emerald-300">
                    <svg class="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span>Menyimpan daftar nilai &amp; memperbarui Nilai Akhir (NA)...</span>
                </div>
            </template>

            <template x-if="!saving && isDirty">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                    <div>
                        <span class="font-extrabold text-amber-300"><span x-text="dirtyCount"></span> nilai diubah (<span x-text="dirtyStudentCount"></span> murid) &mdash; belum disimpan</span>
                        <span class="hidden sm:inline text-slate-300"> &bull; jangan lupa klik Simpan</span>
                    </div>
                </div>
            </template>

            <template x-if="!saving && !isDirty && justSaved">
                <div class="flex items-center gap-2 font-bold text-emerald-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="savedMessage"></span>
                </div>
            </template>

            <div x-show="isDirty && !saving" class="flex items-center gap-2 ml-auto">
                <button type="button" @click="revertChanges()"
                        class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold transition cursor-pointer">
                    Urungkan
                </button>
                <button type="button" @click="saveScores()"
                        class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold shadow-sm transition cursor-pointer">
                    Simpan Nilai (Ctrl+S)
                </button>
            </div>
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

    {{-- Modal Cetak PDF & Download Excel Daftar Nilai --}}
    @include('partials.export-modal', [
        'modalVar' => 'exportModal',
        'modalTitle' => 'Cetak PDF & Download Excel — Daftar Nilai Kelas ' . $teachingClass->class_name,
        'pdfRoute' => route('exports.gradebook.pdf', $teachingClass),
        'excelRoute' => route('exports.gradebook.excel', $teachingClass),
        'defaultOrientation' => 'landscape',
    ])
</div>

@push('scripts')
<script>
function gradebookSpreadsheet(cfg) {
    return {
        rows: cfg.rows,
        savedSnapshot: [],
        phColIds: cfg.phColIds,
        ntColIds: cfg.ntColIds,
        weights: cfg.weights,
        isAutoAttendance: Boolean(cfg.isAutoAttendance),
        sickLeaveTolerance: Number(cfg.sickLeaveTolerance || 0),
        kktp: cfg.kktp,
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

        init() {
            this.savedSnapshot = this.cloneRows(this.rows);
            window.__gradeDirty = false;
            window.__workspaceDirty = false;
            window.__workspaceDirtyLabel = 'Daftar Nilai Peserta Didik';
            window.__workspaceSaveFn = () => this.saveScores();

            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    if (!this.saving) {
                        this.saveScores();
                    }
                }
            });
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
            this.justSaved = false;
            this.savedMessage = '';
            this.recalculateDirty();
        },

        revertChanges() {
            if (!this.isDirty) return;
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
            if (!(await this.ensureSavedBeforeAction('membuka Cetak PDF & Excel'))) return;
            this.exportModal = true;
        },

        async openWeightsModal() {
            if (!(await this.ensureSavedBeforeAction('mengatur rumus persentase NA'))) return;
            this.weightsModal = true;
        },

        async openEditCol(colData) {
            if (!(await this.ensureSavedBeforeAction(`mengubah pengaturan kolom ${colData.label}`))) return;
            this.editCol = colData;
        },

        async submitColumnAdd(e) {
            if (!(await this.ensureSavedBeforeAction('menambah kolom nilai baru'))) return;
            e.target.submit();
        },
        selectInput(e) {
            if (e && e.target && typeof e.target.select === 'function') {
                try { e.target.select(); } catch (_) {}
            }
        },
        focusGradeCell(rIdx, colKey) {
            if (rIdx < 0 || rIdx >= this.rows.length) return;
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
                this.focusGradeCell(e.shiftKey ? rIdx - 1 : rIdx + 1, colKey);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusGradeCell(rIdx + 1, colKey);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusGradeCell(rIdx - 1, colKey);
            }
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
        async saveScores() {
            if (this.saving) return false;
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
