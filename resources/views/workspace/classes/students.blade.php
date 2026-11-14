@extends('layouts.app')

@section('title', 'Pengaturan Kelas — ' . $teachingClass->class_name)
@section('page_title', 'Pengaturan Buku Kelas — ' . $teachingClass->class_name)
@section('page_subtitle', $teachingClass->subject->name . ' • ' . $teachingClass->academicYear->full_label)

@section('content')
@include('partials.class-workspace-tabs')

<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
    {{-- Kolom Kiri: Informasi / Form Mata Pelajaran & Jadwal Mengajar --}}
    <div class="lg:col-span-7 space-y-5">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 md:p-6 space-y-5">
            @if(! auth()->user()->isAdmin())
                <div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-[10px] font-bold mb-1.5">
                        Tersinkronisasi Portal Admin
                    </span>
                    <h2 class="text-sm md:text-base font-bold text-slate-900">Informasi Kelas Tersinkronisasi Portal Admin</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Mata pelajaran, jadwal mengajar, dan hari tatap muka dikelola dari Portal Admin agar selalu sinkron dengan jadwal resmi sekolah.</p>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3">
                        <dt class="text-[11px] font-semibold text-slate-500">Mata Pelajaran</dt>
                        <dd class="font-bold text-slate-900 mt-0.5">{{ $teachingClass->subject->name ?? '-' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3">
                        <dt class="text-[11px] font-semibold text-slate-500">KKTP Efektif</dt>
                        <dd class="font-bold text-slate-900 mt-0.5">{{ $teachingClass->effective_kktp ?? 75 }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3">
                        <dt class="text-[11px] font-semibold text-slate-500">Hari Mengajar</dt>
                        <dd class="font-bold text-slate-900 mt-0.5">{{ $teachingClass->teaching_days_label ?: 'Belum dipetakan Admin' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3">
                        <dt class="text-[11px] font-semibold text-slate-500">Jam Pelajaran</dt>
                        <dd class="font-bold text-slate-900 mt-0.5">{{ $teachingClass->schedule_info ?: '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2 rounded-xl bg-slate-50 border border-slate-200/80 p-3">
                        <dt class="text-[11px] font-semibold text-slate-500">Mode Kolom Absensi</dt>
                        <dd class="font-bold text-slate-900 mt-0.5">
                            {{ $teachingClass->only_teaching_days ? 'Hanya Tanggal Hari Mengajar (Terfilter Jadwal Admin)' : 'Semua Hari Kerja Aktif' }}
                        </dd>
                    </div>
                </dl>
            @else
                <div>
                    <h2 class="text-sm md:text-base font-bold text-slate-900">Pengaturan Mata Pelajaran & Jadwal</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Ubah mata pelajaran yang Anda ampu atau perbarui jadwal mengajar untuk kelas ini.</p>
                </div>

                <form method="POST" action="{{ route('classes.update', $teachingClass) }}" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Mata Pelajaran *</label>
                    <select name="subject_id" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 font-semibold text-slate-800 bg-white">
                        @foreach($subjects as $sub)
                            @php
                                $hasCode = str_contains(strtoupper($sub->name), '(' . strtoupper($sub->code) . ')');
                            @endphp
                            <option value="{{ $sub->id }}" @selected($sub->id === $teachingClass->subject_id)>
                                {{ $hasCode ? $sub->name : "{$sub->name} ({$sub->code})" }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Standar KKM/KKTP otomatis mengikuti mata pelajaran yang dipilih.</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Hari Mengajar Mingguan (KBM Kelas Ini)</label>
                    <p class="text-[11px] text-slate-500 mb-2">Pilih hari apa saja Anda mengajar di kelas ini. Tanggal di luar hari yang dipilih otomatis disembunyikan pada menu Absensi.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
                        @php
                            $currentDays = $teachingClass->teaching_days ?? [];
                            $daysMap = [
                                1 => 'Senin',
                                2 => 'Selasa',
                                3 => 'Rabu',
                                4 => 'Kamis',
                                5 => 'Jumat',
                                6 => 'Sabtu',
                            ];
                        @endphp
                        @foreach($daysMap as $dayNum => $dayName)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-xs font-semibold select-none transition has-checked:bg-emerald-50 has-checked:border-emerald-300 has-checked:text-emerald-900">
                                <input type="checkbox" name="teaching_days[]" value="{{ $dayNum }}"
                                       @checked(in_array($dayNum, $currentDays, true))
                                       class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span>{{ $dayName }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Keterangan Jam Pelajaran / Jadwal Rinci</label>
                    <input type="text" name="schedule_info" value="{{ $teachingClass->schedule_info }}"
                           placeholder="Contoh: Senin (jam 6-7) & Kamis (jam 2)"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 font-medium text-slate-800">
                    <p class="text-[11px] text-slate-400 mt-1">Keterangan hari & jam mengajar ini dicetak pada bagian kop/identitas Jurnal, Absensi, dan Nilai.</p>
                </div>

                <div class="pt-3 border-t border-slate-100">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="only_teaching_days" value="1"
                               @checked($teachingClass->only_teaching_days ?? true)
                               class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <span class="block font-bold text-slate-800 text-xs">Hanya Tampilkan Tanggal Mengajar pada Tabel Absensi (Sembunyikan Hari Non-KBM)</span>
                            <span class="block text-[11px] text-slate-400 mt-0.5">
                                Sangat direkomendasikan. Tabel absensi hanya memuat kolom tanggal pertemuan yang ada jam pelajarannya, sehingga kolom nama siswa jauh lebih lebar dan tabel sangat rapi.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan Pengaturan Kelas</span>
                    </button>
                </div>
            </form>
            @endif
        </div>

        {{-- Area Bahaya / Proteksi: Hapus Buku Kelas --}}
        @if((int) $teachingClass->teacher_id === (int) auth()->id())
            {{-- Guru Pengampu (termasuk Guru yang merangkap Admin/Wakasek seperti Muhammad Irfan) berhak penuh menghapus buku kelas miliknya --}}
            <div class="bg-rose-50/70 rounded-2xl border border-rose-200 p-5 md:p-6 space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-rose-950">Hapus Buku Kelas Ini</h3>
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Tindakan ini akan menghapus buku administrasi <strong>Kelas {{ $teachingClass->class_name }} ({{ $teachingClass->subject->name }})</strong> milik Anda beserta seluruh catatan Jurnal Harian, Absensi, dan Daftar Nilainya.
                        </p>
                        <p class="text-[11px] text-rose-600 font-medium">
                            * Data Master Rombel & seluruh murid sekolah tetap aman dan tidak akan terhapus.
                        </p>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <form method="POST" action="{{ route('classes.destroy', $teachingClass) }}"
                          onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS seluruh buku kelas administrasi {{ $teachingClass->class_name }} ({{ $teachingClass->subject->name }}) beserta Jurnal, Absensi, dan Nilainya?\n\n(Data rombel dan nama murid di sekolah tetap aman).')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 shadow-2xs transition cursor-pointer">
                            Hapus Buku Kelas
                        </button>
                    </form>
                </div>
            </div>
        @elseif(auth()->user()->isAdmin())
            {{-- Admin yang bukan guru pengampu: Diberi proteksi integritas akademik --}}
            <div class="bg-amber-50/80 rounded-2xl border border-amber-200 p-5 md:p-6 space-y-2 shadow-2xs">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-amber-950">Proteksi Buku Administrasi Guru (Terkunci untuk Admin)</h3>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Sebagai <strong>Administrator Sekolah</strong>, Anda dapat melihat dan membantu pengelolaan identitas rombel, namun <strong>tidak dapat menghapus</strong> Buku Administrasi Guru milik guru lain demi melindungi integritas seluruh rekam jejak penilaian, absensi, dan jurnal harian guru pengampu.
                        </p>
                        <p class="text-[11px] text-amber-700 font-semibold">
                            * Penghapusan buku kelas hanya dapat dilakukan secara mandiri oleh guru pengampu: <strong>{{ $teachingClass->teacher?->name ?? 'Guru Pengampu' }}</strong>.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Kolom Kanan: Informasi Rombel & Wali Kelas Terpusat --}}
    <div class="lg:col-span-5 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 md:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900">Rombongan Belajar (Rombel)</h3>
                <span class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-[10px]">
                    Master Terpusat
                </span>
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                    <div class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Nama Kelas & Tingkat</div>
                    <div class="text-base font-extrabold text-slate-900">
                        Kelas {{ $teachingClass->class_name }}
                        <span class="text-xs font-semibold text-slate-500">(Tingkat {{ $teachingClass->grade_level }})</span>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1.5">
                    <div class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Wali Kelas</div>
                    <div class="text-sm font-bold text-slate-900">{{ $teachingClass->homeroom_teacher_name ?: '-' }}</div>
                    @if($teachingClass->homeroom_teacher_nip)
                        <div class="text-slate-600 font-mono text-[11px]">NIP. {{ $teachingClass->homeroom_teacher_nip }}</div>
                    @endif
                    @if(!empty($teachingClass->homeroom_teacher_signature))
                        <div class="pt-2">
                            <span class="text-[10px] text-slate-400 block mb-1 font-semibold uppercase">TTD Digital Wali Kelas:</span>
                            <div class="p-2 bg-white rounded-xl border border-slate-200 inline-block">
                                <img src="{{ $teachingClass->homeroom_teacher_signature }}" class="max-h-12" alt="TTD Wali Kelas">
                            </div>
                        </div>
                    @endif
                </div>

                <div class="p-3 rounded-xl bg-sky-50 border border-sky-200 text-sky-900 text-[11px] leading-relaxed">
                    ℹ️ <strong>Pengelolaan Terpusat:</strong> Nama rombel, daftar murid resmi, dan data wali kelas dikelola secara terpusat oleh <strong>Administrator Sekolah</strong> agar seragam di semua mata pelajaran.
                </div>

                @if(auth()->user()->isAdmin() && $teachingClass->school_class_id)
                    <div class="pt-2">
                        <a href="{{ route('admin.classes.show', $teachingClass->school_class_id) }}"
                           class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 text-white font-bold text-xs hover:bg-purple-700 shadow-2xs transition">
                            <span>Buka Master Rombel di Admin &rarr;</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
