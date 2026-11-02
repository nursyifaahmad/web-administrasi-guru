@extends('layouts.app')

@section('title', 'Daftar Nilai — ' . $teachingClass->class_name)
@section('page_title', 'Menu 3: Daftar Nilai & Rumus NA — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', 'Kolom PH & NT dinamis, Nilai Kehadiran manual, rumus persentase kontrak belajar fleksibel, & indikator KKTP Admin.')

@section('content')
@include('partials.class-workspace-tabs')

@php
    $w = $gradebook['weight'];
    $phColumns = $gradebook['ph_columns'];
    $ntColumns = $gradebook['nt_columns'];
    $kktp = $gradebook['kktp'];
@endphp

<div class="space-y-4"
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
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span x-show="isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 font-bold text-[11px]">
                    Belum disimpan
                </span>
                <span x-show="savedMessage" x-cloak class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-semibold text-[11px]" x-text="savedMessage"></span>

                <button type="button" @click="saveScores()" :disabled="saving"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50 shadow-2xs transition">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Nilai'"></span>
                </button>
                <button type="button" @click="weightsModal = true"
                        class="px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition">
                    Atur Persentase Rumus NA
                </button>
                <button type="button" @click="importModal = true"
                        class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100">
                    Import Excel
                </button>
                <button type="button" @click="exportModal = true"
                        class="px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition">
                    Cetak PDF & Excel
                </button>
            </div>
        </div>

        {{-- Baris Kontrol Kolom Dinamis PH & NT + Statistik Real-time --}}
        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}">
                    @csrf
                    <input type="hidden" name="type" value="PH">
                    <button type="submit" onclick="if(window.__gradeDirty && !confirm('Simpan perubahan nilai terlebih dahulu agar tidak hilang. Lanjutkan tambah kolom PH?')) return false;"
                            class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 font-bold hover:bg-sky-100">
                        + Tambah Kolom PH ({{ $phColumns->count() }})
                    </button>
                </form>
                <form method="POST" action="{{ route('grades.columns.store', $teachingClass) }}">
                    @csrf
                    <input type="hidden" name="type" value="NT">
                    <button type="submit" onclick="if(window.__gradeDirty && !confirm('Simpan perubahan nilai terlebih dahulu agar tidak hilang. Lanjutkan tambah kolom NT?')) return false;"
                            class="px-3 py-1.5 rounded-lg bg-purple-50 text-purple-800 border border-purple-200 font-bold hover:bg-purple-100">
                        + Tambah Kolom NT ({{ $ntColumns->count() }})
                    </button>
                </form>
                <span class="text-[11px] text-slate-500">Klik judul kolom <strong>PH / NT</strong> pada tabel untuk mengubah nama/topik atau menghapus kolom.</span>
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
                        <th rowspan="2" class="sticky left-9 sm:left-10 z-20 bg-slate-100 px-2.5 sm:px-3 py-2.5 text-left border-r border-slate-300 min-w-[130px] sm:min-w-[195px]">Nama Lengkap</th>
                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 w-12">L/P</th>

                        @if($phColumns->count() > 0)
                            <th colspan="{{ $phColumns->count() }}" class="px-2 py-1.5 text-center border-b border-r border-slate-300 bg-sky-100/70 text-sky-950">
                                Nilai Harian / Keaktifan / Karakter ({{ rtrim(rtrim(number_format($w->weight_ph, 2), '0'), '.') }}%)
                            </th>
                        @endif

                        <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 bg-emerald-50 text-emerald-950 w-24">
                            Nilai Kehadiran<br>
                            <span class="text-[10px] font-normal text-emerald-700">({{ rtrim(rtrim(number_format($w->weight_attendance, 2), '0'), '.') }}%)</span>
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
                                        @click="editCol = @js([
                                            'label' => $col->label,
                                            'topic_note' => $col->topic_note,
                                            'type' => 'PH',
                                            'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                            'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                        ])"
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
                                        @click="editCol = @js([
                                            'label' => $col->label,
                                            'topic_note' => $col->topic_note,
                                            'type' => 'NT',
                                            'update_url' => route('grades.columns.update', [$teachingClass, $col]),
                                            'delete_url' => route('grades.columns.destroy', [$teachingClass, $col]),
                                        ])"
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
                            <td class="sticky left-9 sm:left-10 z-10 bg-white px-2.5 sm:px-3 py-1.5 font-bold text-slate-900 border-r border-slate-200 truncate max-w-[135px] sm:max-w-[220px]" x-text="row.full_name"></td>
                            <td class="px-2 py-1.5 text-center font-semibold text-slate-700 border-r border-slate-200" x-text="row.gender"></td>

                            {{-- Input Dinamis PH --}}
                            <template x-for="colId in phColIds" :key="'ph-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" min="0" max="100" step="0.01"
                                           x-model="row.dynamic_scores[colId]"
                                           @input="markDirty()"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500"
                                           :class="(row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '') ? 'bg-amber-50/35 text-slate-500' : 'bg-white text-slate-900'">
                                </td>
                            </template>

                            {{-- Input Manual Nilai Kehadiran + Info Rekap Absen --}}
                            <td class="p-0 border-r border-slate-200 relative group">
                                <input type="number" min="0" max="100" step="0.01"
                                       x-model="row.attendance_score"
                                       @input="markDirty()"
                                       :title="row.attendance_ref.total > 0 ? `Referensi Menu Absensi: Hadir ${row.attendance_ref.present}/${row.attendance_ref.total} (${row.attendance_ref.percentage}%)` : 'Input manual Nilai Kehadiran (0-100)'"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-bold text-emerald-900 bg-emerald-50/25 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </td>

                            {{-- Input Dinamis NT --}}
                            <template x-for="colId in ntColIds" :key="'nt-' + row.student_id + '-' + colId">
                                <td class="p-0 border-r border-slate-200">
                                    <input type="number" min="0" max="100" step="0.01"
                                           x-model="row.dynamic_scores[colId]"
                                           @input="markDirty()"
                                           placeholder="0"
                                           class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
                                           :class="(row.dynamic_scores[colId] === null || row.dynamic_scores[colId] === '') ? 'bg-amber-50/35 text-slate-500' : 'bg-white text-slate-900'">
                                </td>
                            </template>

                            {{-- Input STS & SAS --}}
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" min="0" max="100" step="0.01"
                                       x-model="row.sts_score"
                                       @input="markDirty()"
                                       placeholder="0"
                                       class="no-spinner w-full h-8 px-1.5 text-center text-xs font-semibold bg-amber-50/15 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </td>
                            <td class="p-0 border-r border-slate-200">
                                <input type="number" min="0" max="100" step="0.01"
                                       x-model="row.sas_score"
                                       @input="markDirty()"
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
             class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden max-h-[92vh] flex flex-col"
             x-data="{
                wPh: {{ (float) $w->weight_ph }},
                wAtt: {{ (float) $w->weight_attendance }},
                wNt: {{ (float) $w->weight_nt }},
                wSts: {{ (float) $w->weight_sts }},
                wSas: {{ (float) $w->weight_sas }},
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

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="weightsModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" :disabled="Math.abs(totalW() - 100) >= 0.01"
                            class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-40">
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
        phColIds: cfg.phColIds,
        ntColIds: cfg.ntColIds,
        weights: cfg.weights,
        kktp: cfg.kktp,
        isDirty: false,
        saving: false,
        savedMessage: '',
        editCol: null,
        weightsModal: false,
        importModal: false,
        exportModal: false,

        markDirty() {
            this.isDirty = true;
            window.__gradeDirty = true;
            this.savedMessage = '';
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
                } else {
                    this.isDirty = false;
                    window.__gradeDirty = false;
                    this.savedMessage = `Nilai tersimpan (${data.saved_at})`;
                }
            } catch (e) {
                alert('Terjadi kesalahan jaringan saat menyimpan nilai.');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endpush
@endsection
