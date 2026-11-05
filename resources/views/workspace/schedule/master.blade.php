@extends('layouts.app')

@section('title', 'Jadwal Utama Sekolah')
@section('page_title', 'Jadwal Utama Sekolah')
@section('page_subtitle', 'Tinjau jadwal mengajar pribadi Anda dan matriks lengkap 21 rombel sekolah.')

@section('content')
<div class="space-y-6"
     x-data="{
        tab: '{{ $activeTab }}',
        activeDay: {{ $activeDay }},
        currentDayOfWeek: {{ $todayDayOfWeek }},
        currentTimeStr: new Date().toTimeString().substring(0, 5),
        detailModalOpen: false,
        selectedTeacher: {
            code: '',
            name: '',
            subject: '',
            className: '',
            periodOrder: '',
            timeRange: '',
            dayName: '',
            isConflict: false,
            conflictNotes: '',
            homeroom: '',
            piket: ''
        },
        openTeacherDetail(data) {
            this.selectedTeacher = data;
            this.detailModalOpen = true;
        },
        isToday(d) {
            return Number(d) === Number(this.currentDayOfWeek);
        }
     }">

    {{-- Top Profile Bar Guru & Filter Tab --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-lg shadow-xs shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-extrabold text-slate-900 truncate">{{ $user->name }}</h2>
                    @if(!empty($mySchedule['teacher']['code']) && $mySchedule['teacher']['code'] !== '-')
                        <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                            Kode Guru: {{ $mySchedule['teacher']['code'] }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    NIP. {{ $user->nip ?: '-' }} &bull; Mata Pelajaran: <strong>{{ $mySchedule['teacher']['subject'] ?? ($user->default_subject ?: '-') }}</strong>
                    @if(!empty($mySchedule['teacher']['homeroom_class']) && $mySchedule['teacher']['homeroom_class'] !== '-')
                        &bull; Wali Kelas: <strong>{{ $mySchedule['teacher']['homeroom_class'] }}</strong>
                    @endif
                </p>
            </div>
        </div>

        {{-- Tab Navigasi --}}
        <div class="flex items-center p-1 bg-slate-100 rounded-xl w-full sm:w-auto">
            <button type="button" @click="tab = 'my_schedule'"
                    :class="tab === 'my_schedule' ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                    class="flex-1 sm:flex-initial px-4 py-2 rounded-lg text-xs transition">
                Jadwal Mengajar Saya
            </button>
            <button type="button" @click="tab = 'school_matrix'"
                    :class="tab === 'school_matrix' ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                    class="flex-1 sm:flex-initial px-4 py-2 rounded-lg text-xs transition">
                Matriks 21 Rombel Sekolah
            </button>
            <button type="button" @click="tab = 'piket'"
                    :class="tab === 'piket' ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                    class="flex-1 sm:flex-initial px-4 py-2 rounded-lg text-xs transition">
                Guru Piket &amp; Wali Kelas
            </button>
        </div>
    </div>

    {{-- TAB 1: JADWAL MENGAJAR SAYA --}}
    <div x-show="tab === 'my_schedule'" class="space-y-5">
        @if(!empty($mySchedule['is_admin']) && ($mySchedule['total_hours'] ?? 0) === 0)
            <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex flex-wrap items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Akun Anda berstatus <strong>Administrator Sekolah / Wakasek</strong> (tidak memiliki jadwal KBM mengajar pribadi di kelas). Silakan buka tab <strong>Matriks 21 Rombel Sekolah</strong> untuk memantau dan mengelola seluruh jadwal rombel.</span>
                </div>
                <button type="button" @click="tab = 'school_matrix'" class="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition cursor-pointer">
                    Buka Matriks 21 Rombel &rarr;
                </button>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900">Jadwal Mingguan Saya (Senin s.d. Sabtu)</h3>
            <span class="text-xs text-slate-500 font-medium">Total: <strong>{{ $mySchedule['total_hours'] ?? 0 }} Jam Pelajaran</strong> / Minggu</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($mySchedule['by_day'] ?? [] as $dNum => $dayData)
                @php
                    $isHariIni = ($todayDayOfWeek === $dNum);
                    $hasItems = count($dayData['items']) > 0;
                @endphp
                <div class="rounded-2xl border transition overflow-hidden flex flex-col justify-between {{ $isHariIni ? 'border-emerald-500 bg-white ring-2 ring-emerald-400/40 shadow-md' : ($hasItems ? 'border-slate-200 bg-white shadow-2xs' : 'border-slate-200/70 bg-slate-50/50') }}">
                    {{-- Header Kartu Hari --}}
                    <div class="p-3.5 border-b flex items-center justify-between {{ $isHariIni ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-slate-50 border-slate-100 text-slate-800' }}">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $isHariIni ? 'bg-amber-300 animate-pulse' : ($hasItems ? 'bg-emerald-500' : 'bg-slate-300') }}"></span>
                            <span class="font-extrabold text-xs uppercase tracking-wide">{{ $dayData['day_name'] }}</span>
                            @if($isHariIni)
                                <span class="px-2 py-0.2 rounded-md bg-white text-emerald-800 font-extrabold text-[10px]">HARI INI</span>
                            @endif
                        </div>
                        <span class="text-[11px] font-bold {{ $isHariIni ? 'text-emerald-100' : 'text-slate-500' }}">
                            {{ $dayData['count'] }} Jam KBM
                        </span>
                    </div>

                    {{-- Daftar Jam KBM --}}
                    <div class="p-3.5 flex-1 space-y-2.5">
                        @forelse($dayData['items'] as $item)
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-emerald-400 transition flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white font-extrabold text-xs">
                                            Kelas {{ $item->class_name }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 text-[10px] font-bold">
                                            Jam Ke-{{ $item->period_order }}
                                        </span>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase">
                                            Shift {{ $item->shift }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-extrabold text-slate-900 mt-1 truncate">
                                        {{ $item->subject_name ?: 'Mata Pelajaran' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-semibold flex items-center gap-1 mt-0.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>{{ $item->time_range }} WIB</span>
                                    </div>
                                </div>

                                {{-- Shortcut Aksi jika terhubung ke SchoolClass --}}
                                @if($item->school_class_id)
                                    <a href="{{ route('schedules.index') }}"
                                       title="Buka Lembar KBM Hari Ini"
                                       class="p-2 rounded-xl bg-white border border-slate-200 text-emerald-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition shadow-2xs shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                @endif
                            </div>
                        @empty
                            <div class="py-6 text-center text-slate-400 text-xs">
                                Tidak ada jadwal mengajar pada hari {{ $dayData['day_name'] }}.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- TAB 2: MATRIKS SELURUH SEKOLAH (21 ROMBEL) --}}
    <div x-show="tab === 'school_matrix'" class="space-y-5" style="display: none;">
        {{-- Filter Hari Matriks --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-2 shadow-2xs">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                @foreach(\App\Services\SchoolScheduleService::DAYS_MAP as $dNum => $dName)
                    <a href="{{ route('schedules.main.index', ['tab' => 'school_matrix', 'day' => $dNum]) }}"
                       class="flex-1 min-w-[110px] py-2 px-3 rounded-xl text-center font-bold text-xs transition {{ $activeDay === $dNum ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ strtoupper($dName) }}
                    </a>
                @endforeach
            </div>
        </div>

        @php
            $dayMatrix = $matrixData['matrix_by_day'][$activeDay] ?? null;
            $pagiSlots = $dayMatrix['pagi_slots'] ?? collect();
            $siangSlots = $dayMatrix['siang_slots'] ?? collect();

            $teacherLookup = [];
            foreach ($allTeachers as $t) {
                $teacherLookup[$t->code] = [
                    'name' => $t->name,
                    'subject' => $t->subject ?: '-',
                    'homeroom' => $t->homeroom_class ? 'Wali Kelas ' . $t->homeroom_class : '-',
                    'piket' => $t->piket_day ? 'Piket Hari ' . $t->piket_day : '-',
                ];
            }
        @endphp

        {{-- Shift Pagi --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="p-3.5 bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900 text-white flex items-center justify-between">
                <h4 class="text-xs font-extrabold uppercase tracking-wide">Shift Pagi: Kelas VII &amp; IX (07.00 – 11.55 WIB)</h4>
                <span class="text-[11px] text-slate-300 font-medium">Hari {{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }} &bull; Klik/Tap kode guru untuk melihat detail</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-center border-collapse">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-extrabold">
                            <th class="p-2 border-r border-slate-200 w-12">Jam</th>
                            <th class="p-2 border-r border-slate-200 w-28 whitespace-nowrap">Waktu</th>
                            @foreach(['7A','7B','7C','7D','7E','7F','7G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-emerald-50 text-emerald-900 min-w-[50px]">{{ $c }}</th>
                            @endforeach
                            @foreach(['9A','9B','9C','9D','9E','9F','9G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-blue-50 text-blue-900 min-w-[50px]">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($pagiSlots as $slot)
                            @if($slot['is_activity'])
                                <tr class="bg-amber-50/70 font-bold text-amber-900">
                                    <td class="p-2 border-r border-slate-200 text-slate-500 font-semibold">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="14" class="p-2 text-center uppercase tracking-wider text-amber-900 font-black">{{ $slot['activity_name'] }}</td>
                                </tr>
                            @else
                                <tr class="hover:bg-slate-50">
                                    <td class="p-2 border-r border-slate-200 font-bold text-slate-600 bg-slate-50/50">{{ $slot['period_order'] }}</td>
                                    <td class="p-2 border-r border-slate-200 text-slate-600 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    @foreach(['7A','7B','7C','7D','7E','7F','7G','9A','9B','9C','9D','9E','9F','9G'] as $c)
                                        @php
                                            $item = $slot['classes'][$c] ?? null;
                                            $isMySlot = $item && $item->user_id === $user->id;
                                        @endphp
                                        <td class="p-1 border-r border-slate-200 {{ $isMySlot ? 'bg-emerald-50 ring-2 ring-emerald-500 rounded-sm' : '' }}">
                                            @if($item && $item->teacher_code && $item->teacher_code !== '-')
                                                @php
                                                    $tMeta = $teacherLookup[$item->teacher_code] ?? null;
                                                    $tName = $item->teacher_name ?: ($tMeta['name'] ?? 'Guru Kode ' . $item->teacher_code);
                                                    $tSubj = $item->subject_name ?: ($tMeta['subject'] ?? '-');
                                                    $tHome = $tMeta['homeroom'] ?? '-';
                                                    $tPiket = $tMeta['piket'] ?? '-';
                                                @endphp
                                                <button type="button"
                                                        @click="openTeacherDetail({
                                                            code: '{{ $item->teacher_code }}',
                                                            name: '{{ addslashes($tName) }}',
                                                            subject: '{{ addslashes($tSubj) }}',
                                                            className: '{{ $c }}',
                                                            periodOrder: '{{ $slot['period_order'] }}',
                                                            timeRange: '{{ $slot['time_range'] }}',
                                                            dayName: '{{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }}',
                                                            isConflict: {{ $item->is_conflict ? 'true' : 'false' }},
                                                            conflictNotes: '{{ addslashes($item->conflict_notes ?: '') }}',
                                                            homeroom: '{{ addslashes($tHome) }}',
                                                            piket: '{{ addslashes($tPiket) }}'
                                                        })"
                                                        title="{{ $tName }} ({{ $tSubj }})"
                                                        class="w-full py-1.5 px-0.5 rounded-lg text-center transition cursor-pointer flex flex-col items-center justify-center font-extrabold text-xs active:scale-95 {{ $item->is_conflict ? 'bg-rose-600 text-white animate-pulse' : ($isMySlot ? 'bg-emerald-600 text-white shadow-xs' : 'hover:bg-slate-200 hover:text-slate-900 text-slate-800') }}">
                                                    <span>{{ $item->teacher_code }}</span>
                                                    @if($item->is_conflict)
                                                        <span class="text-[8px] uppercase tracking-tighter">BENTROK</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300 font-normal">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Shift Siang --}}
        @if(count($siangSlots) > 0)
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="p-3.5 bg-gradient-to-r from-amber-950 via-slate-900 to-slate-900 text-white flex items-center justify-between">
                <h4 class="text-xs font-extrabold uppercase tracking-wide">Shift Siang: Kelas VIII (12.00 – 16.25 WIB)</h4>
                <span class="text-[11px] text-slate-300 font-medium">Hari {{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }} &bull; Klik/Tap kode guru untuk melihat detail</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-center border-collapse">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-extrabold">
                            <th class="p-2 border-r border-slate-200 w-12">Jam</th>
                            <th class="p-2 border-r border-slate-200 w-28 whitespace-nowrap">Waktu</th>
                            @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                                <th class="p-2 border-r border-slate-200 bg-amber-50 text-amber-900 min-w-[65px]">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($siangSlots as $slot)
                            @if($slot['is_activity'])
                                <tr class="bg-amber-50/70 font-bold text-amber-900">
                                    <td class="p-2 border-r border-slate-200 text-slate-500 font-semibold">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="7" class="p-2 text-center uppercase tracking-wider text-amber-900 font-black">{{ $slot['activity_name'] }}</td>
                                </tr>
                            @else
                                <tr class="hover:bg-slate-50">
                                    <td class="p-2 border-r border-slate-200 font-bold text-slate-600 bg-slate-50/50">{{ $slot['period_order'] }}</td>
                                    <td class="p-2 border-r border-slate-200 text-slate-600 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                                        @php
                                            $item = $slot['classes'][$c] ?? null;
                                            $isMySlot = $item && $item->user_id === $user->id;
                                        @endphp
                                        <td class="p-1 border-r border-slate-200 {{ $isMySlot ? 'bg-amber-50 ring-2 ring-amber-500 rounded-sm' : '' }}">
                                            @if($item && $item->teacher_code && $item->teacher_code !== '-')
                                                @php
                                                    $tMeta = $teacherLookup[$item->teacher_code] ?? null;
                                                    $tName = $item->teacher_name ?: ($tMeta['name'] ?? 'Guru Kode ' . $item->teacher_code);
                                                    $tSubj = $item->subject_name ?: ($tMeta['subject'] ?? '-');
                                                    $tHome = $tMeta['homeroom'] ?? '-';
                                                    $tPiket = $tMeta['piket'] ?? '-';
                                                @endphp
                                                <button type="button"
                                                        @click="openTeacherDetail({
                                                            code: '{{ $item->teacher_code }}',
                                                            name: '{{ addslashes($tName) }}',
                                                            subject: '{{ addslashes($tSubj) }}',
                                                            className: '{{ $c }}',
                                                            periodOrder: '{{ $slot['period_order'] }}',
                                                            timeRange: '{{ $slot['time_range'] }}',
                                                            dayName: '{{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }}',
                                                            isConflict: {{ $item->is_conflict ? 'true' : 'false' }},
                                                            conflictNotes: '{{ addslashes($item->conflict_notes ?: '') }}',
                                                            homeroom: '{{ addslashes($tHome) }}',
                                                            piket: '{{ addslashes($tPiket) }}'
                                                        })"
                                                        title="{{ $tName }} ({{ $tSubj }})"
                                                        class="w-full py-1.5 px-0.5 rounded-lg text-center transition cursor-pointer flex flex-col items-center justify-center font-extrabold text-xs active:scale-95 {{ $item->is_conflict ? 'bg-rose-600 text-white animate-pulse' : ($isMySlot ? 'bg-amber-600 text-white shadow-xs' : 'hover:bg-slate-200 hover:text-slate-900 text-slate-800') }}">
                                                    <span>{{ $item->teacher_code }}</span>
                                                    @if($item->is_conflict)
                                                        <span class="text-[8px] uppercase tracking-tighter">BENTROK</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300 font-normal">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- TAB 3: GURU PIKET & WALI KELAS --}}
    <div x-show="tab === 'piket'" class="space-y-5" style="display: none;">
        {{-- Jadwal Guru Piket Harian --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs">
            <h3 class="text-sm font-extrabold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span>Jadwal Guru Piket Harian (Senin s.d. Sabtu)</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @php
                    $piketList = [
                        'SENIN' => 'MOH. RIFKI FIRDIANSYAH, S.Pd',
                        'SELASA' => 'ISMAIL, S.Pd. I',
                        'RABU' => 'TETI OKTAVIANI, S.Pd',
                        'KAMIS' => 'PURWANTO, S.Pd',
                        'JUM\'AT' => 'NURUL MAULINA F., S.Pd',
                        'SABTU' => 'LAILINA MUZIYAH, S.Pd',
                    ];
                @endphp
                @foreach($piketList as $hName => $gName)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider">{{ $hName }}</span>
                            <div class="text-xs font-extrabold text-slate-900 mt-0.5">{{ $gName }}</div>
                        </div>
                        <span class="px-2 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold">Piket</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Daftar 21 Wali Kelas --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs">
            <h3 class="text-sm font-extrabold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                <span>Daftar 21 Wali Kelas (Tingkat VII, VIII, IX)</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                @foreach($allTeachers->whereNotNull('homeroom_class') as $t)
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-extrabold text-[10px]">
                                Kelas {{ $t->homeroom_class }}
                            </span>
                            <div class="text-xs font-bold text-slate-900 mt-1 truncate">{{ $t->name }}</div>
                        </div>
                        <span class="text-[11px] text-slate-500 font-medium">Kode {{ $t->code }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- POPUP MODAL DETAIL GURU SAAT TAP / KLIK KODE GURU --}}
    <div x-show="detailModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @keydown.escape.window="detailModalOpen = false"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div @click.away="detailModalOpen = false"
             class="bg-white rounded-3xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-slate-100 transform transition-all space-y-4"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            {{-- Header Modal --}}
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-sm shrink-0">
                        <span x-text="selectedTeacher.code"></span>
                    </div>
                    <div>
                        <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-extrabold text-[10px] tracking-wide uppercase">
                            KODE GURU <span x-text="selectedTeacher.code"></span>
                        </span>
                        <h3 class="text-sm font-extrabold text-slate-900 mt-0.5 line-clamp-2" x-text="selectedTeacher.name"></h3>
                    </div>
                </div>
                <button type="button" @click="detailModalOpen = false"
                        class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Mata Pelajaran Pill & KBM Slot --}}
            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/80 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Mata Pelajaran:</span>
                    <span class="font-extrabold text-emerald-800 text-right" x-text="selectedTeacher.subject"></span>
                </div>
                <div class="h-px bg-slate-200/60"></div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Kelas &amp; Jam:</span>
                    <span class="font-bold text-slate-900 text-right">
                        Kelas <span x-text="selectedTeacher.className"></span> &bull; Jam <span x-text="selectedTeacher.periodOrder"></span>
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Waktu KBM:</span>
                    <span class="font-bold text-slate-700 text-right">
                        <span x-text="selectedTeacher.dayName"></span> (<span x-text="selectedTeacher.timeRange"></span>)
                    </span>
                </div>
            </div>

            {{-- Info Tambahan: Wali Kelas & Piket --}}
            <div class="space-y-1.5 text-xs">
                <template x-if="selectedTeacher.homeroom && selectedTeacher.homeroom !== '-'">
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-blue-50 text-blue-900 border border-blue-100 font-bold">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span x-text="selectedTeacher.homeroom"></span>
                    </div>
                </template>
                <template x-if="selectedTeacher.piket && selectedTeacher.piket !== '-'">
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-purple-50 text-purple-900 border border-purple-100 font-bold">
                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span x-text="selectedTeacher.piket"></span>
                    </div>
                </template>
            </div>

            {{-- Peringatan Bentrok jika ada --}}
            <template x-if="selectedTeacher.isConflict">
                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs">
                    <div class="flex items-center gap-1.5 font-extrabold text-rose-700 uppercase tracking-tight">
                        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Peringatan Jadwal Bentrok</span>
                    </div>
                    <p class="mt-1 font-medium text-rose-800" x-text="selectedTeacher.conflictNotes || 'Guru ini mengajar di lebih dari satu kelas pada jam yang sama.'"></p>
                </div>
            </template>

            {{-- Tombol Tutup --}}
            <button type="button" @click="detailModalOpen = false"
                    class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition">
                Tutup
            </button>
        </div>
    </div>

</div>
@endsection
