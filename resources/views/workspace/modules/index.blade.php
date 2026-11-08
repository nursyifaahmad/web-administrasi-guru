@extends('layouts.app')

@section('title', 'Modul Ajar (Perangkat KBM)')
@section('page_title', 'Modul Ajar')

@section('content')
<div class="space-y-5"
     x-data="{
        createModal: false,
        editModal: false,
        reviewModal: false,
        deleteModal: false,

        // Data modul yang sedang diedit
        editData: {
            id: null,
            title: '',
            subject_id: '',
            grade_level: 'VII',
            school_class_id: '',
            semester: '{{ $academicYear->semester }}',
            topic: '',
            target_meetings: '',
            description: '',
            file_name: '',
            status: 'draft',
            actionUrl: ''
        },

        // Data modul yang sedang direview admin
        reviewData: {
            id: null,
            title: '',
            teacher_name: '',
            file_name: '',
            view_url: '',
            status: '',
            feedback_notes: '',
            approveUrl: '',
            rejectUrl: '',
            resetUrl: '',
            reviewAction: 'approve' // 'approve' | 'reject'
        },

        // Data modul yang akan dihapus
        deleteData: {
            id: null,
            title: '',
            actionUrl: ''
        },

        openEdit(mod) {
            this.editData = {
                id: mod.id,
                title: mod.title || '',
                subject_id: mod.subject_id || '',
                grade_level: mod.grade_level || 'VII',
                school_class_id: mod.school_class_id ? String(mod.school_class_id) : '',
                semester: mod.semester || '{{ $academicYear->semester }}',
                topic: mod.topic || '',
                target_meetings: mod.target_meetings || '',
                description: mod.description || '',
                file_name: mod.file_name || '',
                status: mod.status || 'draft',
                actionUrl: '{{ url('/modul-ajar') }}/' + mod.id
            };
            this.editModal = true;
        },

        openReview(mod, defaultAction = 'approve') {
            this.reviewData = {
                id: mod.id,
                title: mod.title || '',
                teacher_name: mod.teacher ? mod.teacher.name : '-',
                file_name: mod.file_name || '',
                view_url: '{{ url('/modul-ajar') }}/' + mod.id + '/view',
                status: mod.status || '',
                feedback_notes: mod.feedback_notes || '',
                approveUrl: '{{ url('/admin/modul-ajar') }}/' + mod.id + '/approve',
                rejectUrl: '{{ url('/admin/modul-ajar') }}/' + mod.id + '/reject',
                resetUrl: '{{ url('/admin/modul-ajar') }}/' + mod.id + '/reset',
                reviewAction: defaultAction
            };
            this.reviewModal = true;
        },

        openDelete(mod) {
            this.deleteData = {
                id: mod.id,
                title: mod.title || '',
                actionUrl: '{{ url('/modul-ajar') }}/' + mod.id
            };
            this.deleteModal = true;
        }
     }">

    {{-- Tab Switcher Khusus Admin / Wakasek --}}
    @if(auth()->user()->isAdmin())
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/80 gap-1">
                <a href="{{ route('teaching-modules.index', ['view' => 'supervision']) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $effectiveView === 'supervision' ? 'bg-white text-indigo-700 shadow-2xs border border-slate-200/70' : 'text-slate-600 hover:text-slate-900' }}">
                    <svg class="w-4 h-4 {{ $effectiveView === 'supervision' ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Supervisi &amp; Verifikasi Seluruh Guru</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $effectiveView === 'supervision' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700' }}">
                        {{ $adminStats['total'] }}
                    </span>
                    @if($adminStats['submitted'] > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white animate-pulse" title="Menunggu telaah">
                            {{ $adminStats['submitted'] }} Baru
                        </span>
                    @endif
                </a>
                <a href="{{ route('teaching-modules.index', ['view' => 'my']) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $effectiveView === 'my' ? 'bg-white text-emerald-700 shadow-2xs border border-slate-200/70' : 'text-slate-600 hover:text-slate-900' }}">
                    <svg class="w-4 h-4 {{ $effectiveView === 'my' ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Modul Ajar Saya</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $effectiveView === 'my' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                        {{ $myStats['total'] }}
                    </span>
                </a>
            </div>

            <div class="text-[11px] text-slate-500 pr-1">
                @if($effectiveView === 'supervision')
                    Mode Supervisi &amp; Pengesahan: Telaah berkas PDF modul ajar guru untuk disetujui atau diberi revisi.
                @else
                    Menampilkan modul ajar yang Anda ampu sendiri (<strong>{{ auth()->user()->name }}</strong>).
                @endif
            </div>
        </div>

        @if($effectiveView === 'supervision')
            <div class="p-3.5 rounded-2xl bg-indigo-50/90 border border-indigo-200 text-indigo-950 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-base shrink-0 shadow-sm">
                        📑
                    </div>
                    <div>
                        <div class="font-bold text-indigo-950 text-sm">Pusat Verifikasi &amp; Pengesahan Modul Ajar</div>
                        <div class="text-[11px] text-indigo-800">
                            Sebagai Administrator / Wakasek Kurikulum, Anda dapat meninjau dokumen PDF KBM seluruh guru, mengesahkan (Approve), atau memberikan catatan perbaikan (Minta Revisi).
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="createModal = true"
                            class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Unggah Modul Baru</span>
                    </button>
                </div>
            </div>
        @endif
    @endif

    {{-- Ringkasan Statistik --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $currentStats = ($effectiveView === 'supervision' && auth()->user()->isAdmin()) ? $adminStats : $myStats;
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">Total Modul Ajar</span>
                <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs">
                    📁
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900">{{ $currentStats['total'] }}</span>
                <span class="text-[11px] text-slate-400">dokumen</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-amber-200/80 bg-gradient-to-br from-white to-amber-50/30 p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-700">Menunggu Telaah</span>
                <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">
                    ⏳
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-amber-700">{{ $currentStats['submitted'] }}</span>
                <span class="text-[11px] text-amber-600 font-medium">antrean telaah</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-white to-emerald-50/30 p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-700">Disetujui / Sah</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                    ✅
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-emerald-700">{{ $currentStats['approved'] }}</span>
                <span class="text-[11px] text-emerald-600 font-medium">terverifikasi</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200/80 bg-gradient-to-br from-white to-rose-50/30 p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-rose-700">Perlu Revisi</span>
                <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs">
                    ✍️
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-rose-700">{{ $currentStats['revision'] }}</span>
                <span class="text-[11px] text-rose-600 font-medium">ada catatan</span>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar & Tombol Aksi Tambah --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('teaching-modules.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            @if(auth()->user()->isAdmin())
                <input type="hidden" name="view" value="{{ $effectiveView }}">
            @endif

            {{-- Pencarian --}}
            <div class="relative flex-1 min-w-[200px] sm:min-w-[240px]">
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Cari judul modul, materi, topik, nama file..."
                       class="w-full rounded-xl border border-slate-300 pl-9 pr-3.5 py-2 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Filter Tingkat --}}
            <select name="grade_level" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-emerald-500">
                <option value="">Semua Tingkat</option>
                @foreach($gradeLevels as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['grade_level'] === $lvl)>Tingkat {{ $lvl }}</option>
                @endforeach
            </select>

            {{-- Filter Mata Pelajaran --}}
            <select name="subject_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500">
                <option value="">Semua Mata Pelajaran</option>
                @foreach($subjects as $sub)
                    <option value="{{ $sub->id }}" @selected((string)$filters['subject_id'] === (string)$sub->id)>
                        {{ $sub->name }}
                    </option>
                @endforeach
            </select>

            {{-- Filter Status Verifikasi --}}
            <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-emerald-500">
                <option value="">Semua Status</option>
                <option value="draft" @selected($filters['status'] === 'draft')>Draf Mandiri</option>
                <option value="submitted" @selected($filters['status'] === 'submitted')>Menunggu Telaah</option>
                <option value="approved" @selected($filters['status'] === 'approved')>Disetujui / Sah</option>
                <option value="revision" @selected($filters['status'] === 'revision')>Perlu Revisi</option>
            </select>

            {{-- Filter Guru (Khusus Mode Supervisi Admin) --}}
            @if(auth()->user()->isAdmin() && $effectiveView === 'supervision')
                <select name="teacher_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500">
                    <option value="">Semua Guru Pengampu</option>
                    @foreach($teachers as $tch)
                        <option value="{{ $tch->id }}" @selected((string)$filters['teacher_id'] === (string)$tch->id)>
                            {{ $tch->name }}
                        </option>
                    @endforeach
                </select>
            @endif

            @if(!empty($filters['q']) || !empty($filters['grade_level']) || !empty($filters['subject_id']) || !empty($filters['status']) || !empty($filters['teacher_id']))
                <a href="{{ route('teaching-modules.index', ['view' => $effectiveView]) }}"
                   class="px-2.5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition inline-flex items-center gap-1"
                   title="Reset Filter">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>Reset</span>
                </a>
            @endif
        </form>

        {{-- Tombol Unggah Modul Ajar --}}
        <div>
            <button type="button"
                    @click="createModal = true"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Unggah Modul Ajar</span>
            </button>
        </div>
    </div>

    {{-- Grid Daftar Modul Ajar --}}
    @if($modules->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-2xs">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-3xl mb-3">
                📄
            </div>
            <h3 class="text-base font-bold text-slate-800">Belum Ada Modul Ajar</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                @if(!empty($filters['q']) || !empty($filters['grade_level']) || !empty($filters['status']))
                    Tidak ada modul ajar yang sesuai dengan filter pencarian Anda. Silakan ubah filter atau kata kunci.
                @elseif($effectiveView === 'supervision')
                    Belum ada guru yang mengunggah modul ajar untuk tahun ajaran aktif {{ $academicYear->year }} ({{ $academicYear->semester }}).
                @else
                    Anda belum mengunggah berkas modul ajar PDF untuk semester aktif ini. Mulai unggah modul pertama Anda.
                @endif
            </p>
            <div class="mt-5">
                <button type="button"
                        @click="createModal = true"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Unggah Modul Ajar Sekarang</span>
                </button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($modules as $module)
                <div class="bg-white rounded-2xl border border-slate-200 hover:border-slate-300 shadow-2xs hover:shadow-xs transition flex flex-col justify-between overflow-hidden">
                    <div class="p-4 space-y-3">
                        {{-- Baris Status & Tingkat --}}
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-extrabold border {{ $module->badge_class }}">
                                @if($module->status === 'approved')
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @elseif($module->status === 'submitted')
                                    <svg class="w-3 h-3 text-amber-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($module->status === 'revision')
                                    <svg class="w-3 h-3 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @else
                                    <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                @endif
                                <span>{{ $module->status_label }}</span>
                            </span>

                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">Tingkat {{ $module->grade_level }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">{{ $module->semester }}</span>
                            </div>
                        </div>

                        {{-- Mapel & Judul Modul --}}
                        <div>
                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 mb-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>{{ $module->subject->name ?? 'Mata Pelajaran' }}</span>
                                @if($module->schoolClass)
                                    <span class="text-slate-400 font-normal">• Kelas {{ $module->schoolClass->name }}</span>
                                @else
                                    <span class="text-slate-400 font-normal">• Seluruh Kelas {{ $module->grade_level }}</span>
                                @endif
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 leading-snug line-clamp-2" title="{{ $module->title }}">
                                {{ $module->title }}
                            </h4>
                            @if($module->topic)
                                <p class="text-xs text-slate-600 font-medium mt-1 line-clamp-1">
                                    <span class="text-slate-400">Lingkup Materi:</span> {{ $module->topic }}
                                </p>
                            @endif
                        </div>

                        {{-- Rincian File PDF & Alokasi --}}
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    PDF
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-800 truncate text-[11px]" title="{{ $module->file_name }}">
                                        {{ $module->file_name }}
                                    </div>
                                    <div class="text-[10px] text-slate-500">
                                        {{ $module->file_size_for_humans }} • {{ $module->target_meetings ?: 'Alokasi Pertemuan Fleksibel' }}
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('teaching-modules.view-pdf', $module) }}"
                               target="_blank"
                               class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-emerald-700 font-bold text-[11px] shrink-0 transition shadow-2xs inline-flex items-center gap-1"
                               title="Buka Berkas PDF di Tab Baru">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Lihat</span>
                            </a>
                        </div>

                        {{-- Identitas Guru Pengampu --}}
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                    {{ substr($module->teacher->name ?? 'G', 0, 1) }}
                                </div>
                                <span class="font-medium text-slate-700 truncate text-[11px]">
                                    {{ $module->teacher->name ?? 'Guru Pengampu' }}
                                </span>
                            </div>
                            <span class="text-[10px] text-slate-400 shrink-0">
                                {{ $module->updated_at->diffForHumans() }}
                            </span>
                        </div>

                        {{-- Banner Feedback Revisi jika ada --}}
                        @if($module->status === 'revision' && $module->feedback_notes)
                            <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1">
                                <div class="flex items-center justify-between font-bold text-[11px] text-rose-800">
                                    <span>⚠️ Catatan Revisi Wakasek:</span>
                                    @if($module->approver)
                                        <span class="font-normal text-[10px] text-rose-700">oleh {{ $module->approver->name }}</span>
                                    @endif
                                </div>
                                <p class="text-[11px] leading-relaxed text-rose-950 font-medium italic">
                                    "{{ $module->feedback_notes }}"
                                </p>
                            </div>
                        @endif

                        {{-- Banner Pengesahan jika sudah disetujui --}}
                        @if($module->status === 'approved')
                            <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs space-y-0.5">
                                <div class="flex items-center gap-1.5 font-bold text-[11px] text-emerald-900">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Telah Disetujui &amp; Disahkan</span>
                                </div>
                                <div class="text-[10px] text-emerald-800">
                                    Oleh: <strong>{{ $module->approver->name ?? 'Wakasek Kurikulum' }}</strong>
                                    @if($module->approved_at)
                                        • {{ $module->approved_at->format('d M Y H:i') }}
                                    @endif
                                </div>
                                @if($module->feedback_notes)
                                    <div class="text-[10px] text-emerald-700 italic pt-0.5">
                                        "{{ $module->feedback_notes }}"
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Footer Aksi --}}
                    <div class="p-3 bg-slate-50/90 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            {{-- Unduh Dokumen PDF --}}
                            <a href="{{ route('teaching-modules.download', $module) }}"
                               class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-emerald-700 font-bold text-xs transition shadow-2xs inline-flex items-center gap-1"
                               title="Unduh Berkas PDF Resmi">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>Unduh</span>
                            </a>

                            {{-- Hak Akses Guru Pemilik (Edit & Hapus) --}}
                            @if($module->canBeEditedBy(auth()->user()))
                                <button type="button"
                                        @click="openEdit({{ json_encode($module) }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-indigo-700 font-bold text-xs transition shadow-2xs inline-flex items-center gap-1"
                                        title="Edit Informasi Modul & Ganti PDF">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Edit</span>
                                </button>
                            @endif

                            @if($module->canBeDeletedBy(auth()->user()))
                                <button type="button"
                                        @click="openDelete({{ json_encode($module) }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-rose-300 text-slate-700 hover:text-rose-700 font-bold text-xs transition shadow-2xs inline-flex items-center gap-1"
                                        title="Hapus Modul Ajar">
                                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </div>

                        {{-- Aksi Workflow Persetujuan --}}
                        <div class="flex items-center gap-1.5">
                            {{-- Jika Guru pemilik memiliki draf atau modul ditolak revisi: Tombol Ajukan Telaah --}}
                            @if($module->canBeEditedBy(auth()->user()) && ($module->status === 'draft' || $module->status === 'revision'))
                                <form method="POST" action="{{ route('teaching-modules.submit', $module) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            onclick="return confirm('Ajukan Modul Ajar ini ke antrean telaah Wakasek Kurikulum?')"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                        <span>Ajukan Telaah</span>
                                    </button>
                                </form>
                            @endif

                            {{-- Tombol Tindakan Admin / Wakasek --}}
                            @if(auth()->user()->isAdmin())
                                @if($module->status === 'submitted')
                                    <button type="button"
                                            @click="openReview({{ json_encode($module) }}, 'approve')"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Verifikasi</span>
                                    </button>
                                @elseif($module->status === 'approved')
                                    <button type="button"
                                            @click="openReview({{ json_encode($module) }}, 'reject')"
                                            class="px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 hover:bg-amber-100 font-bold text-xs transition inline-flex items-center gap-1"
                                            title="Ubah Keputusan / Minta Revisi Tambahan">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Revisi</span>
                                    </button>
                                @elseif($module->status === 'revision')
                                    <button type="button"
                                            @click="openReview({{ json_encode($module) }}, 'approve')"
                                            class="px-2.5 py-1.5 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-800 hover:bg-emerald-100 font-bold text-xs transition inline-flex items-center gap-1"
                                            title="Setujui Modul Ajar">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Sahkan</span>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Navigasi Paginasi --}}
        <div class="mt-4">
            {{ $modules->links() }}
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- MODAL 1: UNGGAH MODUL AJAR (CREATE)                      --}}
    {{-- ======================================================== --}}
    <div x-show="createModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-5 shadow-2xl border border-slate-200 my-8 max-h-[90vh] overflow-y-auto"
             @click.away="createModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-bold">
                        📄
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Unggah Modul Ajar Baru</h3>
                        <p class="text-xs text-slate-500">Perangkat pembelajaran format PDF untuk tahun ajaran {{ $academicYear->year }} ({{ $academicYear->semester }})</p>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('teaching-modules.store') }}" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf

                {{-- Judul Modul Ajar --}}
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Judul Modul Ajar *
                    </label>
                    <input type="text"
                           name="title"
                           required
                           placeholder="Contoh: Modul Ajar Bahasa Inggris - Descriptive Text Fase D"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                </div>

                {{-- Baris Mapel & Tingkat Kelas --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Mata Pelajaran *
                        </label>
                        <select name="subject_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                            <option value="" disabled selected>— Pilih Mata Pelajaran —</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Tingkat Kelas *
                        </label>
                        <select name="grade_level" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                            @foreach($gradeLevels as $lvl)
                                <option value="{{ $lvl }}">Kelas {{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Baris Rombel Spesifik & Semester --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Target Rombel Belajar
                        </label>
                        <select name="school_class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Berlaku Seluruh Rombel di Tingkat Terpilih —</option>
                            @foreach($schoolClasses as $sc)
                                <option value="{{ $sc->id }}">Kelas {{ $sc->name }} (Tingkat {{ $sc->grade_level }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Kosongkan jika modul dapat digunakan untuk semua rombel di tingkat tersebut.</p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Semester *
                        </label>
                        <select name="semester" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                            <option value="Ganjil" @selected($academicYear->semester === 'Ganjil')>Semester Ganjil</option>
                            <option value="Genap" @selected($academicYear->semester === 'Genap')>Semester Genap</option>
                        </select>
                    </div>
                </div>

                {{-- Lingkup Materi / Topik & Target Pertemuan --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Topik / Lingkup Materi
                        </label>
                        <input type="text"
                               name="topic"
                               placeholder="Contoh: Telling Time, Daily Routines"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Target Alokasi Pertemuan / Jam
                        </label>
                        <input type="text"
                               name="target_meetings"
                               placeholder="Contoh: 3 Pertemuan (6 JP)"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                {{-- Unggah Berkas Dokumen PDF --}}
                <div class="p-4 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-300 hover:border-emerald-500 transition space-y-2">
                    <label class="block font-bold text-slate-800">
                        Unggah Berkas PDF Modul Ajar * (Maks. 20 MB)
                    </label>
                    <input type="file"
                           name="file"
                           required
                           accept="application/pdf"
                           class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer">
                    <p class="text-[11px] text-slate-500">
                        Format berkas harus berupa dokumen <strong>PDF (.pdf)</strong>. Pastikan isi mencakup tujuan pembelajaran, langkah KBM, asesmen, dan lampiran relevan.
                    </p>
                </div>

                {{-- Deskripsi / Catatan --}}
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Deskripsi / Catatan Tambahan (Opsional)
                    </label>
                    <textarea name="description"
                              rows="2"
                              placeholder="Tuliskan catatan pendukung mengenai penerapan modul ajar ini..."
                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                {{-- Pilihan Status Awal --}}
                <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800 text-xs">Ajukan Langsung ke Wakasek?</div>
                        <div class="text-[11px] text-slate-500">Jika dipilih, dokumen langsung masuk ke antrean telaah verifikasi Wakasek Kurikulum.</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="status" value="submitted" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                {{-- Tombol Simpan --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button"
                            @click="createModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition shadow-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan &amp; Unggah Modul</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL 2: EDIT MODUL AJAR (UPDATE)                        --}}
    {{-- ======================================================== --}}
    <div x-show="editModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-5 shadow-2xl border border-slate-200 my-8 max-h-[90vh] overflow-y-auto"
             @click.away="editModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl font-bold">
                        ✏️
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Perbarui Modul Ajar</h3>
                        <p class="text-xs text-slate-500">Edit informasi modul atau perbarui berkas PDF dokumen</p>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" :action="editData.actionUrl" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                {{-- Judul Modul Ajar --}}
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Judul Modul Ajar *
                    </label>
                    <input type="text"
                           name="title"
                           x-model="editData.title"
                           required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>

                {{-- Baris Mapel & Tingkat Kelas --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Mata Pelajaran *
                        </label>
                        <select name="subject_id" x-model="editData.subject_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Tingkat Kelas *
                        </label>
                        <select name="grade_level" x-model="editData.grade_level" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            @foreach($gradeLevels as $lvl)
                                <option value="{{ $lvl }}">Kelas {{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Baris Rombel Spesifik & Semester --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Target Rombel Belajar
                        </label>
                        <select name="school_class_id" x-model="editData.school_class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            <option value="">— Berlaku Seluruh Rombel di Tingkat Terpilih —</option>
                            @foreach($schoolClasses as $sc)
                                <option value="{{ $sc->id }}">Kelas {{ $sc->name }} (Tingkat {{ $sc->grade_level }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Semester *
                        </label>
                        <select name="semester" x-model="editData.semester" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            <option value="Ganjil">Semester Ganjil</option>
                            <option value="Genap">Semester Genap</option>
                        </select>
                    </div>
                </div>

                {{-- Lingkup Materi / Topik & Target Pertemuan --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Topik / Lingkup Materi
                        </label>
                        <input type="text"
                               name="topic"
                               x-model="editData.topic"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Target Alokasi Pertemuan / Jam
                        </label>
                        <input type="text"
                               name="target_meetings"
                               x-model="editData.target_meetings"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Ganti Berkas Dokumen PDF --}}
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-300 space-y-2">
                    <div class="flex items-center justify-between font-bold text-slate-800">
                        <span>Ganti Berkas PDF Dokumen (Opsional)</span>
                        <span class="text-[11px] text-slate-500 font-normal">Berkas saat ini: <strong class="text-slate-700" x-text="editData.file_name"></strong></span>
                    </div>
                    <input type="file"
                           name="file"
                           accept="application/pdf"
                           class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-100 file:text-indigo-800 hover:file:bg-indigo-200 cursor-pointer">
                    <p class="text-[10px] text-slate-400">
                        Biarkan kosong jika tidak ingin mengubah berkas PDF yang sudah diunggah. Maksimal 20 MB.
                    </p>
                </div>

                {{-- Deskripsi / Catatan --}}
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Deskripsi / Catatan Tambahan (Opsional)
                    </label>
                    <textarea name="description"
                              x-model="editData.description"
                              rows="2"
                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                {{-- Pilihan Status Kirim --}}
                <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800 text-xs">Kirimkan Ulang untuk Ditelaah Wakasek?</div>
                        <div class="text-[11px] text-slate-500">Jika modul sebelumnya berstatus "Perlu Revisi", dokumen akan otomatis dikirimkan ke antrean telaah baru.</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="status" value="submitted" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                {{-- Tombol Simpan --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button"
                            @click="editModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL 3: TELAAH & VERIFIKASI WAKASEK (ADMIN ONLY)        --}}
    {{-- ======================================================== --}}
    @if(auth()->user()->isAdmin())
        <div x-show="reviewModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="bg-white rounded-3xl max-w-xl w-full p-6 space-y-5 shadow-2xl border border-slate-200 my-8 max-h-[90vh] overflow-y-auto"
                 @click.away="reviewModal = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl font-bold">
                            ⚖️
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Telaah &amp; Verifikasi Modul Ajar</h3>
                            <p class="text-xs text-slate-500">Panel pengesahan resmi Wakasek Kurikulum / Administrator</p>
                        </div>
                    </div>
                    <button type="button" @click="reviewModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Rangkuman Info Dokumen --}}
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
                    <div>
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Judul Modul:</span>
                        <div class="text-slate-900 font-bold text-sm" x-text="reviewData.title"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-200/80">
                        <div>
                            <span class="text-slate-400">Guru Pengampu:</span>
                            <span class="font-bold text-slate-800" x-text="reviewData.teacher_name"></span>
                        </div>
                        <a :href="reviewData.view_url"
                           target="_blank"
                           class="px-2.5 py-1 rounded-lg bg-white border border-slate-300 text-indigo-700 hover:bg-indigo-50 font-bold transition inline-flex items-center gap-1 shadow-2xs">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span>Buka PDF Dokumen</span>
                        </a>
                    </div>
                </div>

                {{-- Pemilihan Keputusan (Setujui vs Minta Revisi) --}}
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700 text-xs">Keputusan Verifikasi *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button"
                                @click="reviewData.reviewAction = 'approve'"
                                :class="reviewData.reviewAction === 'approve' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 ring-2 ring-emerald-500/20' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                class="p-3 rounded-2xl border text-left transition flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0">
                                ✓
                            </span>
                            <div>
                                <div class="font-bold text-xs">Setujui &amp; Sahkan</div>
                                <div class="text-[10px] text-slate-500">Dokumen valid &amp; resmi</div>
                            </div>
                        </button>

                        <button type="button"
                                @click="reviewData.reviewAction = 'reject'"
                                :class="reviewData.reviewAction === 'reject' ? 'bg-rose-50 border-rose-500 text-rose-900 ring-2 ring-rose-500/20' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                class="p-3 rounded-2xl border text-left transition flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0">
                                ✍️
                            </span>
                            <div>
                                <div class="font-bold text-xs">Minta Perbaikan / Revisi</div>
                                <div class="text-[10px] text-slate-500">Kembalikan ke guru pengampu</div>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Form Aksi Approve --}}
                <form x-show="reviewData.reviewAction === 'approve'"
                      method="POST"
                      :action="reviewData.approveUrl"
                      class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Catatan Apresiasi / Pengesahan (Opsional)
                        </label>
                        <textarea name="feedback_notes"
                                  rows="3"
                                  placeholder="Contoh: Modul ajar tersusun sangat baik, rubrik asesmen komprehensif. Disahkan untuk KBM semester ini."
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="reviewModal = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition shadow-sm inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>Setujui &amp; Sahkan Modul Ajar</span>
                        </button>
                    </div>
                </form>

                {{-- Form Aksi Reject / Minta Revisi --}}
                <form x-show="reviewData.reviewAction === 'reject'"
                      method="POST"
                      :action="reviewData.rejectUrl"
                      class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')

                    <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs">
                        <div class="font-bold mb-1">Penting: Berikan arahan revisi yang jelas</div>
                        <div class="text-[11px] leading-relaxed">
                            Catatan yang Anda tulis akan langsung tampil pada kartu modul di portal guru pengampu agar mereka dapat memperbaiki dokumen dan mengunggah versi revisi.
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-rose-900 mb-1">
                            Catatan Masukan / Bagian yang Perlu Direvisi *
                        </label>
                        <textarea name="feedback_notes"
                                  required
                                  rows="3"
                                  placeholder="Contoh: Mohon lengkapi rubrik asesmen formatif pertemuan kedua dan sesuaikan target alokasi waktu JP sesuai kurikulum sekolah."
                                  class="w-full rounded-xl border border-rose-300 px-3.5 py-2 text-xs text-slate-800 focus:ring-2 focus:ring-rose-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="reviewModal = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition shadow-sm inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Kirim Permintaan Revisi</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- MODAL 4: KONFIRMASI HAPUS MODUL AJAR                     --}}
    {{-- ======================================================== --}}
    <div x-show="deleteModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200 text-center"
             @click.away="deleteModal = false">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-2xl font-bold">
                🗑️
            </div>
            <h3 class="text-base font-bold text-slate-900">Hapus Modul Ajar Ini?</h3>
            <p class="text-xs text-slate-600">
                Anda akan menghapus modul ajar <strong class="text-slate-900" x-text="deleteData.title"></strong> beserta berkas fisik PDF-nya dari sistem. Tindakan ini tidak dapat dibatalkan.
            </p>

            <form method="POST" :action="deleteData.actionUrl" class="flex items-center justify-center gap-2 pt-2">
                @csrf
                @method('DELETE')
                <button type="button"
                        @click="deleteModal = false"
                        class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 transition text-xs">
                    Batal
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition shadow-sm text-xs inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Ya, Hapus Sekarang</span>
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
