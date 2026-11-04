@extends('layouts.app')

@section('title', 'Daftar Kelas Administrasi')
@section('page_title', 'Portofolio Buku Administrasi Kelas (' . $classes->count() . ' Kelas)')
@section('page_subtitle', 'Kelola hingga 15+ kelas ajar per semester secara mandiri — lengkap dengan Jurnal Harian, Absensi, dan Daftar Nilai.')

@section('content')
<div class="space-y-5"
     x-data="{
        createClassModal: false,
        selectedSchoolClassId: '{{ $schoolClasses->first()?->id ?? '' }}',
        schoolClasses: @js($schoolClasses->map(fn($sc) => [
            'id' => (string) $sc->id,
            'name' => $sc->name,
            'grade_level' => $sc->grade_level,
            'homeroom_teacher_name' => $sc->homeroom_teacher_name ?: '-',
            'homeroom_teacher_nip' => $sc->homeroom_teacher_nip ?: '-',
            'active_students_count' => $sc->active_students_count,
        ])),
        get selectedRombel() {
            return this.schoolClasses.find(c => String(c.id) === String(this.selectedSchoolClassId)) || null;
        }
     }">
    {{-- Toolbar Pencarian & Tambah Kelas --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('classes.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama kelas, mapel, atau wali kelas..."
                   class="w-full sm:w-64 rounded-xl border border-slate-300 px-3.5 py-2 text-xs">
            <select name="grade_level" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold">
                <option value="">Semua Tingkat</option>
                @foreach($gradeLevels as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['grade_level'] === $lvl)>Tingkat {{ $lvl }}</option>
                @endforeach
            </select>
            @if(auth()->user()->isAdmin())
                <select name="teacher_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs">
                    <option value="">Semua Guru</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($filters['teacher_id'] === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800">Filter</button>
        </form>

        <button type="button" @click="createClassModal = true"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
            <span>+ Buat Kelas Administrasi Baru</span>
        </button>
    </div>

    {{-- Grid Kartu Kelas (Mendukung 15+ Kelas dengan Rapi & Cepat) --}}
    @if($classes->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold text-xl mb-3">
                +
            </div>
            <h3 class="text-base font-bold text-slate-900">Belum Ada Buku Kelas Administrasi pada {{ $selectedAcademicYear->full_label }}</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Anda dapat membuat 1 hingga 15+ kelas administrasi sesuai jadwal mengajar Anda, lalu mengimpor daftar murid dari Excel dalam hitungan detik.
            </p>
            <button type="button" @click="createClassModal = true"
                    class="mt-4 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700">
                + Buat Kelas Pertama Sekarang
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($classes as $cls)
                @php
                    $kktp = $cls->effective_kktp;
                    $attDays = (int) ($attendanceDaysByClass[$cls->id] ?? 0);
                    $grades = $cls->studentGrades;
                    $avgNa = $grades->count() > 0 ? round($grades->avg('final_score'), 1) : null;
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs hover:border-emerald-400 hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-lg bg-emerald-600 text-white text-xs font-extrabold">{{ $cls->class_name }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">Tingkat {{ $cls->grade_level }}</span>
                                </div>
                                <h3 class="mt-2 text-sm font-extrabold text-slate-900">{{ $cls->subject->name ?? '-' }}</h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-[11px] font-extrabold shrink-0">
                                KKTP {{ $kktp }}
                            </span>
                        </div>

                        <div class="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs text-slate-600">
                            @if(auth()->user()->isAdmin())
                                <div class="truncate"><strong>Guru:</strong> {{ $cls->teacher->name ?? '-' }}</div>
                            @endif
                            <div class="truncate"><strong>Wali Kelas:</strong> {{ $cls->homeroom_teacher_name }} <span class="text-slate-400">(NIP. {{ $cls->homeroom_teacher_nip ?: '-' }})</span></div>
                            @if($cls->schedule_info)
                                <div class="truncate text-[11px] text-slate-500"><strong>Jadwal:</strong> {{ $cls->schedule_info }}</div>
                            @endif
                        </div>

                        {{-- Mini Stats --}}
                        <div class="mt-3.5 grid grid-cols-4 gap-1.5 text-center">
                            <div class="rounded-xl bg-slate-50 p-2 border border-slate-100">
                                <div class="text-xs font-extrabold text-slate-900">{{ $cls->students_count }}</div>
                                <div class="text-[10px] text-slate-500">Murid</div>
                            </div>
                            <div class="rounded-xl bg-indigo-50/60 p-2 border border-indigo-100">
                                <div class="text-xs font-extrabold text-indigo-700">{{ $cls->journals_count }}</div>
                                <div class="text-[10px] text-indigo-600">Jurnal</div>
                            </div>
                            <div class="rounded-xl bg-emerald-50/60 p-2 border border-emerald-100">
                                <div class="text-xs font-extrabold text-emerald-700">{{ $attDays }}</div>
                                <div class="text-[10px] text-emerald-600">Hr Absen</div>
                            </div>
                            <div class="rounded-xl bg-amber-50/60 p-2 border border-amber-100">
                                <div class="text-xs font-extrabold text-amber-800">{{ $avgNa !== null ? $avgNa : '-' }}</div>
                                <div class="text-[10px] text-amber-700">Rata NA</div>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Action Footer --}}
                    <div class="px-4 py-3 bg-slate-50 border-t border-slate-200/80 grid grid-cols-4 gap-1.5 text-center text-[11px] font-bold">
                        <a href="{{ route('journals.index', $cls) }}" class="py-1.5 rounded-lg bg-white border border-slate-200 text-indigo-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition">
                            Jurnal
                        </a>
                        <a href="{{ route('attendances.index', $cls) }}" class="py-1.5 rounded-lg bg-white border border-slate-200 text-emerald-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition">
                            Absensi
                        </a>
                        <a href="{{ route('grades.index', $cls) }}" class="py-1.5 rounded-lg bg-white border border-slate-200 text-amber-800 hover:bg-amber-600 hover:text-white hover:border-amber-600 transition">
                            Nilai
                        </a>
                        <a href="{{ route('classes.settings', $cls) }}" class="py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-800 hover:text-white transition">
                            Pengaturan
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal Buat Kelas Administrasi Baru --}}
    <div x-show="createClassModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="createClassModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-bold">Buat Buku Kelas Administrasi Baru</h3>
                    <p class="text-[11px] text-slate-300">Periode: {{ $selectedAcademicYear->full_label }}</p>
                </div>
                <button type="button" @click="createClassModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('classes.store') }}" class="p-5 space-y-3.5 text-xs overflow-y-auto flex-1">
                @csrf
                @if(auth()->user()->isAdmin())
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Pemilik Kelas (Guru Pengampu) *</label>
                        <select name="teacher_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} (NIP. {{ $t->nip ?: '-' }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Pilihan Rombongan Belajar Terpusat --}}
                @if($schoolClasses->isNotEmpty())
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Pilih Rombongan Belajar (Rombel) @if(!auth()->user()->isAdmin()) * @endif
                        </label>
                        <select name="school_class_id"
                                x-model="selectedSchoolClassId"
                                @if(!auth()->user()->isAdmin()) required @endif
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800 bg-white">
                            @if(auth()->user()->isAdmin())
                                <option value="">— Buat Rombel Bebas / Mandiri (Tanpa Rombel Terpusat) —</option>
                            @else
                                <option value="" disabled>— Pilih Rombel Resmi yang Dikelola Admin —</option>
                            @endif
                            @foreach($schoolClasses as $sc)
                                <option value="{{ $sc->id }}">
                                    Kelas {{ $sc->name }} (Tingkat {{ $sc->grade_level }} • {{ $sc->active_students_count }} Murid)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Card Info Rombel Terpilih --}}
                    <template x-if="selectedRombel">
                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 space-y-1">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-emerald-900" x-text="'Rombel: ' + selectedRombel.name + ' (Tingkat ' + selectedRombel.grade_level + ')'"></span>
                                <span class="px-2 py-0.5 rounded-md bg-emerald-200 text-emerald-900 font-extrabold text-[10px]"
                                      x-text="selectedRombel.active_students_count + ' Murid Terhubung'"></span>
                            </div>
                            <div class="text-[11px] text-emerald-800">
                                <strong>Wali Kelas:</strong> <span x-text="selectedRombel.homeroom_teacher_name"></span>
                                <span x-show="selectedRombel.homeroom_teacher_nip && selectedRombel.homeroom_teacher_nip !== '-'"
                                      x-text="' (NIP. ' + selectedRombel.homeroom_teacher_nip + ')'"></span>
                            </div>
                            <div class="text-[10px] text-emerald-700 italic">
                                Seluruh daftar murid & identitas wali kelas akan otomatis sinkron dengan guru lain di kelas ini.
                            </div>
                        </div>
                    </template>
                @elseif(!auth()->user()->isAdmin())
                    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1">
                        <div class="font-bold text-xs">Belum Ada Rombel Tersedia</div>
                        <div class="text-[11px] leading-relaxed">
                            Administrator Sekolah belum membuat data Rombel untuk tahun ajaran ini. Silakan hubungi Administrator untuk menambahkan data Rombel dan murid terlebih dahulu.
                        </div>
                    </div>
                @endif

                {{-- Mata Pelajaran & Jadwal --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran *</label>
                        <select name="subject_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                            @foreach($subjects as $sub)
                                @php
                                    $hasCode = str_contains(strtoupper($sub->name), '(' . strtoupper($sub->code) . ')');
                                @endphp
                                <option value="{{ $sub->id }}">{{ $hasCode ? $sub->name : "{$sub->name} ({$sub->code})" }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Jadwal Mengajar</label>
                        <input type="text" name="schedule_info" placeholder="Misal: Sen (1-2)"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                </div>

                {{-- Field Manual (Hanya Tampil Jika Rombel Belum Dipilih / Khusus Admin) --}}
                <div x-show="!selectedRombel" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tingkat Kelas *</label>
                            <select name="grade_level" :required="!selectedRombel" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                                @foreach($gradeLevels as $lvl)
                                    <option value="{{ $lvl }}">Kelas {{ $lvl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Kelas / Rombel *</label>
                            <input type="text" name="class_name" :required="!selectedRombel" placeholder="Contoh: VII-C atau 8E"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="font-bold text-slate-800">Data Wali Kelas (Wajib untuk Kolom Cetakan)</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Nama Wali Kelas *</label>
                                <input type="text" name="homeroom_teacher_name" :required="!selectedRombel" placeholder="Contoh: Dra. Ratna Sari"
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">NIP Wali Kelas</label>
                                <input type="text" name="homeroom_teacher_nip" placeholder="19750618 200501 2 007"
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            </div>
                        </div>
                    </div>

                    @if($copyableClasses->isNotEmpty())
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Salin Murid Otomatis dari Kelas Lain (Opsional)</label>
                            <select name="copy_students_from_class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                                <option value="">— Kosongkan —</option>
                                @foreach($copyableClasses as $cc)
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->class_name }} — {{ $cc->subject->name ?? '' }} ({{ $cc->academicYear->year_name ?? '' }} {{ $cc->academicYear->semester ?? '' }} • {{ $cc->students_count }} murid)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="pt-3 flex flex-col-reverse sm:flex-row justify-end gap-2 border-t border-slate-100 shrink-0">
                    <button type="button" @click="createClassModal = false" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-300 font-semibold text-center">Batal</button>
                    <button type="submit" class="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs text-center">Buat Kelas Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
