@if(auth()->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) auth()->id())
    <div class="mb-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs text-amber-900">
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded-md bg-amber-200/80 font-bold uppercase tracking-wide text-[10px]">Mode Kontrol Admin</span>
            <span>Anda sedang memantau & mengelola Buku Administrasi milik <strong>{{ $teachingClass->teacher->name ?? '-' }}</strong> (NIP. {{ $teachingClass->teacher->nip ?: '-' }}).</span>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="font-semibold underline hover:text-amber-700">&larr; Kembali ke Monitoring Admin</a>
    </div>
@endif

<div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 mb-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-3 border-b border-slate-100">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-extrabold tracking-wide">
                    KELAS {{ $teachingClass->class_name }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                    Tingkat {{ $teachingClass->grade_level }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold"
                      title="Standar KKM/KKTP dikontrol oleh Admin sesuai Mata Pelajaran & Tingkat Kelas">
                    KKM / KKTP: {{ $teachingClass->effective_kktp }}
                </span>
                <span class="text-sm font-bold text-slate-900">
                    {{ $teachingClass->subject->name ?? '-' }}
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                <span><strong>Guru Mapel:</strong> {{ $teachingClass->teacher->name ?? '-' }} (NIP. {{ $teachingClass->teacher->nip ?: '-' }})</span>
                <span>&bull;</span>
                <span><strong>Wali Kelas:</strong> {{ $teachingClass->homeroom_teacher_name }} (NIP. {{ $teachingClass->homeroom_teacher_nip ?: '-' }})</span>
                @if($teachingClass->schedule_info)
                    <span>&bull;</span>
                    <span><strong>Jadwal:</strong> {{ $teachingClass->schedule_info }}</span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2"
             x-data="{
                bundleModal: false,
                async openBundleModal() {
                    if (window.__workspaceDirty && typeof window.__workspaceSaveFn === 'function') {
                        const label = window.__workspaceDirtyLabel || 'data';
                        const saveNow = window.confirm(
                            'Ada perubahan ' + label + ' di halaman ini yang belum disimpan.\n\n' +
                            'Klik \"OK\" untuk MENYIMPAN perubahan sekarang lalu membuka Cetak Bundel Lengkap, atau klik \"Batal\" untuk kembali ke tabel.'
                        );
                        if (!saveNow) return;
                        const ok = await window.__workspaceSaveFn();
                        if (!ok) return;
                    }
                    this.bundleModal = true;
                }
             }">
            <button type="button" @click="openBundleModal()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition shadow-2xs">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Cetak Bundel Lengkap</span>
            </button>

            @include('partials.export-modal', [
                'modalVar' => 'bundleModal',
                'modalTitle' => 'Cetak Bundel Lengkap 1 Kelas (Jurnal + Absensi + Nilai)',
                'pdfRoute' => route('exports.bundle.pdf', $teachingClass),
                'excelRoute' => route('exports.bundle.excel', $teachingClass),
                'defaultOrientation' => 'landscape',
                'extraParams' => ['month' => request('month', now()->month), 'year' => request('year', now()->year)],
                'showMonthSelector' => true,
            ])
        </div>
    </div>

    {{-- Navigasi 4 Menu Kelas Administrasi --}}
    <div class="pt-3 flex flex-wrap items-center gap-1.5">
        <a href="{{ route('journals.index', $teachingClass) }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('journals.*') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            <span>1. Jurnal Harian</span>
        </a>
        <a href="{{ route('attendances.index', $teachingClass) }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('attendances.*') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span>2. Absensi (. / s / i / a / d)</span>
        </a>
        <a href="{{ route('grades.index', $teachingClass) }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('grades.*') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <span>3. Daftar Nilai & Rumus NA</span>
        </a>
        <a href="{{ route('classes.students', $teachingClass) }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('classes.students*') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>Peserta Didik & Wali Kelas</span>
        </a>
    </div>
</div>
