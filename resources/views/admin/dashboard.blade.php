@extends('layouts.app')

@section('title', 'Dashboard Monitoring Admin')
@section('page_title', 'Dashboard Monitoring & Kontrol Administrasi Guru')
@section('page_subtitle', 'Pantau keaktifan pengisian Jurnal Harian, Absensi, serta capaian Nilai & KKTP seluruh guru pada ' . $selectedAcademicYear->full_label)

@section('content')
<div class="space-y-5">
    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-xs font-semibold text-slate-500">Guru Aktif</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $kpis['total_teachers'] }}</div>
            <div class="mt-1 text-[11px] text-emerald-600 font-medium">Terdaftar di sistem</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-xs font-semibold text-slate-500">Total Kelas Administrasi</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $kpis['total_classes'] }}</div>
            <div class="mt-1 text-[11px] text-slate-500">{{ $selectedAcademicYear->full_label }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-xs font-semibold text-slate-500">Jurnal Harian Terisi</div>
            <div class="mt-1 text-2xl font-extrabold text-indigo-600">{{ $kpis['total_journals'] }}</div>
            <div class="mt-1 text-[11px] text-slate-500">Total pertemuan tercatat</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-xs font-semibold text-slate-500">Hari Absensi Tercatat</div>
            <div class="mt-1 text-2xl font-extrabold text-emerald-600">{{ $kpis['total_attendance_days'] }}</div>
            <div class="mt-1 text-[11px] text-slate-500">Akumulasi seluruh kelas</div>
        </div>
        <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-xs font-semibold text-slate-500">Rata-rata Ketuntasan KKTP</div>
            <div class="mt-1 text-2xl font-extrabold {{ $kpis['overall_tuntas_rate'] >= 75 ? 'text-emerald-600' : 'text-amber-600' }}">
                {{ number_format($kpis['overall_tuntas_rate'], 1) }}%
            </div>
            <div class="mt-1 text-[11px] text-slate-500">Siswa mencapai KKTP</div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('admin.dashboard') }}"
          class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Filter Guru</label>
            <select name="teacher_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
                <option value="">Semua Guru ({{ $teachers->count() }})</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected($filters['teacher_id'] === $t->id)>
                        {{ $t->name }} ({{ $t->active_classes_count }} kelas)
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Filter Mata Pelajaran</label>
            <select name="subject_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
                <option value="">Semua Mata Pelajaran</option>
                @foreach($subjects as $sub)
                    <option value="{{ $sub->id }}" @selected($filters['subject_id'] === $sub->id)>{{ $sub->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tingkat Kelas</label>
            <select name="grade_level" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
                <option value="">Semua Tingkat</option>
                @foreach($globalSchool->gradeLevels() as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['grade_level'] === $lvl)>Tingkat {{ $lvl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pencarian Cepat</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nama kelas / guru / wali kelas..."
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition">
                Terapkan Filter
            </button>
            <a href="{{ route('admin.dashboard') }}" class="py-2 px-3 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>

    {{-- Tabel Monitoring Seluruh Kelas & Guru --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Matriks Pengawasan Buku Administrasi Seluruh Guru</h2>
                <p class="text-xs text-slate-500">Klik tombol menu pada baris kelas untuk memantau, mengedit, atau mengunduh cetakan PDF/Excel kelas tersebut.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.teachers.index') }}" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100">
                    + Buat Akun Guru
                </a>
                <a href="{{ route('admin.kktp.index') }}" class="px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold hover:bg-indigo-100">
                    Atur KKM / KKTP
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Guru Pengampu</th>
                        <th class="px-4 py-3">Kelas & Mapel</th>
                        <th class="px-4 py-3">Wali Kelas</th>
                        <th class="px-3 py-3 text-center">Murid</th>
                        <th class="px-3 py-3 text-center">Jurnal Harian</th>
                        <th class="px-3 py-3 text-center">Absensi</th>
                        <th class="px-3 py-3 text-center">Nilai & KKTP</th>
                        <th class="px-4 py-3 text-right">Kontrol Langsung Semua Menu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($monitoringRows as $item)
                        @php $cls = $item['class']; @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $cls->teacher->name ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">NIP. {{ $cls->teacher->nip ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded bg-emerald-600 text-white font-extrabold text-[11px]">{{ $cls->class_name }}</span>
                                    <span class="font-bold text-slate-800">{{ $cls->subject->name ?? '-' }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    Tingkat {{ $cls->grade_level }} &bull; <strong class="text-indigo-700">KKTP {{ $item['kktp'] }}</strong>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $cls->homeroom_teacher_name }}</div>
                                <div class="text-[11px] text-slate-500">NIP. {{ $cls->homeroom_teacher_nip ?: '-' }}</div>
                            </td>
                            <td class="px-3 py-3 text-center font-bold text-slate-800">
                                {{ $cls->students_count }}
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $cls->journals_count > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $cls->journals_count }} entri
                                </span>
                                @if($item['last_journal_date'])
                                    <div class="text-[10px] text-slate-400 mt-0.5">Terakhir: {{ \Carbon\Carbon::parse($item['last_journal_date'])->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $item['attendance_days'] > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $item['attendance_days'] }} hari
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if($item['graded_count'] > 0)
                                    <div class="font-bold text-slate-800">Rata NA: {{ number_format($item['avg_na'], 1) }}</div>
                                    <div class="text-[10px] font-semibold {{ $item['tuntas_rate'] >= 75 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        Tuntas: {{ $item['tuntas_count'] }}/{{ $item['graded_count'] }} ({{ $item['tuntas_rate'] }}%)
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400">Belum dinilai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-1">
                                    <a href="{{ route('journals.index', $cls) }}" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold hover:bg-indigo-100">Jurnal</a>
                                    <a href="{{ route('attendances.index', $cls) }}" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold hover:bg-emerald-100">Absensi</a>
                                    <a href="{{ route('grades.index', $cls) }}" class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 font-bold hover:bg-amber-100">Nilai</a>
                                    <a href="{{ route('classes.students', $cls) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">Murid</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-500">
                                Belum ada kelas administrasi yang sesuai dengan filter pada {{ $selectedAcademicYear->full_label }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
