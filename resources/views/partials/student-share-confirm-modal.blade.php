@php
    $showVar = $showVar ?? 'shareConfirmModal.show';
    $classLabelExpr = $classLabelExpr ?? 'shareConfirmModal.className';
    $subjectLabelExpr = $subjectLabelExpr ?? 'shareConfirmModal.subjectName';
    $loadingExpr = $loadingExpr ?? 'false';
    $onSelectTrue = $onSelectTrue ?? 'confirmEnableStudentSharing(true)';
    $onSelectFalse = $onSelectFalse ?? 'confirmEnableStudentSharing(false)';
    $onCancel = $onCancel ?? 'closeStudentShareConfirm()';
@endphp

<div x-show="{{ $showVar }}"
     x-cloak
     style="display: none;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     @keydown.window.escape="if ({{ $showVar }}) {{ $onCancel }}"
     @click.self="{{ $onCancel }}"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/65 backdrop-blur-xs">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden text-left">
        <div class="px-5 py-4 bg-slate-900 text-white flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 flex items-center justify-center text-lg shrink-0">
                    🛡️
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-extrabold">Konfirmasi Sharing Nilai Murid (ON)</h3>
                    <p class="text-[11px] text-slate-300 truncate mt-0.5">
                        Kelas <strong class="text-white" x-text="{{ $classLabelExpr }}"></strong>
                        &mdash;
                        <span x-text="{{ $subjectLabelExpr }}"></span>
                    </p>
                </div>
            </div>
            <button type="button" @click="{{ $onCancel }}" class="text-slate-400 hover:text-white text-xl leading-none cursor-pointer">&times;</button>
        </div>

        <div class="p-5 space-y-3.5 text-xs">
            <p class="text-slate-700 leading-relaxed font-medium">
                Pilih apakah halaman lihat nilai murid untuk kelas ini ingin menggunakan <strong>fitur Anti-Screenshot</strong> atau ditampilkan normal setelah verifikasi <strong>NIS / NISN</strong>:
            </p>

            {{-- Opsi 1: Dengan Anti-Screenshot --}}
            <button type="button"
                    :disabled="{{ $loadingExpr }}"
                    @click="{{ $onSelectTrue }}"
                    class="w-full text-left p-3.5 rounded-2xl border-2 border-emerald-500/80 bg-emerald-50/70 hover:bg-emerald-100/80 transition cursor-pointer disabled:opacity-50 space-y-1.5 group">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-extrabold text-emerald-950 text-xs sm:text-sm flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>Aktifkan dengan Anti-Screenshot</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-bold shrink-0">
                        Direkomendasikan
                    </span>
                </div>
                <p class="text-[11px] text-emerald-900 leading-relaxed">
                    Angka nilai tertutup blur default (buka via <strong>Tekan &amp; Tahan</strong> / <strong>Intip 8 Detik</strong>), watermark Nama &amp; NISN berjalan, penguncian layar saat pindah tab/aplikasi, serta blokir salin &amp; cetak.
                </p>
            </button>

            {{-- Opsi 2: Tanpa Anti-Screenshot (Mode Normal Bersih) --}}
            <button type="button"
                    :disabled="{{ $loadingExpr }}"
                    @click="{{ $onSelectFalse }}"
                    class="w-full text-left p-3.5 rounded-2xl border border-slate-300 bg-white hover:border-sky-400 hover:bg-sky-50/50 transition cursor-pointer disabled:opacity-50 space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-extrabold text-slate-900 text-xs sm:text-sm flex items-center gap-1.5">
                        <span>👁️</span>
                        <span>Aktifkan Tanpa Anti-Screenshot (Mode Normal)</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-200 text-[10px] font-bold shrink-0">
                        Boleh Screenshot
                    </span>
                </div>
                <p class="text-[11px] text-slate-600 leading-relaxed">
                    Murid tetap wajib verifikasi <strong>NIS / NISN</strong> (hanya melihat nilainya masing-masing), namun nilai langsung terlihat jelas tanpa blur, tanpa watermark, dan boleh di-screenshot (mis. untuk ditunjukkan ke orang tua).
                </p>
            </button>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-3">
                <span class="text-[11px] text-slate-400">
                    Untuk mengganti mode nanti, matikan sharing (OFF) lalu aktifkan ulang (ON).
                </span>
                <button type="button"
                        @click="{{ $onCancel }}"
                        class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 font-bold hover:bg-slate-50 transition cursor-pointer shrink-0">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>
