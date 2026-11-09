@extends('layouts.app')

@section('title', 'Buku Administrasi Kelas')
@section('page_title', 'Buku Administrasi Kelas')

@section('content')
<div class="space-y-5"
     x-data="{
        createClassModal: false,
        selectedTeacherId: '{{ auth()->id() }}',
        allocations: @js($scheduleAllocations),
        selectedAllocKey: '',
        useScheduleMode: true,
        isLoadingAllocations: false,

        selectedSchoolClassId: '{{ $schoolClasses->first()?->id ?? '' }}',
        selectedSubjectId: '{{ auth()->user()?->preferredSubjectId() ?: ($subjects->first()?->id ?? '') }}',
        scheduleInfoText: '',
        teachingDaysJson: '',

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
        },

        get currentAllocation() {
            return this.allocations.find(a => a.key === this.selectedAllocKey) || null;
        },

        get pendingAllocations() {
            return this.allocations.filter(a => !a.already_created);
        },

        onTeacherChanged() {
            if (!this.selectedTeacherId) return;
            this.isLoadingAllocations = true;
            fetch('{{ route('classes.schedule-allocations') }}?teacher_id=' + this.selectedTeacherId)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.allocations = data.allocations || [];
                        this.autoSelectFirstPending();
                    }
                })
                .catch(err => console.error(err))
                .finally(() => {
                    this.isLoadingAllocations = false;
                });
        },

        onAllocationSelected() {
            const alloc = this.currentAllocation;
            if (!alloc) return;

            if (alloc.school_class_id) {
                this.selectedSchoolClassId = String(alloc.school_class_id);
            } else {
                const matchSc = this.schoolClasses.find(sc => sc.name === alloc.class_name);
                if (matchSc) {
                    this.selectedSchoolClassId = String(matchSc.id);
                }
            }

            if (alloc.subject_id) {
                this.selectedSubjectId = String(alloc.subject_id);
            }

            this.scheduleInfoText = alloc.schedule_info || '';
            this.teachingDaysJson = JSON.stringify(alloc.teaching_days || []);
        },

        autoSelectFirstPending() {
            this.selectedAllocKey = '';
            const firstPending = this.allocations.find(a => !a.already_created);
            if (firstPending) {
                this.selectedAllocKey = firstPending.key;
                this.onAllocationSelected();
            } else {
                this.selectedSchoolClassId = '{{ $schoolClasses->first()?->id ?? '' }}';
                this.selectedSubjectId = '{{ $subjects->first()?->id ?? '' }}';
                this.scheduleInfoText = '';
                this.teachingDaysJson = '';
            }
        },

        selectedClasses: [],
        bulkDeleteModal: false,
        confirmText: '',
        allDeletableClasses: @js($classes->filter(fn($c) => (int)$c->teacher_id === (int)auth()->id())->map(fn($c) => [
            'id' => (string) $c->id,
            'name' => $c->class_name,
            'subject' => $c->subject?->name ?? '-',
        ])->values()),

        get allDeletableIds() {
            return this.allDeletableClasses.map(c => c.id);
        },

        get isAllSelected() {
            return this.allDeletableIds.length > 0 && this.selectedClasses.length === this.allDeletableIds.length;
        },

        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedClasses = [];
            } else {
                this.selectedClasses = [...this.allDeletableIds];
            }
        },

        openBulkDeleteModal() {
            if (this.selectedClasses.length === 0) return;
            this.confirmText = '';
            this.bulkDeleteModal = true;
        },

        get selectedClassesDetails() {
            return this.allDeletableClasses.filter(c => this.selectedClasses.includes(c.id));
        },

        init() {
            this.autoSelectFirstPending();
        }
     }">

    {{-- Tab Switcher Khusus Akun Admin/Wakasek --}}
    @if(auth()->user()->isAdmin())
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/80 gap-1">
                <a href="{{ route('classes.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $effectiveView === 'my' ? 'bg-white text-emerald-700 shadow-2xs border border-slate-200/70' : 'text-slate-600 hover:text-slate-900' }}">
                    <svg class="w-4 h-4 {{ $effectiveView === 'my' ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Kelas Saya</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $effectiveView === 'my' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $myClassesCount }}</span>
                </a>
                <a href="{{ route('classes.index', ['view' => 'all']) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $effectiveView === 'all' ? 'bg-white text-indigo-700 shadow-2xs border border-slate-200/70' : 'text-slate-600 hover:text-slate-900' }}">
                    <svg class="w-4 h-4 {{ $effectiveView === 'all' ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Supervisi Seluruh Guru</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $effectiveView === 'all' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700' }}">{{ $allClassesCount }}</span>
                </a>
            </div>

            <div class="text-[11px] text-slate-500 pr-1">
                @if($effectiveView === 'my')
                    Menampilkan buku kelas yang diampu langsung oleh Anda (<strong>{{ auth()->user()->name }}</strong>).
                @else
                    Mode Supervisi Monitoring: Menampilkan buku kelas seluruh dewan guru sekolah.
                @endif
            </div>
        </div>

        @if($effectiveView === 'all')
            <div class="p-3.5 rounded-2xl bg-indigo-50/80 border border-indigo-200 text-indigo-950 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                        👁️
                    </div>
                    <div>
                        <div class="font-bold text-indigo-900">Mode Supervisi Administrator: Monitoring Seluruh Kelas</div>
                        <div class="text-[11px] text-indigo-700">Menampilkan buku administrasi KBM seluruh dewan guru untuk pemantauan jurnal mengajar, presensi, dan rekap nilai.</div>
                    </div>
                </div>
                <a href="{{ route('classes.index') }}" class="px-3 py-1.5 rounded-xl bg-white border border-indigo-200 text-indigo-700 font-bold hover:bg-indigo-100 transition inline-flex items-center gap-1.5">
                    <span>&larr; Kembali ke Kelas Saya</span>
                </a>
            </div>
        @endif
    @endif

    {{-- Toolbar Pencarian & Tambah Kelas --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('classes.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            @if(auth()->user()->isAdmin() && $effectiveView === 'all')
                <input type="hidden" name="view" value="all">
            @endif
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama kelas, mapel, atau wali kelas..."
                   class="w-full sm:w-64 rounded-xl border border-slate-300 px-3.5 py-2 text-xs">
            <select name="grade_level" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold">
                <option value="">Semua Tingkat</option>
                @foreach($gradeLevels as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['grade_level'] === $lvl)>Tingkat {{ $lvl }}</option>
                @endforeach
            </select>
            @if(auth()->user()->isAdmin() && $effectiveView === 'all')
                <select name="teacher_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs">
                    <option value="">Semua Guru</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($filters['teacher_id'] === $t->id)>{{ $t->name }} ({{ $t->role === 'admin' ? 'Admin' : 'Guru' }})</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800">Filter</button>
            @if(!empty($filters['q']) || !empty($filters['grade_level']) || !empty($filters['teacher_id']))
                <a href="{{ $effectiveView === 'all' ? route('classes.index', ['view' => 'all']) : route('classes.index') }}"
                   class="px-2.5 py-2 rounded-xl text-slate-500 hover:text-slate-800 text-xs font-semibold">Reset</a>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-2">
            @php
                $pendingScheduleCount = $scheduleAllocations->where('already_created', false)->count();
            @endphp
            @if($pendingScheduleCount > 0 && (!auth()->user()->isAdmin() || $effectiveView === 'my'))
                <form method="POST" action="{{ route('classes.sync-from-schedule') }}" class="inline">
                    @csrf
                    <input type="hidden" name="teacher_id" value="{{ auth()->id() }}">
                    <button type="submit"
                            title="Buat sekaligus seluruh {{ $pendingScheduleCount }} kelas mengajar Anda sesuai Master Jadwal Sekolah"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-2xs transition">
                        <span>⚡ Buat Sekaligus dari Jadwal ({{ $pendingScheduleCount }} Kelas)</span>
                    </button>
                </form>
            @endif

            <button type="button" @click="createClassModal = true; autoSelectFirstPending()"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
                <span>+ Buat Kelas Administrasi Baru</span>
            </button>
        </div>
    </div>

    {{-- Grid Kartu Kelas (Mendukung 15+ Kelas dengan Rapi & Cepat) --}}
    @if($classes->isEmpty())
        @if(auth()->user()->isAdmin() && $effectiveView === 'my')
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold text-2xl mb-3">
                    👤
                </div>
                <h3 class="text-base font-bold text-slate-900">Belum Ada Buku Kelas Administrasi Anda ({{ auth()->user()->name }})</h3>
                <p class="text-xs text-slate-500 mt-1.5 max-w-md mx-auto leading-relaxed">
                    Anda sedang login sebagai <strong>Administrator/Wakasek</strong> dan saat ini belum memiliki buku kelas mengajar pribadi yang diampu langsung pada periode {{ $selectedAcademicYear->full_label }}.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    <button type="button" @click="createClassModal = true"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
                        + Buat Kelas Saya Baru
                    </button>
                    <a href="{{ route('classes.index', ['view' => 'all']) }}"
                       class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 shadow-2xs transition">
                        🏫 Lihat Seluruh Kelas Guru (Supervisi {{ $allClassesCount }} Kelas)
                    </a>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold text-xl mb-3">
                    📚
                </div>
                <h3 class="text-base font-bold text-slate-900">Belum Ada Buku Kelas Administrasi pada {{ $selectedAcademicYear->full_label }}</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    @if($scheduleAllocations->isNotEmpty())
                        Master Jadwal Sekolah mendeteksi Anda memiliki <strong>{{ $scheduleAllocations->count() }} rombel mengajar</strong>. Anda dapat membuat sekaligus seluruh buku kelas atau membuatnya satu per satu dengan panduan resmi.
                    @else
                        Anda dapat membuat 1 hingga 15+ kelas administrasi sesuai jadwal mengajar Anda, lalu mengimpor daftar murid dari Excel dalam hitungan detik.
                    @endif
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                    @if($pendingScheduleCount > 0)
                        <form method="POST" action="{{ route('classes.sync-from-schedule') }}" class="inline">
                            @csrf
                            <input type="hidden" name="teacher_id" value="{{ auth()->id() }}">
                            <button type="submit"
                                    class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-2xs transition">
                                ⚡ Buat Sekaligus dari Master Jadwal ({{ $pendingScheduleCount }} Kelas)
                            </button>
                        </form>
                    @endif
                    <button type="button" @click="createClassModal = true; autoSelectFirstPending()"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
                        + Buat Kelas Baru Terpandu
                    </button>
                </div>
            </div>
        @endif
    @else
        {{-- Bilah Aksi Massal & Pilih Semua --}}
        @php
            $myDeletableClassesCount = $classes->filter(fn($c) => (int)$c->teacher_id === (int)auth()->id())->count();
        @endphp
        @if($myDeletableClassesCount > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-2xs">
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-bold text-slate-700">
                        <input type="checkbox"
                               @change="toggleSelectAll()"
                               :checked="isAllSelected"
                               class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300 transition cursor-pointer">
                        <span>Pilih Semua (<span x-text="allDeletableClasses.length"></span> Buku Kelas)</span>
                    </label>
                    <template x-if="selectedClasses.length > 0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                            <span x-text="selectedClasses.length"></span> buku dipilih
                        </span>
                    </template>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            x-show="selectedClasses.length > 0"
                            x-cloak
                            @click="selectedClasses = []"
                            class="px-3 py-1.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-100 text-xs font-semibold transition cursor-pointer">
                        Batal Pilih
                    </button>
                    <button type="button"
                            x-show="selectedClasses.length > 0"
                            x-cloak
                            @click="openBulkDeleteModal()"
                            class="px-4 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-2xs transition inline-flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>Hapus (<span x-text="selectedClasses.length"></span>) Buku Kelas Terpilih</span>
                    </button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($classes as $cls)
                @php
                    $kktp = $cls->effective_kktp;
                    $attDays = (int) ($attendanceDaysByClass[$cls->id] ?? 0);
                    $grades = $cls->studentGrades;
                    $avgNa = $grades->count() > 0 ? round($grades->avg('final_score'), 1) : null;
                    $isOwnClass = (int) $cls->teacher_id === (int) auth()->id();
                @endphp
                <div class="bg-white rounded-2xl border transition flex flex-col justify-between overflow-hidden relative"
                     :class="selectedClasses.includes('{{ $cls->id }}') ? 'border-rose-400 ring-2 ring-rose-400/30 bg-rose-50/10 shadow-md' : 'border-slate-200 hover:border-emerald-400 shadow-2xs hover:shadow-md'">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                @if($isOwnClass)
                                    <div class="pt-0.5 shrink-0">
                                        <input type="checkbox"
                                               value="{{ $cls->id }}"
                                               x-model="selectedClasses"
                                               title="Pilih buku kelas {{ $cls->class_name }} untuk dihapus massal"
                                               class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300 transition cursor-pointer">
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-600 text-white text-xs font-extrabold">{{ $cls->class_name }}</span>
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">Tingkat {{ $cls->grade_level }}</span>
                                    </div>
                                    <h3 class="mt-2 text-sm font-extrabold text-slate-900 truncate">{{ $cls->subject->name ?? '-' }}</h3>
                                </div>
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
                                <div class="mt-1 flex items-center gap-1.5 text-[11px] text-emerald-800 bg-emerald-50/80 border border-emerald-200 rounded-lg px-2.5 py-1">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="truncate font-bold">{{ $cls->schedule_info }}</span>
                                </div>
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
            <form id="syncFromScheduleModalForm" method="POST" action="{{ route('classes.sync-from-schedule') }}" class="hidden">
                @csrf
                <input type="hidden" name="teacher_id" :value="selectedTeacherId">
            </form>

            <form method="POST" action="{{ route('classes.store') }}" class="p-5 space-y-3.5 text-xs overflow-y-auto flex-1">
                @csrf
                @if(auth()->user()->isAdmin())
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Pemilik Kelas (Guru Pengampu) *</label>
                        <select name="teacher_id"
                                x-model="selectedTeacherId"
                                @change="onTeacherChanged()"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                            <option value="{{ auth()->id() }}" @selected(old('teacher_id', auth()->id()) == auth()->id())>
                                {{ auth()->user()->name }} (Saya / Administrator)
                            </option>
                            @foreach($teachers->where('id', '!=', auth()->id()) as $t)
                                <option value="{{ $t->id }}" @selected(old('teacher_id') == $t->id)>
                                    {{ $t->name }} ({{ $t->role === 'admin' ? 'Admin' : 'Guru' }} - NIP. {{ $t->nip ?: '-' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="teacher_id" :value="selectedTeacherId">
                @endif

                {{-- Banner Master Jadwal Sekolah --}}
                <div x-show="isLoadingAllocations" class="p-3 rounded-xl bg-slate-100 text-slate-600 flex items-center gap-2 text-xs">
                    <svg class="animate-spin w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Memuat alokasi mengajar Master Jadwal Sekolah...</span>
                </div>

                <div x-show="!isLoadingAllocations && allocations.length > 0" class="p-3.5 rounded-xl bg-emerald-50/90 border border-emerald-200 text-emerald-950 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0">📅</span>
                            <div class="text-xs font-bold text-emerald-900">
                                Jadwal Resmi Wakasek: <span x-text="allocations.length + ' Rombel'"></span>
                                (<span x-text="allocations.reduce((acc, a) => acc + (a.total_jp || 0), 0) + ' JP'"></span>)
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                              :class="pendingAllocations.length > 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-200 text-emerald-900'"
                              x-text="pendingAllocations.length > 0 ? pendingAllocations.length + ' Belum Dibuat' : 'Semua Sudah Dibuat ✓'">
                        </span>
                    </div>

                    <div x-show="pendingAllocations.length > 0" class="pt-1.5 border-t border-emerald-200/70 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-emerald-800">
                            Ingin cepat? Buat seluruh <strong x-text="pendingAllocations.length"></strong> buku kelas dari Master Jadwal sekaligus:
                        </span>
                        <button type="submit"
                                form="syncFromScheduleModalForm"
                                class="px-3 py-1 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shrink-0 transition shadow-2xs">
                            ⚡ Buat Sekaligus
                        </button>
                    </div>
                </div>

                {{-- Selector Pintar: Pilih Alokasi Mengajar Resmi --}}
                <div x-show="!isLoadingAllocations && allocations.length > 0 && useScheduleMode" class="space-y-1">
                    <label class="block font-bold text-slate-700">
                        Pilih Kelas Mengajar dari Master Jadwal Sekolah *
                    </label>
                    <select x-model="selectedAllocKey"
                            @change="onAllocationSelected()"
                            class="w-full rounded-xl border border-emerald-400 bg-emerald-50/40 px-3 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                        <option value="" disabled>— Pilih Alokasi Mengajar Resmi Anda —</option>
                        <template x-for="alloc in allocations" :key="alloc.key">
                            <option :value="alloc.key"
                                    :disabled="alloc.already_created"
                                    :class="alloc.already_created ? 'text-slate-400 bg-slate-100' : 'text-slate-900 font-bold'">
                                <span x-text="(alloc.already_created ? '✓ [Sudah Dibuat] ' : '⭐ [Siap Dibuat] ') + alloc.class_name + ' • ' + alloc.subject_name + ' (' + alloc.total_jp + ' JP • ' + alloc.schedule_info + ')'"></span>
                            </option>
                        </template>
                    </select>
                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span>Rombel, Mapel, dan Jam terisi otomatis sesuai Master Jadwal Wakasek.</span>
                        <button type="button" @click="useScheduleMode = false" class="text-emerald-700 hover:underline font-semibold">
                            Mode Mandiri &rarr;
                        </button>
                    </div>
                </div>

                <div x-show="!isLoadingAllocations && allocations.length > 0 && !useScheduleMode" class="p-2.5 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-between text-[11px] text-slate-600">
                    <span>Mode pengisian mandiri aktif (di luar jadwal resmi).</span>
                    <button type="button" @click="useScheduleMode = true; onAllocationSelected()" class="text-emerald-700 hover:underline font-bold">
                        &larr; Kembali ke Master Jadwal
                    </button>
                </div>

                {{-- Pilihan Rombongan Belajar Terpusat --}}
                @if($schoolClasses->isNotEmpty())
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Pilih Rombongan Belajar (Rombel) @if(!auth()->user()->isAdmin()) * @endif
                            <span x-show="useScheduleMode && currentAllocation" class="text-[10px] text-emerald-600 font-extrabold">(Sesuai Jadwal)</span>
                        </label>
                        <select name="school_class_id"
                                x-model="selectedSchoolClassId"
                                :disabled="useScheduleMode && currentAllocation"
                                @if(!auth()->user()->isAdmin()) required @endif
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800 bg-white disabled:bg-slate-100 disabled:text-slate-600 disabled:cursor-not-allowed">
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
                        <template x-if="useScheduleMode && currentAllocation">
                            <input type="hidden" name="school_class_id" :value="selectedSchoolClassId">
                        </template>
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
                        <label class="block font-bold text-slate-700 mb-1">
                            Mata Pelajaran *
                            <span x-show="useScheduleMode && currentAllocation" class="text-[10px] text-emerald-600 font-extrabold">(Sesuai Jadwal)</span>
                        </label>
                        <select name="subject_id"
                                x-model="selectedSubjectId"
                                :disabled="useScheduleMode && currentAllocation"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold disabled:bg-slate-100 disabled:text-slate-600 disabled:cursor-not-allowed">
                            @foreach($subjects as $sub)
                                @php
                                    $hasCode = str_contains(strtoupper($sub->name), '(' . strtoupper($sub->code) . ')');
                                @endphp
                                <option value="{{ $sub->id }}">{{ $hasCode ? $sub->name : "{$sub->name} ({$sub->code})" }}</option>
                            @endforeach
                        </select>
                        <template x-if="useScheduleMode && currentAllocation">
                            <input type="hidden" name="subject_id" :value="selectedSubjectId">
                        </template>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Jadwal Mengajar
                            <span x-show="useScheduleMode && currentAllocation" class="text-[10px] text-emerald-600 font-extrabold">(Terkunci)</span>
                        </label>
                        <input type="text"
                               name="schedule_info"
                               x-model="scheduleInfoText"
                               :readonly="useScheduleMode && currentAllocation"
                               placeholder="Misal: Sen (1-2)"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs"
                               :class="useScheduleMode && currentAllocation ? 'bg-slate-100 text-slate-700 font-semibold cursor-not-allowed' : 'bg-white'">
                        <input type="hidden" name="teaching_days" :value="teachingDaysJson">
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
                    <button type="submit"
                            :disabled="useScheduleMode && currentAllocation && currentAllocation.already_created"
                            class="w-full sm:w-auto px-5 py-2 rounded-xl font-bold text-center shadow-2xs transition"
                            :class="(useScheduleMode && currentAllocation && currentAllocation.already_created) ? 'bg-slate-300 text-slate-500 cursor-not-allowed' : 'bg-emerald-600 text-white hover:bg-emerald-700'">
                        <span x-text="(useScheduleMode && currentAllocation) ? (currentAllocation.already_created ? 'Buku Kelas ' + currentAllocation.class_name + ' Sudah Dibuat' : '+ Buat Buku Kelas ' + currentAllocation.class_name) : 'Buat Kelas Sekarang'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Massal Buku Administrasi Kelas --}}
    <div x-show="bulkDeleteModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="bulkDeleteModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden">
            {{-- Modal Header --}}
            <div class="px-5 py-4 bg-rose-600 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold">Konfirmasi Hapus Massal Buku Kelas</h3>
                        <p class="text-[11px] text-rose-100">Tindakan ini permanen dan tidak dapat dibatalkan</p>
                    </div>
                </div>
                <button type="button" @click="bulkDeleteModal = false" class="text-rose-200 hover:text-white text-xl leading-none">&times;</button>
            </div>

            {{-- Modal Body --}}
            <div class="p-5 space-y-4 text-xs overflow-y-auto flex-1">
                {{-- Alert Box --}}
                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1.5 leading-relaxed">
                    <p class="font-bold flex items-center gap-1.5 text-amber-800">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Peringatan Penghapusan:</span>
                    </p>
                    <p>
                        Anda akan menghapus <strong x-text="selectedClasses.length"></strong> buku administrasi kelas beserta seluruh data internal di dalamnya:
                        <strong>Jurnal Harian, Rekap Absensi, Kolom & Bobot Nilai, serta Nilai Akhir</strong>.
                    </p>
                    <div class="text-[11px] text-emerald-800 font-semibold bg-emerald-50 p-2.5 rounded-lg border border-emerald-200 mt-2 flex items-start gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Data Master Peserta Didik & Rombongan Belajar di menu <em>Master Rombel & Murid</em> <strong>TIDAK AKAN TERHAPUS</strong> (tetap aman).</span>
                    </div>
                </div>

                {{-- Daftar Kelas yang Dipilih --}}
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">
                        Daftar Buku Kelas yang Akan Dihapus (<span x-text="selectedClasses.length"></span> buku):
                    </label>
                    <div class="max-h-36 overflow-y-auto rounded-xl border border-slate-200 p-2 bg-slate-50 space-y-1">
                        <template x-for="item in selectedClassesDetails" :key="item.id">
                            <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-emerald-600 text-white font-extrabold text-[10px]" x-text="item.name"></span>
                                    <span class="font-bold text-slate-800" x-text="item.subject"></span>
                                </div>
                                <span class="text-rose-600 text-[10px] font-semibold bg-rose-50 px-2 py-0.5 rounded">Akan dihapus</span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Input Validasi "HAPUS" --}}
                <div class="pt-2 border-t border-slate-100">
                    <label class="block font-bold text-slate-700 mb-1">
                        Ketik kata <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 font-mono font-extrabold tracking-wider">HAPUS</span> di bawah ini untuk konfirmasi:
                    </label>
                    <input type="text"
                           x-model="confirmText"
                           placeholder="Ketik HAPUS"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 font-mono text-center text-sm font-bold tracking-widest text-slate-900 uppercase focus:border-rose-500 focus:ring-rose-500">
                    <p class="text-[11px] text-slate-400 mt-1 text-center">
                        Tombol konfirmasi hapus akan aktif secara otomatis setelah Anda mengetik kata <strong>HAPUS</strong> dengan benar.
                    </p>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2 shrink-0">
                <button type="button"
                        @click="bulkDeleteModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 transition cursor-pointer">
                    Batal
                </button>
                <form method="POST" action="{{ route('classes.bulk-destroy') }}">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selectedClasses" :key="id">
                        <input type="hidden" name="class_ids[]" :value="id">
                    </template>
                    <input type="hidden" name="confirmation_text" :value="confirmText">
                    <button type="submit"
                            :disabled="confirmText.trim().toUpperCase() !== 'HAPUS'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
                            :class="confirmText.trim().toUpperCase() === 'HAPUS' ? 'bg-rose-600 hover:bg-rose-700 text-white cursor-pointer shadow-xs' : 'bg-slate-200 text-slate-400 cursor-not-allowed border border-slate-300'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>Hapus (<span x-text="selectedClasses.length"></span>) Buku Kelas Sekarang</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
