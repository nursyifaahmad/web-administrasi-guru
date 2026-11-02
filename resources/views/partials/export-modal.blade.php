<div x-show="{{ $modalVar }}" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div @click.away="{{ $modalVar }} = false"
         class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden"
         x-data="{
            city: @js($globalSchool->default_city ?? 'Lemahabang'),
            print_date: @js(now()->toDateString()),
            paper_size: 'F4',
            orientation: @js($defaultOrientation ?? 'landscape'),
            show_header: true,
            show_headmaster: false,
            show_digital_signature: true,
            show_journal_paraf: true,
            month: @js($extraParams['month'] ?? ''),
            year: @js($extraParams['year'] ?? now()->year),
            buildUrl(base, downloadPdf = false) {
                const params = new URLSearchParams();
                params.set('city', this.city);
                params.set('print_date', this.print_date);
                params.set('paper_size', this.paper_size);
                params.set('orientation', this.orientation);
                params.set('show_header', this.show_header ? '1' : '0');
                params.set('show_headmaster', this.show_headmaster ? '1' : '0');
                params.set('show_digital_signature', this.show_digital_signature ? '1' : '0');
                params.set('show_journal_paraf', this.show_journal_paraf ? '1' : '0');
                if (this.month) params.set('month', this.month);
                if (this.year) params.set('year', this.year);
                if (downloadPdf) params.set('download', '1');
                return base + '?' + params.toString();
            }
         }">
        <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold">{{ $modalTitle }}</h3>
                <p class="text-[11px] text-slate-300">Dilengkapi Titimangsa, Wali Kelas + NIP, & Guru Mapel + NIP</p>
            </div>
            <button type="button" @click="{{ $modalVar }} = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <div class="p-5 space-y-4 text-xs">
            @if(!empty($showMonthSelector))
            <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Bulan Absensi</label>
                    <select x-model="month" class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5 border">
                        @foreach(\App\Support\IndonesianDate::MONTHS as $mNum => $mName)
                            <option value="{{ $mNum }}">{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Tahun</label>
                    <input type="number" x-model="year" min="2020" max="2099" class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5 border">
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Kota Titimangsa</label>
                    <input type="text" x-model="city" placeholder="Misal: Lemahabang"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Tanggal Cetak (Titimangsa)</label>
                    <input type="date" x-model="print_date"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Ukuran Kertas</label>
                    <select x-model="paper_size" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                        <option value="F4">F4 / Folio Resmi (21.5 x 33 cm)</option>
                        <option value="A4">A4 Standar (21 x 29.7 cm)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Orientasi Kertas</label>
                    <select x-model="orientation" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                        <option value="landscape">Landscape (Mendatar)</option>
                        <option value="portrait">Portrait (Tegak)</option>
                    </select>
                </div>
            </div>

            <div class="space-y-2 pt-1 border-t border-slate-100">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="show_header" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-slate-700 font-medium">Sertakan Kop Surat Resmi Sekolah & Identitas Lengkap</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="show_headmaster" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-slate-700 font-medium">Tampilkan Kolom "Mengetahui, Kepala Sekolah + NIP"</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="show_digital_signature" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-slate-700 font-medium">Tampilkan Gambar Tanda Tangan Digital (bila tersedia)</span>
                </label>
                @if(!empty($isJournalExport))
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="show_journal_paraf" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-slate-700 font-medium">Tampilkan Paraf Digital pada Kolom Paraf Tabel Jurnal (matikan untuk paraf basah)</span>
                </label>
                @endif
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-[11px] text-slate-600">
                <div class="font-bold text-slate-800 mb-0.5">Pratinjau Penandatangan Bawah:</div>
                <div>&bull; Kiri: <strong>Wali Kelas {{ $teachingClass->class_name }}</strong> — {{ $teachingClass->homeroom_teacher_name }} (NIP. {{ $teachingClass->homeroom_teacher_nip ?: '-' }})</div>
                <div>&bull; Kanan: <strong x-text="city + ', ' + print_date"></strong> — <strong>Guru Mapel</strong> {{ $teachingClass->teacher->name ?? '-' }} (NIP. {{ $teachingClass->teacher->nip ?: '-' }})</div>
            </div>
        </div>

        <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-end gap-2">
            <a :href="buildUrl(@js($pdfRoute), false)" target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 transition">
                <span>Buka / Cetak PDF</span>
            </a>
            <a :href="buildUrl(@js($pdfRoute), true)"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-bold hover:bg-rose-100 transition">
                <span>Unduh PDF</span>
            </a>
            <a :href="buildUrl(@js($excelRoute), false)"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition">
                <span>Download Excel (.xlsx)</span>
            </a>
        </div>
    </div>
</div>
