<div class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 text-xs shrink-0 select-none"
     role="group"
     aria-label="Pengaturan Zoom Tabel">
    <button type="button"
            @click="zoomOut()"
            :disabled="tableZoom <= tableZoomMin"
            title="Perkecil Tampilan Tabel (Zoom Out)"
            aria-label="Perkecil tampilan tabel"
            class="w-7 h-7 sm:w-6 sm:h-6 rounded-lg inline-flex items-center justify-center font-extrabold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
        &minus;
    </button>
    <button type="button"
            @click="resetZoom()"
            title="Klik untuk kembalikan ukuran tabel ke 100%"
            aria-label="Reset zoom tabel ke 100%"
            class="min-w-[48px] h-7 sm:h-6 px-2 rounded-lg inline-flex items-center justify-center font-extrabold tabular-nums transition cursor-pointer"
            :class="tableZoom !== 100 ? 'bg-emerald-600 text-white shadow-2xs hover:bg-emerald-700' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'">
        <span x-text="`${tableZoom}%`">100%</span>
    </button>
    <button type="button"
            @click="zoomIn()"
            :disabled="tableZoom >= tableZoomMax"
            title="Perbesar Tampilan Tabel (Zoom In)"
            aria-label="Perbesar tampilan tabel"
            class="w-7 h-7 sm:w-6 sm:h-6 rounded-lg inline-flex items-center justify-center font-extrabold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
        +
    </button>
</div>
