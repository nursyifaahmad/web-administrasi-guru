@extends('layouts.app')

@section('title', 'Master Jadwal Pelajaran Sekolah')
@section('page_title', 'Master Jadwal Pelajaran Sekolah')
@section('page_subtitle', 'Pengaturan jadwal KBM 21 rombel, deteksi bentrok otomatis, dan sinkronisasi ke administrasi guru.')

@section('content')
<div class="space-y-6"
     x-data="{
        wizardModal: false,
        editModal: false,
        swapModal: false,
        selectedItem: null,
        selectedTeacherCode: '',
        selectedTeacherName: '',
        selectedSubjectName: '',
        swapItem1: null,
        swapItem2Id: '',
        swapTargetSlots: [],
        loadingSwapTargets: false,
        swapFilterDay: 'all',
        teachersList: @js($teachers->values()),
        activeDay: {{ $activeDay }},

        openEdit(item) {
            this.selectedItem = item;
            this.selectedTeacherCode = item.teacher_code || '';
            this.selectedTeacherName = item.teacher_name || '';
            this.selectedSubjectName = item.subject_name || '';
            this.editModal = true;
        },

        onTeacherSelect(code) {
            this.selectedTeacherCode = code;
            let t = this.teachersList.find(x => String(x.code) === String(code));
            if (t) {
                this.selectedTeacherName = t.name;
                this.selectedSubjectName = t.subject;
            } else if (code === '') {
                this.selectedTeacherName = '';
                this.selectedSubjectName = '';
            }
        },

        async openSwap(item) {
            this.swapItem1 = item;
            this.swapItem2Id = '';
            this.swapTargetSlots = [];
            this.loadingSwapTargets = true;
            this.swapFilterDay = 'all';
            this.swapModal = true;

            try {
                let resp = await fetch('{{ route('admin.schedules.master.swap-targets') }}?item_id=' + item.id, {
                    headers: { 'Accept': 'application/json' }
                });
                let data = await resp.json();
                if (data.success && data.slots) {
                    this.swapTargetSlots = data.slots;
                }
            } catch (err) {
                console.error('Gagal memuat slot target penukaran:', err);
            } finally {
                this.loadingSwapTargets = false;
            }
        },

        get filteredSwapTargets() {
            if (this.swapFilterDay === 'all') {
                return this.swapTargetSlots;
            }
            return this.swapTargetSlots.filter(s => String(s.day_of_week) === String(this.swapFilterDay));
        },

        isCommonActivity(item) {
            if (!item || !item.teacher_code) return false;
            let code = String(item.teacher_code).trim().toUpperCase();
            return ['UPACARA BENDERA', 'UPACARA', 'SHOLAT DHUHA', 'DHUHA', 'ISTIRAHAT', 'KEGIATAN KEAGAMAAN', 'SABTU BERSIH / OR', 'SABTU BERSIH', 'LITERASI'].includes(code);
        }
     }">

    {{-- Header Action Bar --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold">
                    Tahun Ajaran: {{ $academicYear->full_label ?? '-' }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold">
                    21 Rombel &bull; 2 Shift (Perbaikan Kelas)
                </span>
            </div>
            <h2 class="text-base font-extrabold text-slate-900 mt-2">Pusat Pengaturan Jadwal Pelajaran Wakasek</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
            {{-- Tombol Utama: Buat Jadwal Baru dari Awal --}}
            <a href="{{ route('admin.schedules.master.builder') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Buat Jadwal Baru dari Awal</span>
            </a>

            {{-- Tombol Sinkronkan ke Kelas Guru --}}
            <form method="POST" action="{{ route('admin.schedules.master.sync-classes') }}"
                  onsubmit="return confirm('Sinkronkan seluruh jadwal dari Master Jadwal ke Buku Administrasi Kelas Guru? Seluruh hari mengajar & jam pelajaran guru akan diperbarui otomatis.')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs transition border border-indigo-200">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sinkronkan ke Guru</span>
                </button>
            </form>

            {{-- Cetak / Ekspor --}}
            <a href="{{ route('admin.schedules.master.export') }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition border border-slate-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak / Cetak PDF</span>
            </a>
        </div>
    </div>

    {{-- Banner Status Kesiapan 2 Pilar Master Data (Master Guru & Master Rombel) --}}
    @if(isset($prerequisites))
        @if(!$prerequisites['is_ready'])
            <div class="rounded-2xl bg-amber-50 border border-amber-300 p-4 md:p-5 shadow-2xs space-y-3" x-data="{ detailOpen: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 font-bold text-base shadow-xs">
                            ⚠️
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold text-amber-950 flex items-center gap-2 flex-wrap">
                                <span>Perhatian: Data Master Prasyarat Belum Lengkap untuk Pembuatan Jadwal Baru</span>
                            </h3>
                            <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                                Master Jadwal Sekolah membutuhkan <strong>Data Master Guru</strong> dan <strong>Master Rombel &amp; Murid</strong> yang lengkap sebelum jadwal baru dapat disusun dari awal.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="detailOpen = !detailOpen"
                            class="px-3 py-1.5 rounded-lg bg-amber-200/80 hover:bg-amber-300 text-amber-950 font-bold text-xs transition shrink-0 cursor-pointer">
                        <span x-text="detailOpen ? 'Tutup Checklist' : 'Lihat Checklist Kesiapan'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    {{-- Status Pilar 1: Master Guru --}}
                    <div class="p-3.5 rounded-xl bg-white border {{ $prerequisites['teachers']['is_ready'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-amber-300 bg-amber-50/50' }} space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                @if($prerequisites['teachers']['is_ready'])
                                    <span class="text-emerald-600">✅</span>
                                @else
                                    <span class="text-amber-600">⚠️</span>
                                @endif
                                <span>1. Data Master Guru</span>
                            </span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded {{ $prerequisites['teachers']['is_ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $prerequisites['teachers']['complete'] }}/{{ $prerequisites['teachers']['total'] }} Guru Siap
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            @if($prerequisites['teachers']['is_ready'])
                                Seluruh guru telah memiliki mata pelajaran dan kode mengajar di Master Guru.
                            @elseif($prerequisites['teachers']['total'] === 0)
                                Belum ada data guru aktif di Master Guru.
                            @else
                                Terdapat <strong>{{ $prerequisites['teachers']['incomplete_count'] }}</strong> guru yang belum memiliki kode mengajar / mapel.
                            @endif
                        </p>
                        <div class="pt-1">
                            <a href="{{ route('admin.teachers.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-800 hover:underline">
                                <span>Buka Menu Master Guru &rarr;</span>
                            </a>
                        </div>
                    </div>

                    {{-- Status Pilar 2: Master Rombel & Murid --}}
                    <div class="p-3.5 rounded-xl bg-white border {{ $prerequisites['classes']['is_ready'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-amber-300 bg-amber-50/50' }} space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                @if($prerequisites['classes']['is_ready'])
                                    <span class="text-emerald-600">✅</span>
                                @else
                                    <span class="text-amber-600">⚠️</span>
                                @endif
                                <span>2. Master Rombel &amp; Murid</span>
                            </span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded {{ $prerequisites['classes']['is_ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $prerequisites['classes']['total'] }} Rombel Terkunci
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            @if($prerequisites['classes']['is_ready'])
                                Daftar rombel kelas terkunci dari database (Tingkat: {{ collect($prerequisites['classes']['by_grade'])->map(fn($v, $k) => "$k: $v rombel")->join(', ') }}).
                            @else
                                Belum ada rombel kelas yang terdaftar pada tahun ajaran ini di Master Rombel.
                            @endif
                        </p>
                        <div class="pt-1">
                            <a href="{{ route('admin.classes.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-indigo-700 hover:text-indigo-800 hover:underline">
                                <span>Buka Master Rombel &amp; Murid &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Detail Guru yang Belum Lengkap (Expandable) --}}
                @if(!$prerequisites['teachers']['is_ready'] && !empty($prerequisites['teachers']['incomplete_list']))
                    <div x-show="detailOpen" x-cloak class="mt-2 pt-3 border-t border-amber-200/80 space-y-2">
                        <h4 class="text-xs font-extrabold text-amber-950">Daftar Guru yang Perlu Dilengkapi di Master Guru:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($prerequisites['teachers']['incomplete_list'] as $inc)
                                <div class="p-2 rounded-lg bg-white border border-amber-200 text-xs">
                                    <div class="font-bold text-slate-900 truncate">{{ $inc['name'] }}</div>
                                    <div class="text-[11px] text-rose-600 font-semibold">{{ $inc['missing'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm">✅</span>
                    <span class="font-bold">Prasyarat Master Data Siap &amp; Terkunci:</span>
                    <span>{{ $prerequisites['teachers']['complete'] }} Guru Master Lengkap &bull; {{ $prerequisites['classes']['total'] }} Rombel Kelas Terkunci dari Master Rombel</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.teachers.index') }}" class="font-semibold text-emerald-700 hover:underline text-[11px]">Master Guru</a>
                    <span>&bull;</span>
                    <a href="{{ route('admin.classes.index') }}" class="font-semibold text-emerald-700 hover:underline text-[11px]">Master Rombel</a>
                </div>
            </div>
        @endif
    @endif

    {{-- Banner Peringatan Jadwal Bertabrakan (Conflict Detection Engine) --}}
    @if(count($conflicts) > 0)
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 md:p-5 shadow-2xs" x-data="{ expanded: false }">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 font-bold text-base shadow-xs">
                        ⚠️
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-rose-900">
                            Terdeteksi {{ count($conflicts) }} Titik Jadwal Bertabrakan (Bentrok Jam Sama)
                        </h3>
                        <p class="text-xs text-rose-700 mt-0.5 leading-relaxed">
                            Sistem mendeteksi guru yang dijadwalkan mengajar di 2 kelas berbeda pada hari dan jam yang sama persis.
                            Klik tombol di samping untuk melihat rincian dan gunakan tombol <strong>Tukar Slot</strong> untuk memperbaikinya langsung.
                        </p>
                    </div>
                </div>
                <button type="button" @click="expanded = !expanded"
                        class="px-3 py-1.5 rounded-lg bg-rose-200/80 hover:bg-rose-300/80 text-rose-900 font-bold text-xs transition shrink-0">
                    <span x-text="expanded ? 'Tutup Rincian' : 'Lihat ' + {{ count($conflicts) }} + ' Bentrok'"></span>
                </button>
            </div>

            {{-- Daftar Rincian Bentrok yang Terbuka --}}
            <div x-show="expanded" x-collapse class="mt-4 pt-3 border-t border-rose-200/70 space-y-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                    @foreach($conflicts as $idx => $c)
                        <div class="p-3 rounded-xl bg-white border border-rose-200 shadow-2xs flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 text-xs font-extrabold text-rose-900">
                                    <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px]">#{{ $idx + 1 }}</span>
                                    <span>{{ $c['day_name'] }} &bull; Shift {{ $c['shift'] }}</span>
                                    <span class="text-rose-600 font-semibold">({{ $c['time_range'] }})</span>
                                </div>
                                <div class="text-xs text-slate-800 font-bold mt-1 truncate">
                                    Kode {{ $c['teacher_code'] }}: {{ $c['teacher_name'] }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Mapel: {{ $c['subject_name'] }} &bull; Kelas:
                                    <span class="font-extrabold text-rose-700 underline">{{ implode(' & ', $c['clashing_classes']) }}</span>
                                </div>
                            </div>
                            <a href="{{ route('admin.schedules.master.index', ['day' => $c['day_of_week']]) }}"
                               class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-[11px] font-bold transition shrink-0">
                                Ke Hari {{ $c['day_name'] }} &rarr;
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-sm shrink-0">
                    ✓
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-emerald-950">Jadwal Sempurna & Bebas Bentrok</h3>
                    <p class="text-xs text-emerald-700">Tidak ada jadwal guru yang bertabrakan pada hari dan jam yang sama.</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-md bg-emerald-200 text-emerald-900 font-bold text-xs">Valid</span>
        </div>
    @endif

    {{-- Tab Navigasi Hari (Senin – Sabtu) --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-2 shadow-2xs">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
            @foreach(\App\Services\SchoolScheduleService::DAYS_MAP as $dNum => $dName)
                @php
                    $isDayActive = $activeDay === $dNum;
                    $dayConflicts = collect($conflicts)->where('day_of_week', $dNum)->count();
                @endphp
                <a href="{{ route('admin.schedules.master.index', ['day' => $dNum]) }}"
                   class="flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-center font-bold text-xs transition flex items-center justify-center gap-2 {{ $isDayActive ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>{{ strtoupper($dName) }}</span>
                    @if($dayConflicts > 0)
                        <span class="px-1.5 py-0.2 rounded-full {{ $isDayActive ? 'bg-white text-rose-700' : 'bg-rose-100 text-rose-700' }} text-[10px] font-extrabold">
                            {{ $dayConflicts }} ⚠️
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- MATRIKS JADWAL HARI TERPILIH --}}
    @php
        $dayMatrix = $matrixData['matrix_by_day'][$activeDay] ?? null;
        $pagiSlots = $dayMatrix['pagi_slots'] ?? collect();
        $siangSlots = $dayMatrix['siang_slots'] ?? collect();
    @endphp

    <div class="space-y-6">

        {{-- 1. SHIFT PAGI: KELAS VII & KELAS IX --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                    <h3 class="text-sm font-extrabold uppercase tracking-wide">
                        Shift Pagi: Kelas VII (7A–7G) & Kelas IX (9A–9G)
                    </h3>
                </div>
                <span class="text-xs text-slate-300 font-medium">Jam Belajar: 07.00 – 11.55 WIB</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-center border-collapse">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-extrabold">
                            <th class="p-2 border-r border-slate-200 w-12">Jam</th>
                            <th class="p-2 border-r border-slate-200 w-28 whitespace-nowrap">Waktu</th>
                            {{-- Header Kelas VII --}}
                            @foreach(['7A','7B','7C','7D','7E','7F','7G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-emerald-50 text-emerald-900 min-w-[55px]">{{ $c }}</th>
                            @endforeach
                            {{-- Header Kelas IX --}}
                            @foreach(['9A','9B','9C','9D','9E','9F','9G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-blue-50 text-blue-900 min-w-[55px]">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($pagiSlots as $slot)
                            @if($slot['is_activity'])
                                <tr class="bg-amber-50/80 font-bold text-amber-900">
                                    <td class="p-2 border-r border-slate-200 text-slate-500 font-semibold">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="14" class="p-2 text-center uppercase tracking-widest text-amber-800">
                                        {{ $slot['activity_name'] }}
                                    </td>
                                </tr>
                            @else
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="p-2 border-r border-slate-200 font-bold text-slate-600 bg-slate-50/40">{{ $slot['period_order'] }}</td>
                                    <td class="p-2 border-r border-slate-200 font-semibold text-slate-600 whitespace-nowrap">{{ $slot['time_range'] }}</td>

                                    {{-- Kolom 7A-7G --}}
                                    @foreach(['7A','7B','7C','7D','7E','7F','7G'] as $c)
                                        @php
                                            $item = $slot['classes'][$c] ?? null;
                                        @endphp
                                        <td class="p-1 border-r border-slate-200 {{ $item?->is_conflict ? 'bg-rose-100/90 border-rose-400 font-bold text-rose-900' : '' }}">
                                            @if($item)
                                                <button type="button" @click="openEdit({{ json_encode($item) }})"
                                                        title="{{ $item->is_conflict ? $item->conflict_notes : ($item->teacher_name . ' (' . $item->subject_name . ')') }}"
                                                        class="w-full py-1.5 px-1 rounded-lg text-center transition cursor-pointer flex flex-col items-center justify-center {{ $item->is_conflict ? 'bg-rose-600 text-white font-black ring-2 ring-rose-400 animate-pulse' : 'hover:bg-emerald-50 hover:text-emerald-900' }}">
                                                    <span class="text-xs font-black">{{ $item->teacher_code ?: '-' }}</span>
                                                    @if($item->is_conflict)
                                                        <span class="text-[9px] uppercase tracking-tighter">BENTROK</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Kolom 9A-9G --}}
                                    @foreach(['9A','9B','9C','9D','9E','9F','9G'] as $c)
                                        @php
                                            $item = $slot['classes'][$c] ?? null;
                                        @endphp
                                        <td class="p-1 border-r border-slate-200 {{ $item?->is_conflict ? 'bg-rose-100/90 border-rose-400 font-bold text-rose-900' : '' }}">
                                            @if($item)
                                                <button type="button" @click="openEdit({{ json_encode($item) }})"
                                                        title="{{ $item->is_conflict ? $item->conflict_notes : ($item->teacher_name . ' (' . $item->subject_name . ')') }}"
                                                        class="w-full py-1.5 px-1 rounded-lg text-center transition cursor-pointer flex flex-col items-center justify-center {{ $item->is_conflict ? 'bg-rose-600 text-white font-black ring-2 ring-rose-400 animate-pulse' : 'hover:bg-blue-50 hover:text-blue-900' }}">
                                                    <span class="text-xs font-black">{{ $item->teacher_code ?: '-' }}</span>
                                                    @if($item->is_conflict)
                                                        <span class="text-[9px] uppercase tracking-tighter">BENTROK</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="16" class="p-8 text-center text-slate-400">
                                    Belum ada data jadwal untuk Shift Pagi. Klik <strong>"+ Buat Jadwal Baru dari Awal"</strong>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 2. SHIFT SIANG: KELAS VIII --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-amber-950 via-slate-900 to-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                    <h3 class="text-sm font-extrabold uppercase tracking-wide">
                        Shift Siang: Kelas VIII (8A–8G)
                    </h3>
                </div>
                <span class="text-xs text-slate-300 font-medium">Jam Belajar: 12.00 – 16.25 WIB</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-center border-collapse">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-extrabold">
                            <th class="p-2 border-r border-slate-200 w-12">Jam</th>
                            <th class="p-2 border-r border-slate-200 w-28 whitespace-nowrap">Waktu</th>
                            {{-- Header Kelas VIII --}}
                            @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-amber-50 text-amber-900 min-w-[70px]">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($siangSlots as $slot)
                            @if($slot['is_activity'])
                                <tr class="bg-amber-50/80 font-bold text-amber-900">
                                    <td class="p-2 border-r border-slate-200 text-slate-500 font-semibold">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="7" class="p-2 text-center uppercase tracking-widest text-amber-800">
                                        {{ $slot['activity_name'] }}
                                    </td>
                                </tr>
                            @else
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="p-2 border-r border-slate-200 font-bold text-slate-600 bg-slate-50/40">{{ $slot['period_order'] }}</td>
                                    <td class="p-2 border-r border-slate-200 font-semibold text-slate-600 whitespace-nowrap">{{ $slot['time_range'] }}</td>

                                    {{-- Kolom 8A-8G --}}
                                    @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                                        @php
                                            $item = $slot['classes'][$c] ?? null;
                                        @endphp
                                        <td class="p-1 border-r border-slate-200 {{ $item?->is_conflict ? 'bg-rose-100/90 border-rose-400 font-bold text-rose-900' : '' }}">
                                            @if($item)
                                                <button type="button" @click="openEdit({{ json_encode($item) }})"
                                                        title="{{ $item->is_conflict ? $item->conflict_notes : ($item->teacher_name . ' (' . $item->subject_name . ')') }}"
                                                        class="w-full py-1.5 px-1 rounded-lg text-center transition cursor-pointer flex flex-col items-center justify-center {{ $item->is_conflict ? 'bg-rose-600 text-white font-black ring-2 ring-rose-400 animate-pulse' : 'hover:bg-amber-50 hover:text-amber-900' }}">
                                                    <span class="text-xs font-black">{{ $item->teacher_code ?: '-' }}</span>
                                                    @if($item->is_conflict)
                                                        <span class="text-[9px] uppercase tracking-tighter">BENTROK</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-slate-400">
                                    Belum ada data jadwal untuk Shift Siang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- MODAL WIZARD: BUAT JADWAL BARU DARI AWAL --}}
    <div x-show="wizardModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="wizardModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-7 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-4 border-b border-slate-100 mb-5">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-extrabold mb-1.5">
                            Wizard Setup Wakasek
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-900">Buat Jadwal Baru dari Awal</h3>
                        <p class="text-xs text-slate-500">Konfigurasi struktur matriks jadwal pelajaran sekolah untuk semester aktif.</p>
                    </div>
                    <button type="button" @click="wizardModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.schedules.master.scratch') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Tahun Ajaran & Semester</label>
                        <input type="text" readonly value="{{ $academicYear->full_label }}"
                               class="w-full rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Model Shift Belajar Sekolah</label>
                        <select name="shift_mode" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-bold focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="double" selected>2 Shift (Pagi: Kelas 7 & 9, Siang: Kelas 8) — Masa Renovasi / Perbaikan</option>
                            <option value="single">1 Shift Reguler (Semua Kelas Masuk Pagi) — Kondisi Normal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Jumlah Hari Belajar per Minggu</label>
                        <select name="days_count" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-bold focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="6" selected>6 Hari Kerja (Senin s.d. Sabtu) [Standar SMPN 2 Lemahabang]</option>
                            <option value="5">5 Hari Kerja (Senin s.d. Jumat)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilihan Permulaan Matriks</label>
                        <div class="space-y-2">
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer transition">
                                <input type="radio" name="source_mode" value="wakasek_template" checked class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-extrabold text-slate-900">Muat Template Resmi Wakasek SMPN 2 Lemahabang</div>
                                    <div class="text-[11px] text-slate-500">Langsung memuat 21 rombel, 36 kode guru, dan slot KBM lengkap untuk disesuaikan.</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer transition">
                                <input type="radio" name="source_mode" value="empty" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-extrabold text-slate-900">Matriks Kosong Bersih (Mulai dari Nol)</div>
                                    <div class="text-[11px] text-slate-500">Menghasilkan tabel kerangka kosong, Wakasek mengisi guru & jam satu per satu.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
                        <button type="button" @click="wizardModal = false"
                                class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs">
                            Proses & Buat Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL QUICK EDIT SEL JADWAL --}}
    <div x-show="editModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="editModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <span class="text-[11px] font-bold text-emerald-700" x-text="selectedItem ? ('Kelas ' + selectedItem.class_name + ' &bull; Jam ' + selectedItem.period_order + ' (' + selectedItem.time_range + ')') : ''"></span>
                        <h3 class="text-base font-extrabold text-slate-900">Edit Guru & Mapel Jadwal</h3>
                    </div>
                    <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="selectedItem">
                    <form method="POST" :action="'/admin/jadwal-master/item/' + selectedItem.id" class="space-y-4">
                        @csrf
                        @method('PUT')

                        {{-- Alert Jika Sedang Bentrok --}}
                        <div x-show="selectedItem.is_conflict" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                            <strong class="font-bold">Status Bentrok:</strong>
                            <p class="mt-0.5" x-text="selectedItem.conflict_notes"></p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kode Guru / Kegiatan</label>
                            <select name="teacher_code" x-model="selectedTeacherCode" @change="onTeacherSelect($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">-- Kosongkan Sel Jadwal --</option>
                                <optgroup label="Kode Guru Resmi (36 Guru)">
                                    <template x-for="t in teachersList" :key="t.code">
                                        <option :value="t.code" :selected="String(t.code) === String(selectedTeacherCode)"
                                                x-text="'Kode ' + t.code + ' — ' + t.name + ' (' + (t.subject || '-') + ')'"></option>
                                    </template>
                                </optgroup>
                                <optgroup label="Kegiatan Sekolah / Pembiasaan">
                                    <option value="UPACARA BENDERA">UPACARA BENDERA</option>
                                    <option value="SHOLAT DHUHA">SHOLAT DHUHA</option>
                                    <option value="ISTIRAHAT">ISTIRAHAT</option>
                                    <option value="KEGIATAN KEAGAMAAN">KEGIATAN KEAGAMAAN</option>
                                    <option value="SABTU BERSIH / OR">SABTU BERSIH / OR</option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Guru (Otomatis)</label>
                            <input type="text" name="teacher_name" x-model="selectedTeacherName"
                                   class="w-full rounded-xl bg-slate-50 border border-slate-300 px-3 py-2 text-xs font-semibold">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Mata Pelajaran (Otomatis)</label>
                            <input type="text" name="subject_name" x-model="selectedSubjectName"
                                   class="w-full rounded-xl bg-slate-50 border border-slate-300 px-3 py-2 text-xs font-semibold">
                        </div>

                        <div class="pt-3 flex items-center justify-between border-t border-slate-100">
                            <button type="button" @click="openSwap(selectedItem); editModal = false"
                                    x-show="!isCommonActivity(selectedItem)"
                                    class="px-3 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs transition">
                                ⇄ Tukar dengan Sel Lain
                            </button>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editModal = false"
                                        class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                    Batal
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    {{-- MODAL TUKAR JADWAL (SWAP SLOTS) --}}
    <div x-show="swapModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="swapModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <span class="text-[11px] font-bold text-amber-700 flex items-center gap-1.5">
                            <span>⚡ Pengaturan Fleksibel &amp; Anti-Bentrok</span>
                        </span>
                        <h3 class="text-base font-extrabold text-slate-900">Tukar Jadwal (Swap Slot)</h3>
                    </div>
                    <button type="button" @click="swapModal = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="swapItem1">
                    <form method="POST" action="{{ route('admin.schedules.master.swap') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="item_id_1" :value="swapItem1.id">

                        {{-- Slot 1 Terpilih --}}
                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs space-y-1">
                            <span class="font-extrabold text-amber-900 uppercase tracking-wider text-[10px] block">Slot 1 yang Dipilih:</span>
                            <div class="font-black text-slate-900 text-sm flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-lg bg-amber-200/80 text-amber-950 font-black" x-text="'Kelas ' + swapItem1.class_name"></span>
                                <span x-text="(swapItem1.day_name || ('Hari ' + swapItem1.day_of_week)) + ' &bull; Jam ' + swapItem1.period_order + ' (' + swapItem1.time_range + ')'"></span>
                            </div>
                            <div class="text-[11px] text-slate-600 pt-0.5">
                                Isi: <strong class="text-slate-800" x-text="swapItem1.teacher_code ? ('Kode ' + swapItem1.teacher_code + ' (' + (swapItem1.teacher_name || '-') + (swapItem1.subject_name ? ' - ' + swapItem1.subject_name : '') + ')') : '⚪ Slot Kosong'"></strong>
                            </div>
                        </div>

                        {{-- Jaminan Konsistensi JP --}}
                        <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-950 space-y-1">
                            <div class="font-bold flex items-center gap-1.5 text-emerald-900">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Jumlah JP Kelas Dijamin Aman &amp; Konsisten</span>
                            </div>
                            <p class="text-[11px] text-emerald-800 leading-relaxed">
                                Target penukaran khusus dibatasi untuk <strong>Kelas <span x-text="swapItem1.class_name"></span></strong> saja (bisa hari yang sama atau berbeda hari). Hal ini memastikan total JP per mapel dan beban belajar kelas tetap persis sama.
                            </p>
                        </div>

                        {{-- Filter Hari Cepat --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-800">
                                    Pilih Slot Target di Kelas <span class="text-amber-800 font-extrabold" x-text="swapItem1.class_name"></span>:
                                </label>
                                <span class="text-[10px] text-slate-400 font-bold" x-show="!loadingSwapTargets"
                                      x-text="filteredSwapTargets.length + ' Slot Tersedia'"></span>
                            </div>

                            <div class="flex items-center gap-1 flex-wrap">
                                <button type="button" @click="swapFilterDay = 'all'"
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer"
                                        :class="swapFilterDay === 'all' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'">
                                    Semua Hari
                                </button>
                                @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => "Jum'at", 6 => 'Sabtu'] as $dNum => $dName)
                                    <button type="button" @click="swapFilterDay = '{{ $dNum }}'"
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer"
                                            :class="swapFilterDay === '{{ $dNum }}' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'">
                                        {{ $dName }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dropdown Pilihan Slot Target --}}
                        <div>
                            {{-- State Loading --}}
                            <div x-show="loadingSwapTargets" class="py-4 text-center text-xs text-slate-400 font-bold flex items-center justify-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Memuat slot Kelas <span x-text="swapItem1.class_name"></span> dari database...</span>
                            </div>

                            {{-- Select Dropdown --}}
                            <div x-show="!loadingSwapTargets">
                                <select name="item_id_2" required x-model="swapItem2Id"
                                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-bold bg-white focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">-- Pilih Slot Target di Kelas <span x-text="swapItem1.class_name"></span> --</option>
                                    <template x-for="target in filteredSwapTargets" :key="target.id">
                                        <option :value="target.id" x-text="target.label"></option>
                                    </template>
                                </select>
                                <p x-show="filteredSwapTargets.length === 0" class="text-[11px] text-slate-400 italic mt-1.5">
                                    Tidak ada slot target yang tersedia pada filter hari yang dipilih.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                            <button type="button" @click="swapModal = false"
                                    class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                Batal
                            </button>
                            <button type="submit"
                                    :disabled="!swapItem2Id"
                                    class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs cursor-pointer transition">
                                Lakukan Penukaran Slot
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    {{-- LEGENDA KODE GURU & WALI KELAS --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs space-y-4" x-data="{ openLegend: false }">
        <div class="flex items-center justify-between cursor-pointer" @click="openLegend = !openLegend">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <h3 class="text-sm font-extrabold text-slate-900">Legenda 36 Kode Guru, Wali Kelas & Guru Piket</h3>
                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-xs font-bold">{{ $teachers->count() }} Guru</span>
            </div>
            <button type="button" class="text-xs font-bold text-emerald-700 hover:underline">
                <span x-text="openLegend ? 'Sembunyikan' : 'Buka Legenda Lengkap &darr;'"></span>
            </button>
        </div>

        <div x-show="openLegend" x-collapse class="pt-3 border-t border-slate-100">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 text-xs">
                @foreach($teachers as $t)
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs shrink-0">
                                {{ $t->code }}
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ $t->name }}</div>
                                <div class="text-[11px] text-slate-500 truncate">{{ $t->subject ?: '-' }}</div>
                            </div>
                        </div>
                        @if($t->homeroom_class)
                            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold shrink-0">
                                Wali {{ $t->homeroom_class }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
@endsection
