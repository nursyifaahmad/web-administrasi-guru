@extends('layouts.app')

@section('title', 'Absensi — ' . $teachingClass->class_name)
@section('page_title', 'Menu 2: Absensi Peserta Didik — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', 'Kode: "." = Hadir, "d" = Dispen (tetap dihitung Hadir), "s" = Sakit, "i" = Izin, "a" = Alfa. Otomatis menyinkronkan Jurnal Harian.')

@section('content')
@include('partials.class-workspace-tabs')

@php
    $monthName = \App\Support\IndonesianDate::monthName($month);
@endphp

<div class="space-y-4"
     x-data="attendanceSpreadsheet({
        students: @js($alpineStudents),
        initialCells: @js($alpineCells),
        daysInMonth: @js($matrix['days_in_month']),
        year: @js($year),
        month: @js($month),
        saveUrl: @js(route('attendances.bulk-save', $teachingClass)),
        csrf: @js(csrf_token())
     })">

    {{-- Sub-tab Bulanan vs Rekap Semester + Pemilih Bulan & Ekspor --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="{{ route('attendances.index', [$teachingClass, 'tab' => 'monthly', 'month' => $month, 'year' => $year]) }}"
               class="px-3.5 py-2 rounded-xl font-bold transition {{ $tab === 'monthly' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Matriks Bulanan (Tanggal 1–{{ $matrix['days_in_month'] }})
            </a>
            <a href="{{ route('attendances.index', [$teachingClass, 'tab' => 'recap', 'month' => $month, 'year' => $year]) }}"
               class="px-3.5 py-2 rounded-xl font-bold transition {{ $tab === 'recap' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Rekapitulasi 1 Semester
            </a>

            @if($tab === 'monthly')
                <form method="GET" action="{{ route('attendances.index', $teachingClass) }}" class="flex items-center gap-1.5 ml-1">
                    <input type="hidden" name="tab" value="monthly">
                    <select name="month" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800">
                        @foreach(\App\Support\IndonesianDate::MONTHS as $mNum => $mLbl)
                            <option value="{{ $mNum }}" @selected($month === $mNum)>{{ $mLbl }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="year" value="{{ $year }}" min="2020" max="2099" onchange="this.form.submit()"
                           class="w-24 rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800">
                </form>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if($tab === 'monthly')
                <span x-show="isDirty" x-cloak class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 font-bold text-[11px]">
                    Ada perubahan belum disimpan
                </span>
                <span x-show="savedMessage" x-cloak class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-semibold text-[11px]" x-text="savedMessage"></span>

                <button type="button" @click="saveAll()" :disabled="saving"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50 shadow-2xs transition">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Absensi'"></span>
                </button>
                <button type="button" @click="importModal = true"
                        class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100">
                    Import Excel
                </button>
            @endif
            <button type="button" @click="exportModal = true"
                    class="px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition">
                Cetak PDF & Download Excel
            </button>
        </div>
    </div>

    @if($tab === 'monthly')
        {{-- Panduan Kode & Mode Sentuh Cepat HP (Tap-to-Fill) --}}
        <div class="bg-slate-900 text-slate-200 rounded-2xl px-4 py-3 text-xs space-y-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold text-white">Mode Input HP / Laptop:</span>
                    <button type="button" @click="tapStamp = null"
                            class="px-2.5 py-1 rounded-lg font-bold transition border"
                            :class="tapStamp === null ? 'bg-white text-slate-900 border-white' : 'bg-slate-800 text-slate-300 border-slate-700'">
                        Ketik Keyboard
                    </button>
                    <button type="button" @click="tapStamp = '.'"
                            class="px-2.5 py-1 rounded-lg font-extrabold transition border"
                            :class="tapStamp === '.' ? 'bg-emerald-500 text-white border-emerald-400 ring-2 ring-emerald-300' : 'bg-emerald-500/20 text-emerald-300 border-emerald-400/30'">
                        Tap "." Hadir
                    </button>
                    <button type="button" @click="tapStamp = 'd'"
                            class="px-2.5 py-1 rounded-lg font-extrabold transition border"
                            :class="tapStamp === 'd' ? 'bg-sky-500 text-white border-sky-400 ring-2 ring-sky-300' : 'bg-sky-500/20 text-sky-300 border-sky-400/30'">
                        Tap "d" Dispen
                    </button>
                    <button type="button" @click="tapStamp = 's'"
                            class="px-2.5 py-1 rounded-lg font-extrabold transition border"
                            :class="tapStamp === 's' ? 'bg-amber-500 text-white border-amber-400 ring-2 ring-amber-300' : 'bg-amber-500/20 text-amber-300 border-amber-400/30'">
                        Tap "s" Sakit
                    </button>
                    <button type="button" @click="tapStamp = 'i'"
                            class="px-2.5 py-1 rounded-lg font-extrabold transition border"
                            :class="tapStamp === 'i' ? 'bg-purple-500 text-white border-purple-400 ring-2 ring-purple-300' : 'bg-purple-500/20 text-purple-300 border-purple-400/30'">
                        Tap "i" Izin
                    </button>
                    <button type="button" @click="tapStamp = 'a'"
                            class="px-2.5 py-1 rounded-lg font-extrabold transition border"
                            :class="tapStamp === 'a' ? 'bg-rose-500 text-white border-rose-400 ring-2 ring-rose-300' : 'bg-rose-500/20 text-rose-300 border-rose-400/30'">
                        Tap "a" Alfa
                    </button>
                    <button type="button" @click="tapStamp = ''"
                            class="px-2.5 py-1 rounded-lg font-bold transition border"
                            :class="tapStamp === '' ? 'bg-slate-600 text-white border-slate-400 ring-2 ring-slate-300' : 'bg-slate-800 text-slate-300 border-slate-700'">
                        Tap Hapus
                    </button>
                </div>
                <div class="text-[11px] text-slate-400">
                    Tip: Di HP, pilih tombol <strong>Tap</strong> di atas lalu sentuh kotak tanggal murid tanpa perlu membuka keyboard!
                </div>
            </div>
        </div>

        {{-- Spreadsheet Matriks Bulanan --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto spreadsheet-scroll">
                <table class="w-full text-xs border-collapse select-none">
                    <thead>
                        <tr class="bg-slate-100 text-slate-800 text-[11px] uppercase border-b border-slate-300">
                            <th rowspan="2" class="sticky left-0 z-20 bg-slate-100 px-2 py-2.5 text-center border-r border-slate-300 w-9 sm:w-10">No</th>
                            <th rowspan="2" class="sticky left-9 sm:left-10 z-20 bg-slate-100 px-2.5 sm:px-3 py-2.5 text-left border-r border-slate-300 min-w-[130px] sm:min-w-[185px]">Nama Lengkap</th>
                            <th rowspan="2" class="px-2.5 py-2.5 text-center border-r border-slate-300 min-w-[90px]">Nama Panggilan</th>
                            <th rowspan="2" class="px-2 py-2.5 text-center border-r border-slate-300 w-10">L/P</th>
                            <th :colspan="daysInMonth" class="px-2 py-1.5 text-center border-b border-r border-slate-300 bg-slate-200/70 font-extrabold">
                                Bulan {{ $monthName }} {{ $year }} — Klik Angka Tanggal untuk Isi Cepat
                            </th>
                            <th rowspan="2" class="px-2.5 py-2.5 text-center border-r border-slate-300 bg-emerald-50 text-emerald-900 min-w-[60px]" title="Jumlah Hadir = Hadir (.) + Dispen (d)">
                                Jml Hadir
                            </th>
                            <th colspan="3" class="px-2 py-1.5 text-center border-b border-slate-300 bg-rose-50 text-rose-900">
                                Ketidakhadiran
                            </th>
                        </tr>
                        <tr class="bg-slate-50 text-slate-700 text-[10px] border-b border-slate-300">
                            <template x-for="d in daysArray" :key="'hdr-'+d">
                                <th class="p-0 border-r border-slate-200 w-7 min-w-[28px] text-center">
                                    <div class="relative" x-data="{ menu: false }">
                                        <button type="button" @click="menu = !menu"
                                                class="w-full py-1.5 font-extrabold hover:bg-emerald-100 hover:text-emerald-900 transition"
                                                :class="columnHasData(d) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600'"
                                                x-text="d"></button>
                                        <div x-show="menu" x-cloak @click.away="menu = false"
                                             class="absolute left-0 top-full mt-1 z-30 w-40 rounded-xl bg-white border border-slate-200 shadow-lg p-1.5 text-left font-normal normal-case space-y-1">
                                            <div class="px-2 py-1 text-[10px] font-bold text-slate-500 border-b border-slate-100">Tanggal <span x-text="d"></span> {{ $monthName }}</div>
                                            <button type="button" @click="fillDay(d, '.'); menu = false"
                                                    class="w-full px-2 py-1.5 rounded-lg text-[11px] font-bold text-emerald-700 hover:bg-emerald-50 text-left">
                                                Set Semua Hadir (.)
                                            </button>
                                            <button type="button" @click="fillDay(d, ''); menu = false"
                                                    class="w-full px-2 py-1.5 rounded-lg text-[11px] font-semibold text-rose-600 hover:bg-rose-50 text-left">
                                                Kosongkan Tanggal Ini
                                            </button>
                                        </div>
                                    </div>
                                </th>
                            </template>
                            <th class="px-2 py-1.5 text-center border-r border-slate-200 bg-amber-50/70 text-amber-900 w-9" title="Sakit (s)">S</th>
                            <th class="px-2 py-1.5 text-center border-r border-slate-200 bg-purple-50/70 text-purple-900 w-9" title="Izin (i)">I</th>
                            <th class="px-2 py-1.5 text-center bg-rose-50/70 text-rose-900 w-9" title="Alfa (a)">A</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <template x-if="students.length === 0">
                            <tr>
                                <td :colspan="8 + daysInMonth" class="px-5 py-10 text-center text-slate-500">
                                    Belum ada peserta didik di kelas ini. Tambahkan murid pada tab <strong>Peserta Didik & Wali Kelas</strong> terlebih dahulu.
                                </td>
                            </tr>
                        </template>
                        <template x-for="(st, rIdx) in students" :key="st.id">
                            <tr class="hover:bg-slate-50/80">
                                <td class="sticky left-0 z-10 bg-white px-2 py-1.5 text-center font-bold text-slate-600 border-r border-slate-200" x-text="st.no"></td>
                                <td class="sticky left-9 sm:left-10 z-10 bg-white px-2.5 sm:px-3 py-1.5 font-bold text-slate-900 border-r border-slate-200 truncate max-w-[135px] sm:max-w-[210px]" x-text="st.full_name"></td>
                                <td class="px-2 py-1.5 text-center text-slate-600 border-r border-slate-200 truncate" x-text="st.nickname"></td>
                                <td class="px-2 py-1.5 text-center font-semibold text-slate-700 border-r border-slate-200" x-text="st.gender"></td>

                                <template x-for="d in daysArray" :key="st.id + '-' + d">
                                    <td class="p-0 border-r border-slate-200 text-center">
                                        <input type="text"
                                               maxlength="1"
                                               autocapitalize="none"
                                               autocomplete="off"
                                               autocorrect="off"
                                               spellcheck="false"
                                               :readonly="tapStamp !== null"
                                               :id="`att-${rIdx}-${d}`"
                                               :value="getCell(st.id, d)"
                                               @focus="if(tapStamp === null) $el.select()"
                                               @click="onCellTap(st.id, d)"
                                               @keydown="handleKey($event, st.id, rIdx, d)"
                                               @input="handleInput($event, st.id, d)"
                                               class="w-7 h-8 sm:h-7 text-center text-xs font-extrabold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:z-20 relative cursor-pointer"
                                               :class="cellColorClass(getCell(st.id, d))">
                                    </td>
                                </template>

                                <td class="px-2 py-1.5 text-center font-extrabold text-emerald-800 bg-emerald-50/40 border-r border-slate-200">
                                    <span x-text="rowStats(st.id).present"></span>
                                    <template x-if="rowStats(st.id).dispen > 0">
                                        <span class="text-[10px] text-sky-700 font-bold" :title="`Termasuk ${rowStats(st.id).dispen} Dispen`" x-text="`(${rowStats(st.id).dispen}d)`"></span>
                                    </template>
                                </td>
                                <td class="px-1.5 py-1.5 text-center font-bold text-amber-800 bg-amber-50/20 border-r border-slate-200" x-text="rowStats(st.id).sakit"></td>
                                <td class="px-1.5 py-1.5 text-center font-bold text-purple-800 bg-purple-50/20 border-r border-slate-200" x-text="rowStats(st.id).izin"></td>
                                <td class="px-1.5 py-1.5 text-center font-bold text-rose-800 bg-rose-50/20" x-text="rowStats(st.id).alfa"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Tampilan Rekapitulasi 1 Semester Penuh --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                <div class="text-xs font-bold text-slate-800">
                    Rekapitulasi Kehadiran Semester ({{ $recap['total_active_dates'] }} Hari Pertemuan Tercatat)
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-slate-100 text-slate-700 uppercase text-[11px] border-b border-slate-200">
                        <tr>
                            <th rowspan="2" class="px-3 py-2.5 text-center border-r border-slate-200 w-12">No</th>
                            <th rowspan="2" class="px-4 py-2.5 border-r border-slate-200">Nama Lengkap</th>
                            <th rowspan="2" class="px-3 py-2.5 text-center border-r border-slate-200">Nama Panggilan</th>
                            <th rowspan="2" class="px-3 py-2.5 text-center border-r border-slate-200 w-14">L/P</th>
                            <th colspan="3" class="px-3 py-2 text-center border-b border-r border-slate-200 bg-emerald-50 text-emerald-900">Kehadiran</th>
                            <th colspan="4" class="px-3 py-2 text-center border-b border-r border-slate-200 bg-rose-50 text-rose-900">Ketidakhadiran</th>
                            <th rowspan="2" class="px-3 py-2.5 text-center w-24">% Hadir</th>
                        </tr>
                        <tr class="text-[10px]">
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-emerald-50/50">Hadir (.)</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-sky-50/70 text-sky-900">Dispen (d)</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-emerald-100/70 text-emerald-950">Jml Hadir</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-amber-50/50">Sakit</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-purple-50/50">Izin</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-rose-50/50">Alfa</th>
                            <th class="px-2.5 py-1.5 text-center border-r border-slate-200 bg-rose-100/60">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recap['rows'] as $r)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2.5 text-center font-bold border-r border-slate-100">{{ $r['no'] }}</td>
                                <td class="px-4 py-2.5 font-bold text-slate-900 border-r border-slate-100">{{ $r['student']->full_name }}</td>
                                <td class="px-3 py-2.5 text-center text-slate-600 border-r border-slate-100">{{ $r['student']->nickname ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-center font-semibold border-r border-slate-100">{{ $r['student']->gender }}</td>
                                <td class="px-3 py-2.5 text-center border-r border-slate-100">{{ $r['dot'] }}</td>
                                <td class="px-3 py-2.5 text-center font-semibold text-sky-700 border-r border-slate-100">{{ $r['dispen'] }}</td>
                                <td class="px-3 py-2.5 text-center font-extrabold text-emerald-700 bg-emerald-50/30 border-r border-slate-100">{{ $r['present'] }}</td>
                                <td class="px-3 py-2.5 text-center border-r border-slate-100">{{ $r['sakit'] }}</td>
                                <td class="px-3 py-2.5 text-center border-r border-slate-100">{{ $r['izin'] }}</td>
                                <td class="px-3 py-2.5 text-center border-r border-slate-100">{{ $r['alfa'] }}</td>
                                <td class="px-3 py-2.5 text-center font-bold text-rose-700 border-r border-slate-100">{{ $r['absent'] }}</td>
                                <td class="px-3 py-2.5 text-center font-extrabold text-slate-900">{{ number_format($r['percentage'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-5 py-10 text-center text-slate-500">Belum ada data peserta didik.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Modal Import Excel Absensi --}}
    <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="importModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Import Absensi Bulan {{ $monthName }} {{ $year }}</h3>
                <button type="button" @click="importModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="{{ route('attendances.import', $teachingClass) }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 flex items-center justify-between gap-2">
                    <span>Unduh template Excel absensi bulan ini:</span>
                    <a href="{{ route('attendances.template', [$teachingClass, 'month' => $month, 'year' => $year]) }}"
                       class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shrink-0">
                        Unduh Template
                    </a>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">File Excel (.xlsx / .xls / .csv) *</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full text-xs">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="importModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Import & Sinkronkan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Cetak PDF & Download Excel Absensi --}}
    @include('partials.export-modal', [
        'modalVar' => 'exportModal',
        'modalTitle' => $tab === 'recap'
            ? ('Cetak PDF & Download Excel — Rekap Absensi Semester Kelas ' . $teachingClass->class_name)
            : ('Cetak PDF & Download Excel — Absensi ' . $monthName . ' ' . $year . ' Kelas ' . $teachingClass->class_name),
        'pdfRoute' => $tab === 'recap'
            ? route('exports.attendance-recap.pdf', $teachingClass)
            : route('exports.attendance-monthly.pdf', $teachingClass),
        'excelRoute' => $tab === 'recap'
            ? route('exports.attendance-recap.excel', $teachingClass)
            : route('exports.attendance-monthly.excel', $teachingClass),
        'defaultOrientation' => $tab === 'recap' ? 'portrait' : 'landscape',
        'extraParams' => ['month' => $month, 'year' => $year],
    ])
</div>

@push('scripts')
<script>
function attendanceSpreadsheet(cfg) {
    return {
        students: cfg.students,
        cells: cfg.initialCells,
        daysInMonth: cfg.daysInMonth,
        daysArray: Array.from({ length: cfg.daysInMonth }, (_, i) => i + 1),
        tapStamp: null,
        isDirty: false,
        saving: false,
        savedMessage: '',
        importModal: false,
        exportModal: false,

        onCellTap(studentId, day) {
            if (this.tapStamp !== null) {
                this.setCell(studentId, day, this.tapStamp);
            }
        },

        getCell(studentId, day) {
            const row = this.cells[String(studentId)];
            return row ? (row[day] || '') : '';
        },
        setCell(studentId, day, val) {
            const key = String(studentId);
            if (!this.cells[key]) this.cells[key] = {};
            this.cells[key][day] = val;
            this.isDirty = true;
            this.savedMessage = '';
        },
        fillDay(day, statusVal) {
            this.students.forEach((st) => {
                this.setCell(st.id, day, statusVal);
            });
        },
        columnHasData(day) {
            return this.students.some((st) => this.getCell(st.id, day) !== '');
        },
        cellColorClass(val) {
            switch (val) {
                case '.': return 'bg-emerald-50/70 text-emerald-900';
                case 'd': return 'bg-sky-100 text-sky-900';
                case 's': return 'bg-amber-100 text-amber-900';
                case 'i': return 'bg-purple-100 text-purple-900';
                case 'a': return 'bg-rose-100 text-rose-900';
                default: return 'bg-white text-slate-700';
            }
        },
        rowStats(studentId) {
            let dot = 0, dispen = 0, sakit = 0, izin = 0, alfa = 0;
            for (let d = 1; d <= this.daysInMonth; d++) {
                const v = this.getCell(studentId, d);
                if (v === '.') dot++;
                else if (v === 'd') dispen++;
                else if (v === 's') sakit++;
                else if (v === 'i') izin++;
                else if (v === 'a') alfa++;
            }
            return {
                present: dot + dispen, // Dispen tetap dihitung Hadir!
                dispen,
                sakit,
                izin,
                alfa,
            };
        },
        focusCell(rIdx, day) {
            if (rIdx < 0 || rIdx >= this.students.length || day < 1 || day > this.daysInMonth) return;
            const el = document.getElementById(`att-${rIdx}-${day}`);
            if (el) {
                el.focus();
                el.select();
            }
        },
        handleKey(e, studentId, rIdx, day) {
            const k = e.key;
            if (k === 'ArrowDown' || k === 'Enter') {
                e.preventDefault();
                this.focusCell(rIdx + 1, day);
            } else if (k === 'ArrowUp') {
                e.preventDefault();
                this.focusCell(rIdx - 1, day);
            } else if (k === 'ArrowRight') {
                e.preventDefault();
                this.focusCell(rIdx, day + 1);
            } else if (k === 'ArrowLeft') {
                e.preventDefault();
                this.focusCell(rIdx, day - 1);
            } else if (k === 'Backspace' || k === 'Delete') {
                e.preventDefault();
                this.setCell(studentId, day, '');
            } else if (['.', 's', 'i', 'a', 'd', 'S', 'I', 'A', 'D'].includes(k)) {
                e.preventDefault();
                this.setCell(studentId, day, k.toLowerCase());
                this.focusCell(rIdx + 1, day);
            }
        },
        handleInput(e, studentId, day) {
            const raw = (e.target.value || '').trim().toLowerCase();
            if (['.', 's', 'i', 'a', 'd', ''].includes(raw)) {
                this.setCell(studentId, day, raw);
            } else {
                e.target.value = this.getCell(studentId, day);
            }
        },
        async saveAll() {
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
                    body: JSON.stringify({
                        year: cfg.year,
                        month: cfg.month,
                        cells: this.cells,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Gagal menyimpan data absensi.');
                } else {
                    this.isDirty = false;
                    this.savedMessage = `Tersimpan (${data.saved_at}) & Jurnal tersinkron`;
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan saat menyimpan absensi.');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endpush
@endsection
