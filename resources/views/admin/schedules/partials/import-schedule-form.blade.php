@php
    $templateBaseUrl = route('admin.schedules.master.template', $academicYear?->id ? ['academic_year_id' => $academicYear->id] : []);
    $cancelClick = $cancelClick ?? '';
    $cancelLabel = $cancelLabel ?? 'Batal';
    $submitLabel = $submitLabel ?? 'Mulai Import Jadwal';
@endphp

<form method="POST"
      action="{{ route('admin.schedules.master.import') }}"
      enctype="multipart/form-data"
      class="space-y-4"
      x-data="{
        templateShiftMode: 'double',
        templateBaseUrl: @js($templateBaseUrl),
        get templateDownloadUrl() {
            const sep = this.templateBaseUrl.includes('?') ? '&' : '?';
            return this.templateBaseUrl + sep + 'shift_mode=' + this.templateShiftMode;
        }
      }">
    @csrf
    <input type="hidden" name="academic_year_id" value="{{ $academicYear?->id }}">

    <div class="rounded-xl bg-indigo-50/80 border border-indigo-200 p-3.5 text-xs text-indigo-900 space-y-2.5">
        <p class="font-semibold">Panduan Singkat &amp; Template Excel:</p>
        <ul class="list-disc pl-4 space-y-1 text-indigo-800">
            <li>Gunakan <strong>Template Kosong (.xlsx)</strong> sesuai jumlah shift sekolah, file hasil <strong>Export Excel</strong> yang sudah diedit, atau file <strong>CSV</strong> format Wakasek.</li>
            <li>Sistem otomatis mengenali format <strong>1 Shift</strong> (1 tabel kelas) maupun <strong>2 Shift</strong> (2 tabel <code>HARI</code> &amp; <code>WAKTU</code> berdampingan).</li>
        </ul>

        <div class="pt-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold text-indigo-900">Mode Template Excel:</span>
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border cursor-pointer text-[11px] font-bold transition"
                       :class="templateShiftMode === 'single' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-800 border-indigo-200 hover:bg-indigo-100/70'">
                    <input type="radio" x-model="templateShiftMode" value="single" class="sr-only">
                    <span>1 Shift (Semua Rombel)</span>
                </label>
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border cursor-pointer text-[11px] font-bold transition"
                       :class="templateShiftMode === 'double' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-800 border-indigo-200 hover:bg-indigo-100/70'">
                    <input type="radio" x-model="templateShiftMode" value="double" class="sr-only">
                    <span>2 Shift (Pagi &amp; Siang)</span>
                </label>
            </div>

            <div>
                <a :href="templateDownloadUrl"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-indigo-100 text-indigo-700 font-bold border border-indigo-300 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span x-text="'Download Template Excel ' + (templateShiftMode === 'single' ? '1 Shift' : '2 Shift') + ' (.xlsx)'"></span>
                </a>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih File Jadwal (.xlsx, .xls, .csv)</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
               class="block w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-300 rounded-xl p-1.5 cursor-pointer">
    </div>

    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
        <input type="checkbox" name="sync_to_classes" value="1"
               class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-xs text-slate-700 leading-relaxed">
            <strong class="block text-slate-800">Sekaligus sinkronkan jadwal &amp; hari mengajar ke Buku Kelas Guru</strong>
            Membuat/memperbarui buku kelas guru sesuai pembagian kelas dan hari pada file impor.
        </span>
    </label>

    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
        <strong>Perhatian:</strong> Impor akan mengganti seluruh isi slot Master Jadwal pada tahun pelajaran aktif (<strong>{{ $academicYear?->full_label ?? '-' }}</strong>) dan menjalankan deteksi bentrok otomatis.
    </div>

    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        @if($cancelClick !== '')
            <button type="button" @click="{{ $cancelClick }}"
                    class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition cursor-pointer">
                {{ $cancelLabel }}
            </button>
        @endif
        <button type="submit"
                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-sm transition cursor-pointer">
            {{ $submitLabel }}
        </button>
    </div>
</form>
