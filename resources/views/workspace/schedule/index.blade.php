@extends('layouts.app')

@section('title', 'Jadwal Mengajar Mingguan — ' . $teacher->name)
@section('page_title', 'Jadwal Mengajar Mingguan (Senin–Sabtu)')
@section('page_subtitle', 'Pemetaan lokasi dan hari mengajar kelas untuk ' . $teacher->name . ' — Tahun Ajaran ' . ($academicYear?->full_label ?? '-'))

@section('content')
<div class="space-y-5">

    {{-- Header Bar: Identitas Guru & Tombol Aksi (Cetak & Pemilih Guru Admin) --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-lg shadow-xs shrink-0">
                {{ strtoupper(substr($teacher->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-extrabold text-slate-900 truncate">{{ $teacher->name }}</h2>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                        {{ $teacher->role === 'admin' ? 'Guru / Admin' : 'Guru Pengampu' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    NIP. {{ $teacher->nip ?: '-' }} &bull; Tahun Ajaran: <strong>{{ $academicYear?->full_label ?? 'Semua Semester' }}</strong>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
            {{-- Dropdown Pemilih Guru (Khusus Akun Admin) --}}
            @if(auth()->user()->isAdmin() && $allTeachers->count() > 1)
                <form method="GET" action="{{ route('schedules.index') }}" class="flex items-center gap-2 w-full sm:w-auto">
                    <label class="text-xs font-semibold text-slate-600 hidden sm:inline">Pilih Guru:</label>
                    <select name="teacher_id" onchange="this.form.submit()"
                            class="w-full sm:w-auto rounded-xl border border-slate-300 text-xs font-bold text-slate-800 px-3 py-2 bg-slate-50 focus:ring-emerald-500 focus:border-emerald-500">
                        @foreach($allTeachers as $t)
                            <option value="{{ $t->id }}" @selected($t->id === $teacher->id)>
                                {{ $t->name }} ({{ $t->nip ?: 'Non-NIP' }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <button type="button" onclick="window.print()"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 shadow-2xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Jadwal Mingguan</span>
            </button>
        </div>
    </div>

    {{-- Banner Pintar: KBM Hari Ini (Quick Glance) --}}
    <div class="rounded-2xl border p-4 md:p-5 shadow-2xs transition {{ $todayClasses->isNotEmpty() ? 'bg-gradient-to-r from-emerald-900 via-emerald-800 to-slate-900 text-white border-emerald-700/60' : 'bg-slate-900 text-slate-200 border-slate-800' }} print:hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-3 border-b border-white/10">
            <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full {{ $todayClasses->isNotEmpty() ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                <h3 class="text-sm font-extrabold uppercase tracking-wide">
                    Jadwal KBM Hari Ini: <span class="text-amber-300">{{ $todayDayName }}, {{ \App\Support\IndonesianDate::formatLong($today) }}</span>
                </h3>
            </div>
            <div class="text-xs">
                @if($todayClasses->isNotEmpty())
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 font-bold">
                        Ada {{ $todayClasses->count() }} Kelas Mengajar Hari Ini
                    </span>
                @else
                    <span class="px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 font-medium">
                        Tidak Ada Jadwal Mengajar Hari Ini
                    </span>
                @endif
            </div>
        </div>

        @if($todayClasses->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($todayClasses as $cls)
                    <div class="rounded-xl bg-white/10 backdrop-blur-xs border border-white/15 p-3.5 flex flex-col justify-between gap-3 hover:bg-white/15 transition">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs">
                                    Kelas {{ $cls->class_name }}
                                </span>
                                <span class="text-[11px] text-emerald-200 font-semibold truncate">
                                    {{ $cls->subject->name ?? '-' }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs font-bold text-amber-200 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ $cls->schedule_info ?: 'Jadwal Jam Reguler' }}</span>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-300">
                                Wali Kelas: {{ $cls->homeroom_teacher_name ?: '-' }} &bull; {{ $cls->activeStudents->count() }} Siswa
                            </div>
                        </div>

                        <div class="pt-2 border-t border-white/10 grid grid-cols-3 gap-1.5 text-[11px]">
                            <a href="{{ route('journals.index', $cls) }}"
                               class="py-1.5 px-1 rounded-lg bg-white text-slate-900 font-bold hover:bg-emerald-50 text-center transition truncate">
                                📝 Jurnal
                            </a>
                            <a href="{{ route('attendances.index', $cls) }}"
                               class="py-1.5 px-1 rounded-lg bg-emerald-500 text-slate-950 font-extrabold hover:bg-emerald-400 text-center transition truncate">
                                📋 Absensi
                            </a>
                            <a href="{{ route('grades.index', $cls) }}"
                               class="py-1.5 px-1 rounded-lg bg-white/20 text-white font-bold hover:bg-white/30 text-center transition truncate">
                                📊 Nilai
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-xs text-slate-400 leading-relaxed">
                Hari ini tidak ada kelas tatap muka yang terjadwal. Anda dapat memanfaatkan waktu untuk menyusun administrasi, memeriksa tugas peserta didik, atau kegiatan akademik lainnya.
            </p>
        @endif
    </div>

    {{-- Kartu Statistik Ringkas --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 print:hidden">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Total Kelas Diampu</div>
                <div class="text-lg font-black text-slate-900">{{ $classes->count() }} <span class="text-xs font-semibold text-slate-500">Buku Kelas</span></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Hari Mengajar Efektif</div>
                <div class="text-lg font-black text-emerald-600">{{ $activeDaysCount }} <span class="text-xs font-semibold text-slate-500">Hari / Minggu</span></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Total Peserta Didik</div>
                <div class="text-lg font-black text-slate-900">{{ $totalStudents }} <span class="text-xs font-semibold text-slate-500">Siswa Aktif</span></div>
            </div>
        </div>
    </div>

    {{-- Grid Jadwal Mingguan Utama: 6 Kolom (Senin s/d Sabtu) --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between text-xs font-bold text-slate-700 px-1 print:hidden">
            <span class="flex items-center gap-1.5">
                <span>🗓️ Pemetaan Jadwal KBM Mingguan (Senin s/d Sabtu)</span>
            </span>
            <span class="text-slate-400 font-normal text-[11px]">
                * Klik pada kartu kelas untuk langsung membuka jurnal, absensi, atau nilai
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5 items-start">
            @foreach($daysMap as $dayNum => $dayName)
                @php
                    $isToday = ($dayNum === $todayDayOfWeek);
                    $dayClasses = $scheduledByDay[$dayNum] ?? collect();
                @endphp

                <div class="rounded-2xl border transition overflow-hidden flex flex-col {{ $isToday ? 'border-emerald-400 ring-2 ring-emerald-300/60 bg-emerald-50/20 shadow-xs' : 'border-slate-200 bg-slate-50/60' }}">
                    {{-- Header Kolom Hari --}}
                    <div class="p-3 border-b flex items-center justify-between {{ $isToday ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-slate-800 text-slate-100 border-slate-700' }}">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-xs uppercase tracking-wider">{{ strtoupper($dayName) }}</span>
                            @if($isToday)
                                <span class="px-1.5 py-0.5 rounded bg-amber-400 text-slate-950 font-black text-[9px] uppercase tracking-tight">
                                    Hari Ini
                                </span>
                            @endif
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $isToday ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-300' }}">
                            {{ $dayClasses->count() }} Kelas
                        </span>
                    </div>

                    {{-- Isi Kartu Kelas pada Hari Tersebut --}}
                    <div class="p-2.5 space-y-2.5 flex-1 min-h-[70px] md:min-h-[160px]">
                        @forelse($dayClasses as $cls)
                            <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-2xs hover:border-emerald-300 hover:shadow-xs transition space-y-2 group">
                                <div class="flex items-start justify-between gap-1.5">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-black text-slate-900 group-hover:text-emerald-700 transition">
                                                Kelas {{ $cls->class_name }}
                                            </span>
                                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase {{ $cls->grade_level === 'VII' ? 'bg-sky-100 text-sky-800' : ($cls->grade_level === 'VIII' ? 'bg-indigo-100 text-indigo-800' : 'bg-purple-100 text-purple-800') }}">
                                                {{ $cls->grade_level }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] font-semibold text-slate-600 mt-0.5 truncate" title="{{ $cls->subject->name ?? '-' }}">
                                            {{ $cls->subject->name ?? '-' }}
                                        </div>
                                    </div>
                                    <a href="{{ route('classes.students', $cls) }}"
                                       title="Pengaturan Kelas & Jadwal"
                                       class="text-slate-400 hover:text-emerald-600 p-1 rounded-lg hover:bg-slate-50 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </a>
                                </div>

                                {{-- Keterangan Jam Pelajaran --}}
                                <div class="px-2 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[10.5px] font-bold flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="truncate">{{ $cls->schedule_info ?: 'Jam KBM Reguler' }}</span>
                                </div>

                                {{-- Wali Kelas & Murid --}}
                                <div class="text-[10px] text-slate-500 space-y-0.5">
                                    <div class="truncate" title="Wali Kelas: {{ $cls->homeroom_teacher_name }}">
                                        WK: <span class="font-medium text-slate-700">{{ $cls->homeroom_teacher_name ?: '-' }}</span>
                                    </div>
                                    <div>
                                        Murid: <span class="font-bold text-slate-700">{{ $cls->activeStudents->count() }}</span> orang
                                    </div>
                                </div>

                                {{-- Tombol Cepat Aksi Guru --}}
                                <div class="pt-1.5 border-t border-slate-100 grid grid-cols-3 gap-1 text-[10.5px]">
                                    <a href="{{ route('journals.index', $cls) }}"
                                       title="Buka Jurnal Harian"
                                       class="py-1 text-center rounded-lg bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-bold transition">
                                        Jurnal
                                    </a>
                                    <a href="{{ route('attendances.index', $cls) }}"
                                       title="Buka Absensi"
                                       class="py-1 text-center rounded-lg bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-bold transition">
                                        Absen
                                    </a>
                                    <a href="{{ route('grades.index', $cls) }}"
                                       title="Buka Nilai"
                                       class="py-1 text-center rounded-lg bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-bold transition">
                                        Nilai
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="h-16 md:h-28 rounded-xl border border-dashed border-slate-200 flex flex-col items-center justify-center p-2 text-center text-slate-400">
                                <span class="text-xs">Kosong</span>
                                <span class="text-[10px] text-slate-400 mt-0.5">Tidak ada jadwal KBM</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Peringatan Kelas yang Belum Diatur Hari Mengajarnya (Jika Ada) --}}
    @if($unscheduledClasses->isNotEmpty())
        <div class="bg-amber-50 rounded-2xl border border-amber-200 p-4 md:p-5 text-xs text-amber-900 space-y-2 print:hidden">
            <div class="flex items-start gap-2.5">
                <div class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 font-bold">
                    !
                </div>
                <div class="space-y-1">
                    <h4 class="font-extrabold text-amber-950">
                        Terdapat {{ $unscheduledClasses->count() }} Kelas yang Belum Diatur Hari Mengajarnya:
                    </h4>
                    <p class="text-amber-800 leading-relaxed">
                        Kelas di bawah ini belum ditentukan hari apa saja diajarkan dalam seminggu, sehingga belum terpetakan ke kolom hari di atas:
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach($unscheduledClasses as $uCls)
                            <a href="{{ route('classes.students', $uCls) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-amber-300 font-bold text-amber-900 hover:bg-amber-100 hover:border-amber-400 transition shadow-2xs">
                                <span>Kelas {{ $uCls->class_name }} ({{ $uCls->subject->code ?? $uCls->subject->name }})</span>
                                <span class="text-[10px] text-amber-600">&rarr; Atur Hari</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- AREA CETAK RESMI (Hanya Muncul Saat window.print()) --}}
<div class="hidden print:block text-slate-900 text-xs p-4">
    {{-- Kop Sekolah Resmi --}}
    <div class="text-center pb-3 mb-4 border-b-2 border-slate-900 space-y-0.5">
        <h3 class="text-xs font-bold uppercase tracking-wider">{{ $school->gov_header ?: 'PEMERINTAH KABUPATEN' }}</h3>
        <h1 class="text-base font-black uppercase">{{ $school->school_name ?: 'SMP NEGERI 2 LEMAHABANG' }}</h1>
        <p class="text-[10px] text-slate-600">{{ $school->address_line1 }} &bull; Telp: {{ $school->phone ?: '-' }} &bull; Email: {{ $school->email ?: '-' }}</p>
    </div>

    {{-- Judul Jadwal Cetak --}}
    <div class="text-center mb-4">
        <h2 class="text-sm font-black uppercase underline">JADWAL MENGAJAR MINGGUAN GURU</h2>
        <p class="text-xs font-medium text-slate-700 mt-0.5">Tahun Ajaran {{ $academicYear?->full_label ?? '-' }}</p>
    </div>

    {{-- Identitas Guru --}}
    <table class="w-full text-xs mb-4">
        <tr>
            <td class="w-28 font-bold py-0.5">Nama Guru</td>
            <td class="w-3">:</td>
            <td class="font-bold">{{ $teacher->name }}</td>
            <td class="w-28 font-bold py-0.5">Mata Pelajaran</td>
            <td class="w-3">:</td>
            <td>{{ $classes->pluck('subject.name')->unique()->filter()->implode(', ') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="font-bold py-0.5">NIP</td>
            <td>:</td>
            <td>{{ $teacher->nip ?: '-' }}</td>
            <td class="font-bold py-0.5">Total Kelas</td>
            <td>:</td>
            <td>{{ $classes->count() }} Kelas ({{ $totalStudents }} Murid)</td>
        </tr>
    </table>

    {{-- Tabel Jadwal Cetak Senin s/d Sabtu --}}
    <table class="w-full border-collapse border border-slate-800 text-xs mb-6">
        <thead>
            <tr class="bg-slate-100">
                <th class="border border-slate-800 px-2 py-2 text-center w-8">No</th>
                <th class="border border-slate-800 px-3 py-2 text-left w-24">Hari</th>
                <th class="border border-slate-800 px-3 py-2 text-center w-24">Kelas</th>
                <th class="border border-slate-800 px-3 py-2 text-left">Mata Pelajaran</th>
                <th class="border border-slate-800 px-3 py-2 text-left w-48">Jam Pelajaran / Keterangan</th>
                <th class="border border-slate-800 px-3 py-2 text-left w-48">Wali Kelas</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($daysMap as $dNum => $dName)
                @php $dClasses = $scheduledByDay[$dNum] ?? collect(); @endphp
                @if($dClasses->isNotEmpty())
                    @foreach($dClasses as $cIdx => $cls)
                        <tr>
                            <td class="border border-slate-800 px-2 py-1.5 text-center">{{ $no++ }}</td>
                            @if($cIdx === 0)
                                <td rowspan="{{ $dClasses->count() }}" class="border border-slate-800 px-3 py-1.5 font-bold align-top bg-slate-50/50">
                                    {{ $dName }}
                                </td>
                            @endif
                            <td class="border border-slate-800 px-3 py-1.5 text-center font-bold">
                                Kelas {{ $cls->class_name }}
                            </td>
                            <td class="border border-slate-800 px-3 py-1.5">
                                {{ $cls->subject->name ?? '-' }}
                            </td>
                            <td class="border border-slate-800 px-3 py-1.5">
                                {{ $cls->schedule_info ?: 'Jam KBM Reguler' }}
                            </td>
                            <td class="border border-slate-800 px-3 py-1.5">
                                {{ $cls->homeroom_teacher_name ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td class="border border-slate-800 px-2 py-1.5 text-center">{{ $no++ }}</td>
                        <td class="border border-slate-800 px-3 py-1.5 font-bold bg-slate-50/50">{{ $dName }}</td>
                        <td colspan="4" class="border border-slate-800 px-3 py-1.5 text-slate-500 italic text-center">
                            Tidak ada jadwal mengajar KBM tatap muka
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    {{-- Tanda Tangan Cetak --}}
    <table class="w-full text-xs">
        <tr>
            <td class="w-1/2 text-center align-top">
                <div>Mengetahui,</div>
                <div>Kepala {{ $school->school_name ?: 'SMP Negeri 2 Lemahabang' }}</div>
                <div class="h-20"></div>
                <div class="font-bold underline">{{ $school->headmaster_name ?: '....................................' }}</div>
                <div>NIP. {{ $school->headmaster_nip ?: '....................................' }}</div>
            </td>
            <td class="w-1/2 text-center align-top">
                <div>{{ $school->default_city ?: 'Lemahabang' }}, {{ \App\Support\IndonesianDate::formatLong(now()) }}</div>
                <div>Guru Mata Pelajaran,</div>
                <div class="h-20"></div>
                <div class="font-bold underline">{{ $teacher->name }}</div>
                <div>NIP. {{ $teacher->nip ?: '....................................' }}</div>
            </td>
        </tr>
    </table>
</div>
@endsection
