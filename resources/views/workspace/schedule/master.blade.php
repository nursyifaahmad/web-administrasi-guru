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
        @endphp

        {{-- Shift Pagi --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="p-3.5 bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900 text-white flex items-center justify-between">
                <h4 class="text-xs font-extrabold uppercase tracking-wide">Shift Pagi: Kelas VII &amp; IX (07.00 – 11.55 WIB)</h4>
                <span class="text-[11px] text-slate-300">Hari {{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }}</span>
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
                                    <td class="p-2 border-r border-slate-200">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="14" class="p-2 text-center uppercase tracking-wider">{{ $slot['activity_name'] }}</td>
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
                                        <td class="p-1 border-r border-slate-200 {{ $isMySlot ? 'bg-emerald-100 font-extrabold text-emerald-900 ring-2 ring-emerald-500' : '' }}">
                                            <span class="font-bold text-xs" title="{{ $item?->teacher_name }} ({{ $item?->subject_name }})">
                                                {{ $item?->teacher_code ?: '-' }}
                                            </span>
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
                <span class="text-[11px] text-slate-300">Hari {{ \App\Services\SchoolScheduleService::DAYS_MAP[$activeDay] ?? '' }}</span>
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
                                    <td class="p-2 border-r border-slate-200">{{ $slot['period_order'] ?: '-' }}</td>
                                    <td class="p-2 border-r border-slate-200 whitespace-nowrap">{{ $slot['time_range'] }}</td>
                                    <td colspan="7" class="p-2 text-center uppercase tracking-wider">{{ $slot['activity_name'] }}</td>
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
                                        <td class="p-1 border-r border-slate-200 {{ $isMySlot ? 'bg-amber-100 font-extrabold text-amber-900 ring-2 ring-amber-500' : '' }}">
                                            <span class="font-bold text-xs" title="{{ $item?->teacher_name }} ({{ $item?->subject_name }})">
                                                {{ $item?->teacher_code ?: '-' }}
                                            </span>
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

</div>
@endsection
