@extends('layouts.app')

@section('title', 'Master Rombel & Peserta Didik')
@section('page_title', 'Master Rombongan Belajar (Rombel) & Peserta Didik')
@section('page_subtitle', 'Data terpusat kelas sekolah & murid — seluruh guru pada rombel yang sama otomatis menggunakan data murid yang identik.')

@section('content')
<div class="space-y-5" x-data="{ createModal: false }">
    {{-- Toolbar Filter & Tambah Rombel --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.classes.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama rombel atau wali kelas..."
                   class="w-full sm:w-64 rounded-xl border border-slate-300 px-3.5 py-2 text-xs">
            <select name="grade_level" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold">
                <option value="">Semua Tingkat</option>
                @foreach($gradeLevels as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['grade_level'] === $lvl)>Tingkat {{ $lvl }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800">Filter</button>
        </form>

        <button type="button" @click="createModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Tambah Rombel Baru</span>
        </button>
    </div>

    {{-- Banner Informasi Hak Akses & Sinkronisasi --}}
    <div class="rounded-2xl bg-indigo-50 border border-indigo-200 p-4 text-xs text-indigo-950 flex items-start gap-3 shadow-2xs">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 mt-0.5 font-bold">i</div>
        <div class="space-y-1">
            <div class="font-extrabold text-sm text-indigo-900">Pusat Data Murid & Rombel Sekolah</div>
            <p class="text-indigo-800 leading-relaxed">
                Semua guru mata pelajaran yang mengajar di rombel yang sama (misal: Guru Matematika VII-A dan Guru Bahasa Indonesia VII-A) akan otomatis membaca daftar peserta didik yang sama dari halaman ini. 
                Akun guru berstatus <strong>read-only</strong> (tidak dapat mengimpor atau menghapus murid) demi menjaga keaslian data sekolah.
            </p>
        </div>
    </div>

    {{-- Daftar Kartu Rombel --}}
    @if($classes->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold text-xl mb-3">+</div>
            <h3 class="text-base font-bold text-slate-900">Belum Ada Rombel pada {{ $selectedAcademicYear->full_label }}</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Tambahkan rombongan belajar sekolah (misal: VII-A, VII-B, dst.) lalu input atau impor daftar murid dari Excel.
            </p>
            <button type="button" @click="createModal = true"
                    class="mt-4 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700">
                + Tambah Rombel Sekarang
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($classes as $c)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs hover:border-emerald-400 hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-lg bg-emerald-600 text-white text-xs font-extrabold">{{ $c->name }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">Tingkat {{ $c->grade_level }}</span>
                                </div>
                                <h3 class="mt-2 text-sm font-bold text-slate-900">Wali Kelas: {{ $c->homeroom_teacher_name }}</h3>
                                <div class="text-xs text-slate-500">NIP: {{ $c->homeroom_teacher_nip ?: '-' }}</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-extrabold shrink-0">
                                {{ $c->active_students_count }} Murid
                            </span>
                        </div>

                        {{-- Status TTD Digital Wali Kelas --}}
                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500">TTD Digital Wali Kelas:</span>
                            @if(!empty($c->homeroom_teacher_signature))
                                <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-bold">Tersedia</span>
                            @else
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 font-semibold">Belum Ada</span>
                            @endif
                        </div>

                        {{-- Daftar Guru yang Mengajar di Rombel Ini --}}
                        <div class="mt-3 pt-3 border-t border-slate-100 space-y-1.5">
                            <div class="text-[11px] font-bold text-slate-700 flex items-center justify-between">
                                <span>Guru Pengampu di Rombel Ini:</span>
                                <span class="text-slate-400 font-normal">({{ $c->teaching_classes_count }} Mapel)</span>
                            </div>
                            @if($c->teachingClasses->isEmpty())
                                <div class="text-[11px] text-slate-400 italic">Belum ada guru yang mengambil kelas ajar di rombel ini.</div>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($c->teachingClasses as $tc)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-50 border border-slate-200 text-[10px] text-slate-700"
                                              title="{{ $tc->teacher->name ?? '-' }} ({{ $tc->subject->name ?? '-' }})">
                                            <strong>{{ $tc->subject->code ?? $tc->subject->name }}</strong>: {{ strtok($tc->teacher->name ?? '-', ' ') }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer Aksi --}}
                    <div class="px-4 py-3 bg-slate-50 border-t border-slate-200/80 flex items-center justify-between gap-2 text-xs">
                        <form method="POST" action="{{ route('admin.classes.destroy', $c) }}"
                              onsubmit="return confirm('Hapus Rombel {{ $c->name }} beserta seluruh data muridnya?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2.5 py-1.5 rounded-lg text-rose-600 hover:bg-rose-50 font-bold transition">
                                Hapus
                            </button>
                        </form>
                        <a href="{{ route('admin.classes.show', $c) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition">
                            <span>Kelola Murid ({{ $c->active_students_count }}) &rarr;</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal Tambah Rombel Baru --}}
    <div x-show="createModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="createModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold">Tambah Rombongan Belajar (Rombel) Baru</h3>
                    <p class="text-[11px] text-slate-300">Tahun Ajaran: {{ $selectedAcademicYear->full_label }}</p>
                </div>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.classes.store') }}" enctype="multipart/form-data" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tingkat Kelas *</label>
                        <select name="grade_level" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                            @foreach($gradeLevels as $lvl)
                                <option value="{{ $lvl }}">Kelas {{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Rombel / Kelas *</label>
                        <input type="text" name="name" required placeholder="Contoh: VII-A atau 8E"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="font-bold text-slate-800">Identitas Wali Kelas (Otomatis tercetak di PDF & Excel semua guru)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar Wali Kelas *</label>
                            <input type="text" name="homeroom_teacher_name" required placeholder="Contoh: Dra. Ratna Sari Dewi"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">NIP Wali Kelas</label>
                            <input type="text" name="homeroom_teacher_nip" placeholder="19750618 200501 2 007"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Upload File Tanda Tangan Wali Kelas (Opsional, PNG/JPG)</label>
                        <input type="file" name="homeroom_teacher_signature_file" accept="image/*" class="w-full text-xs">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Rombel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
