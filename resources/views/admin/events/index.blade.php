@extends('layouts.app')

@section('title', 'Kalender & Hari Libur Sekolah')
@section('page_title', 'Kalender & Hari Libur Sekolah')
@section('page_subtitle', 'Kelola tanggal merah nasional, libur semester, dan agenda kegiatan sekolah non-KBM — otomatis terarsir di seluruh buku absensi guru.')

@section('content')
<div class="space-y-5" x-data="{ createModal: false }">
    {{-- Toolbar Filter & Tambah Agenda --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.events.index') }}" class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-700">Tahun Ajaran:</span>
            <select name="academic_year_id" onchange="this.form.submit()"
                    class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 bg-white">
                @foreach($allYears as $y)
                    <option value="{{ $y->id }}" @selected($activeYear->id === $y->id)>
                        {{ $y->full_label }} {{ $y->is_active ? '(Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </form>

        <button type="button" @click="createModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Tambah Hari Libur / Agenda</span>
        </button>
    </div>

    {{-- Banner Informasi Otomasi Kalender --}}
    <div class="rounded-2xl bg-indigo-50 border border-indigo-200 p-4 text-xs text-indigo-950 flex items-start gap-3 shadow-2xs">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 mt-0.5 font-bold">💡</div>
        <div class="space-y-1">
            <div class="font-extrabold text-sm text-indigo-900">Aturan Kalender Otomatis (6 Hari Sekolah: Senin s/d Sabtu)</div>
            <p class="text-indigo-800 leading-relaxed">
                1. <strong>Hari Minggu</strong> secara otomatis dideteksi sebagai hari libur akhir pekan pada seluruh menu absensi guru (kolom otomatis berlabel <em>Min</em> dan terarsir merah muda).<br>
                2. Tanggal merah nasional atau kegiatan sekolah (seperti HUT RI, Classmeeting, PTS/PAS, atau Libur Semester) yang Anda tambahkan di bawah ini <strong>otomatis terhubung ke seluruh buku absensi guru</strong>.<br>
                3. Hari libur/kegiatan yang dikosongkan <strong>sama sekali tidak dianggap alpa</strong> dan tidak merugikan persentase kehadiran siswa.
            </p>
        </div>
    </div>

    {{-- Tabel Daftar Agenda / Hari Libur --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <div class="text-xs font-bold text-slate-800">
                Daftar Tanggal Merah & Kegiatan Sekolah — {{ $activeYear->full_label }} ({{ $events->count() }} Agenda)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-100 text-slate-700 uppercase text-[11px] border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-center w-12 border-r border-slate-200">No</th>
                        <th class="px-4 py-3 border-r border-slate-200 w-44">Tanggal / Rentang</th>
                        <th class="px-4 py-3 border-r border-slate-200">Nama Agenda / Hari Libur</th>
                        <th class="px-3 py-3 border-r border-slate-200 w-36 text-center">Kategori</th>
                        <th class="px-3 py-3 border-r border-slate-200 w-28 text-center">Status KBM</th>
                        <th class="px-4 py-3 border-r border-slate-200">Keterangan</th>
                        <th class="px-3 py-3 text-center w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($events as $idx => $event)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 text-center font-bold text-slate-500 border-r border-slate-100">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 border-r border-slate-100 whitespace-nowrap">
                                @if($event->event_end_date && $event->event_end_date->ne($event->event_date))
                                    <div>{{ $event->event_date->format('d/m/Y') }} – {{ $event->event_end_date->format('d/m/Y') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">
                                        ({{ $event->event_date->diffInDays($event->event_end_date) + 1 }} hari)
                                    </div>
                                @else
                                    <div>{{ \App\Support\IndonesianDate::formatWithDay($event->event_date) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900 border-r border-slate-100">
                                {{ $event->title }}
                            </td>
                            <td class="px-3 py-3 text-center border-r border-slate-100">
                                @php
                                    $badgeClass = match($event->type) {
                                        'libur_nasional' => 'bg-rose-100 text-rose-800 border-rose-200',
                                        'kegiatan_sekolah' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'ujian_asesmen' => 'bg-sky-100 text-sky-800 border-sky-200',
                                        'libur_semester' => 'bg-purple-100 text-purple-800 border-purple-200',
                                        default => 'bg-slate-100 text-slate-700 border-slate-200',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-lg border text-[10px] font-bold {{ $badgeClass }}">
                                    {{ $event->type_label }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center border-r border-slate-100">
                                @if($event->is_holiday)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 text-[10px] font-bold">
                                        Libur KBM
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[10px] font-bold">
                                        Tetap KBM
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 border-r border-slate-100 text-[11px]">
                                {{ $event->description ?: '-' }}
                            </td>
                            <td class="px-3 py-3 text-center">
                                <form method="POST" action="{{ route('admin.events.destroy', $event) }}"
                                      onsubmit="return confirm('Hapus agenda {{ $event->title }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold text-[10px] transition">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                Belum ada tanggal merah atau agenda kegiatan sekolah yang didaftarkan untuk {{ $activeYear->full_label }}.<br>
                                <span class="text-[11px] text-slate-400">Hari Minggu tetap otomatis terdeteksi libur di seluruh kelas.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah Hari Libur / Agenda --}}
    <div x-show="createModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="createModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold">Tambah Hari Libur / Agenda Sekolah</h3>
                    <p class="text-[11px] text-slate-300">{{ $activeYear->full_label }}</p>
                </div>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.events.store') }}" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $activeYear->id }}">

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Hari Libur / Agenda Kegiatan *</label>
                    <input type="text" name="title" required placeholder="Misal: HUT Kemerdekaan RI ke-81, Classmeeting, PTS..."
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500 font-medium">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tanggal Mulai *</label>
                        <input type="date" name="event_date" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tanggal Selesai (Opsional)</label>
                        <input type="date" name="event_end_date"
                               placeholder="Kosongkan jika hanya 1 hari"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400">Isi jika berlangsung beberapa hari berturut-turut.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Agenda *</label>
                        <select name="type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
                            <option value="libur_nasional">Libur Nasional / Tanggal Merah</option>
                            <option value="kegiatan_sekolah">Kegiatan Sekolah (Non-KBM)</option>
                            <option value="ujian_asesmen">Penilaian / Ujian / Asesmen</option>
                            <option value="libur_semester">Libur Akhir Semester</option>
                        </select>
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_holiday" value="1" checked
                                   class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            <span class="text-slate-700 font-semibold">Meliburkan KBM Tatap Muka (Kolom Absensi Diarsir)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Keterangan / Catatan (Opsional)</label>
                    <textarea name="description" rows="2" placeholder="Catatan tambahan yang akan tampil di keterangan absensi..."
                              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="createModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 font-semibold text-xs hover:bg-slate-50">Batal</button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 shadow-2xs transition">
                        Simpan Agenda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
