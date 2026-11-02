@extends('layouts.app')

@section('title', 'Backup & Restore Database — Portal Admin')
@section('page_title', 'Backup & Restore Database')
@section('page_subtitle', 'Cadangkan seluruh data sistem secara terkompresi dan pulihkan database sewaktu-waktu dengan aman.')

@section('content')
<div class="space-y-5" x-data="{
    restoreModal: false,
    uploadModal: false,
    selectedRestoreFile: '',
    confirmedRestore: false,
    confirmedUpload: false,
    openRestoreServer(filename) {
        this.selectedRestoreFile = filename;
        this.confirmedRestore = false;
        this.restoreModal = true;
    }
}">

    {{-- Notifikasi Flash --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2.5">
                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">✓</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 font-bold">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2.5">
                <span class="w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center font-black">!</span>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-700 hover:text-rose-950 font-bold">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-semibold shadow-2xs">
            <div class="font-bold mb-1">Periksa kembali data Anda:</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Bar: Tombol Aksi Utama --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <span>Manajemen Cadangan & Pemulihan Database</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Mencakup Akun Guru, Rombel, Murid, Jurnal Harian, Absensi, Nilai, Standar KKTP & Kalender.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" @click="uploadModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 text-xs font-bold hover:bg-slate-50 shadow-2xs transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload &amp; Restore File</span>
            </button>

            <form method="POST" action="{{ route('admin.backup.store') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <span>Buat Backup Baru Sekarang</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Kartu Statistik Database & Penyimpanan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Total File Backup di Server</div>
                <div class="text-base font-black text-slate-900">
                    {{ $stats['total_backups_count'] }} File
                    <span class="text-xs font-semibold text-slate-500">({{ $stats['total_backup_size_formatted'] }})</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Database Engine &amp; Tabel</div>
                <div class="text-base font-black text-slate-900">
                    {{ $stats['driver'] }}: {{ $stats['table_count'] }} Tabel
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase text-slate-400">Cadangan Terakhir</div>
                <div class="text-xs font-black text-slate-900 truncate" title="{{ $stats['last_backup_time'] }}">
                    {{ $stats['last_backup_time'] }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Riwayat File Backup --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                <span>Daftar Riwayat Cadangan Database di Server</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-extrabold">{{ $backups->count() }}</span>
            </h3>
            <span class="text-[11px] text-slate-400 hidden sm:inline">Lokasi: <code>storage/app/backups/</code></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-center w-12">No</th>
                        <th class="px-4 py-3">Nama File Backup</th>
                        <th class="px-4 py-3">Tipe / Keterangan</th>
                        <th class="px-4 py-3">Waktu Pembuatan</th>
                        <th class="px-4 py-3 text-right">Ukuran File</th>
                        <th class="px-4 py-3 text-center w-48">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($backups as $index => $b)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 text-center font-bold text-slate-500">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold {{ $b['is_gzip'] ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-800' }}">
                                        {{ $b['is_gzip'] ? '.SQL.GZ' : '.SQL' }}
                                    </span>
                                    <span class="font-mono text-xs">{{ $b['filename'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($b['is_safety_auto'])
                                    <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 text-[10px] font-bold">
                                        🛡️ Otomatis (Pra-Restore)
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                        Manual
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $b['created_at_label'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-800">
                                {{ $b['size_formatted'] }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    {{-- Tombol Download --}}
                                    <a href="{{ route('admin.backup.download', $b['filename']) }}"
                                       title="Download file backup ke komputer / HP"
                                       class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center gap-1">
                                        <span>⬇️</span> <span class="hidden sm:inline">Unduh</span>
                                    </a>

                                    {{-- Tombol Restore --}}
                                    <button type="button"
                                            @click="openRestoreServer('{{ $b['filename'] }}')"
                                            title="Pulihkan database dari file ini"
                                            class="px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs transition flex items-center gap-1">
                                        <span>🔄</span> <span class="hidden sm:inline">Restore</span>
                                    </button>

                                    {{-- Tombol Hapus --}}
                                    <form method="POST" action="{{ route('admin.backup.destroy', $b['filename']) }}"
                                          onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS file backup {{ $b['filename'] }} dari server?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="Hapus file backup ini"
                                                class="px-2 py-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                                    📁
                                </div>
                                <div class="font-bold text-slate-700">Belum ada file backup di server</div>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Klik tombol <strong>"Buat Backup Baru Sekarang"</strong> di atas untuk mencadangkan seluruh data sistem.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panduan Tips Keamanan & Docker --}}
    <div class="bg-slate-900 rounded-2xl text-slate-300 p-5 md:p-6 text-xs space-y-3">
        <div class="flex items-center gap-2 text-white font-extrabold text-sm">
            <span>💡 Tips Keamanan &amp; Praktik Terbaik (Best Practices)</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
            <div class="bg-slate-800/80 rounded-xl p-3.5 border border-slate-700/60 space-y-1.5">
                <div class="font-bold text-emerald-400 flex items-center gap-1.5">
                    <span>1. Simpan Salinan Off-Site</span>
                </div>
                <p class="text-slate-400 leading-relaxed text-[11px]">
                    Setelah membuat backup di server, biasakan mengunduh file <code>.sql.gz</code> ke flashdisk khusus atau Google Drive sekolah setiap akhir bulan atau saat pembagian raport.
                </p>
            </div>

            <div class="bg-slate-800/80 rounded-xl p-3.5 border border-slate-700/60 space-y-1.5">
                <div class="font-bold text-amber-400 flex items-center gap-1.5">
                    <span>2. Auto Safety Backup</span>
                </div>
                <p class="text-slate-400 leading-relaxed text-[11px]">
                    Sebelum Anda memulihkan database, sistem secara otomatis mencadangkan kondisi data saat ini dengan awalan <code>safety_auto_...</code>, sehingga tidak ada resiko kehilangan data.
                </p>
            </div>

            <div class="bg-slate-800/80 rounded-xl p-3.5 border border-slate-700/60 space-y-1.5">
                <div class="font-bold text-sky-400 flex items-center gap-1.5">
                    <span>3. Ketahanan Docker Mini PC</span>
                </div>
                <p class="text-slate-400 leading-relaxed text-[11px]">
                    File cadangan tersimpan di volume <code>./data/backups</code> pada Mini PC Anda. Seluruh file tetap aman meskipun kontainer Docker di-rebuild atau di-restart.
                </p>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Restore dari Server (Safety Guard) --}}
    <div x-show="restoreModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="restoreModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-rose-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">⚠️</span>
                    <h3 class="text-sm font-extrabold uppercase tracking-wide">Konfirmasi Pemulihan Database</h3>
                </div>
                <button type="button" @click="restoreModal = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.backup.restore') }}" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="source" value="server">
                <input type="hidden" name="filename" :value="selectedRestoreFile">

                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 space-y-1.5">
                    <div class="font-extrabold text-rose-950 flex items-center gap-1.5">
                        <span>PERINGATAN RESIKO TINGGI:</span>
                    </div>
                    <p class="leading-relaxed">
                        Tindakan ini akan <strong>MENIMPA SELURUH DATA SISTEM</strong> saat ini dengan data dari file cadangan berikut:
                    </p>
                    <div class="font-mono font-bold text-rose-950 bg-white/80 px-2.5 py-1.5 rounded-lg border border-rose-200 truncate" x-text="selectedRestoreFile"></div>
                </div>

                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 space-y-1 text-[11px]">
                    <div class="font-bold flex items-center gap-1.5 text-emerald-950">
                        <span>🛡️ Jaminan Keamanan Sistem:</span>
                    </div>
                    <p>
                        Sistem akan secara otomatis membuat <strong>Cadangan Darurat (Safety Backup)</strong> dari kondisi database saat ini sebelum proses pemulihan dieksekusi.
                    </p>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="flex items-start gap-2.5 cursor-pointer p-2.5 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100">
                        <input type="checkbox" name="confirm_restore" value="1" x-model="confirmedRestore"
                               class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                        <span class="text-slate-800 font-semibold leading-relaxed">
                            Saya memahami resiko ini dan MENYETUJUI pemulihan database sekarang.
                        </span>
                    </label>
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="restoreModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batalkan
                    </button>
                    <button type="submit"
                            :disabled="!confirmedRestore"
                            class="px-5 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition">
                        Ya, Pulihkan Database Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Upload & Restore File --}}
    <div x-show="uploadModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="uploadModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📤</span>
                    <h3 class="text-sm font-extrabold">Upload &amp; Pulihkan File Backup</h3>
                </div>
                <button type="button" @click="uploadModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.backup.restore') }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="source" value="upload">

                <div>
                    <label class="block font-bold text-slate-800 mb-1.5">Pilih File Backup (.sql / .sql.gz / .gz) *</label>
                    <input type="file" name="backup_file" required accept=".sql,.gz,.sql.gz"
                           class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1">Ukuran maksimal file: 25 MB.</p>
                </div>

                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 space-y-1">
                    <div class="font-extrabold text-rose-950">PERINGATAN RESIKO:</div>
                    <p class="leading-relaxed">
                        Data yang ada di dalam file upload akan menimpa database sistem. Pastikan file berasal dari sumber terpercaya (Sistem Administrasi Guru SMPN 2 Lemahabang).
                    </p>
                </div>

                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-[11px]">
                    🛡️ Sistem otomatis membuat cadangan darurat (Safety Backup) sesaat sebelum proses upload dieksekusi.
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="flex items-start gap-2.5 cursor-pointer p-2.5 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100">
                        <input type="checkbox" name="confirm_restore" value="1" x-model="confirmedUpload"
                               class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                        <span class="text-slate-800 font-semibold leading-relaxed">
                            Saya menyetujui pemulihan database dari file yang di-upload.
                        </span>
                    </label>
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="uploadModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batalkan
                    </button>
                    <button type="submit"
                            :disabled="!confirmedUpload"
                            class="px-5 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition">
                        Upload &amp; Pulihkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
