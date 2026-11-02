@extends('layouts.app')

@section('title', 'Jurnal Harian — ' . $teachingClass->class_name)
@section('page_title', 'Menu 1: Jurnal Harian — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', $teachingClass->subject->name . ' • Tersinkronisasi otomatis dengan kehadiran murid pada menu Absensi.')

@section('content')
@include('partials.class-workspace-tabs')

<div class="space-y-4"
     x-data="journalWorkspace({
        previewUrl: @js(route('journals.attendance-preview', $teachingClass)),
        todayDate: @js(now()->toDateString()),
        hasProfileSig: @js(!empty($teachingClass->teacher?->signature_data) || !empty(auth()->user()->signature_data))
     })">

    {{-- Toolbar Filter, Tambah Jurnal & Cetak PDF/Excel --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('journals.index', $teachingClass) }}" class="flex flex-wrap items-center gap-2 text-xs">
            <select name="month" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                <option value="">Semua Bulan (1 Semester)</option>
                @foreach(\App\Support\IndonesianDate::MONTHS as $mNum => $mName)
                    <option value="{{ $mNum }}" @selected($filterMonth === $mNum)>{{ $mName }}</option>
                @endforeach
            </select>
            <input type="number" name="year" value="{{ $filterYear }}" min="2020" max="2099"
                   class="w-24 rounded-xl border border-slate-300 px-3 py-2 font-semibold">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari kegiatan / materi / catatan..."
                   class="w-52 rounded-xl border border-slate-300 px-3 py-2">
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800">Filter</button>
            @if($filterMonth || $search)
                <a href="{{ route('journals.index', $teachingClass) }}" class="px-3 py-2 rounded-xl border border-slate-300 text-slate-600 font-semibold">Reset</a>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <button type="button" @click="openCreate()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition">
                <span>+ Tambah Jurnal Harian</span>
            </button>
            <button type="button" @click="exportModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-2xs transition">
                <span>Cetak PDF & Download Excel</span>
            </button>
        </div>
    </div>

    {{-- Tabel Utama Jurnal Harian --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 uppercase text-[11px] border-b border-slate-200">
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-12">No</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200 w-36">Tanggal</th>
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-24">Jam Pelajaran Ke</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200">Kegiatan / Materi</th>
                        <th colspan="2" class="px-3 py-2 text-center border-b border-r border-slate-200 w-40 bg-emerald-50/70 text-emerald-950">Jumlah Murid</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-slate-200 w-56">Catatan</th>
                        <th rowspan="2" class="px-3 py-3 text-center border-r border-slate-200 w-28">Paraf</th>
                        <th rowspan="2" class="px-3 py-3 text-right w-36">Aksi</th>
                    </tr>
                    <tr class="bg-emerald-50/40 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                        <th class="px-2.5 py-2 text-center border-r border-slate-200 text-emerald-800">Hadir</th>
                        <th class="px-2.5 py-2 text-center border-r border-slate-200 text-rose-800">Tidak Hadir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($journals as $index => $journal)
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-3 py-3 text-center font-bold text-slate-700 border-r border-slate-100">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 border-r border-slate-100">
                                <div class="font-bold text-slate-900">{{ \App\Support\IndonesianDate::dayName($journal->journal_date) }}</div>
                                <div class="text-[11px] text-slate-500">{{ \App\Support\IndonesianDate::formatLong($journal->journal_date) }}</div>
                            </td>
                            <td class="px-3 py-3 text-center font-bold text-indigo-700 border-r border-slate-100">
                                {{ $journal->lesson_hours }}
                            </td>
                            <td class="px-4 py-3 text-slate-800 leading-relaxed border-r border-slate-100 whitespace-pre-line">{{ $journal->activity_material }}</td>
                            <td class="px-3 py-3 text-center font-extrabold text-emerald-700 bg-emerald-50/20 border-r border-slate-100">
                                {{ $journal->present_count }}
                            </td>
                            <td class="px-3 py-3 text-center font-extrabold {{ $journal->absent_count > 0 ? 'text-rose-700 bg-rose-50/30' : 'text-slate-600' }} border-r border-slate-100">
                                {{ $journal->absent_count }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 border-r border-slate-100">
                                <div>{{ $journal->notes ?: '-' }}</div>
                                @if($journal->attendance_breakdown)
                                    <div class="mt-1 inline-block px-2 py-0.5 rounded bg-slate-100 text-[10px] font-semibold text-slate-600">
                                        {{ $journal->attendance_breakdown }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center border-r border-slate-100">
                                @php $paraf = $journal->signature_data ?: $teachingClass->teacher?->signature_data; @endphp
                                @if(!empty($paraf))
                                    <img src="{{ $paraf }}" alt="Paraf" class="max-h-9 mx-auto bg-white border border-slate-200 rounded p-0.5">
                                @else
                                    <span class="text-[10px] text-slate-400 italic">Paraf Basah</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-1">
                                    <form method="POST" action="{{ route('journals.resync', [$teachingClass, $journal]) }}" title="Tarik ulang jumlah Hadir/Tidak Hadir dari Menu Absensi">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-semibold hover:bg-emerald-100">
                                            Sync Absen
                                        </button>
                                    </form>
                                    <button type="button"
                                            @click="openEdit(@js([
                                                'journal_date' => $journal->journal_date->toDateString(),
                                                'lesson_hours' => $journal->lesson_hours,
                                                'activity_material' => $journal->activity_material,
                                                'present_count' => $journal->present_count,
                                                'absent_count' => $journal->absent_count,
                                                'is_attendance_overridden' => $journal->is_attendance_overridden,
                                                'notes' => $journal->notes,
                                                'has_signature' => !empty($journal->signature_data),
                                                'update_url' => route('journals.update', [$teachingClass, $journal]),
                                            ]))"
                                            class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('journals.destroy', [$teachingClass, $journal]) }}"
                                          onsubmit="return confirm('Hapus baris jurnal tanggal {{ $journal->journal_date->format('d/m/Y') }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-slate-500">
                                Belum ada entri Jurnal Harian pada filter ini. Klik <strong>+ Tambah Jurnal Harian</strong> untuk mencatat kegiatan belajar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah / Edit Jurnal Harian --}}
    <div x-show="formModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="formModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-xl w-full overflow-hidden max-h-[92vh] flex flex-col">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold" x-text="isEdit ? 'Edit Jurnal Harian' : 'Tambah Jurnal Harian Baru'"></h3>
                <button type="button" @click="formModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>

            <form method="POST" :action="isEdit ? form.update_url : @js(route('journals.store', $teachingClass))"
                  class="p-5 space-y-3.5 text-xs overflow-y-auto">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Pembelajaran *</label>
                        <input type="date" name="journal_date" x-model="form.journal_date" @change="fetchAttendanceForDate()" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Jam Pelajaran Ke *</label>
                        <input type="text" name="lesson_hours" x-model="form.lesson_hours" required placeholder="Contoh: 1 - 2 atau 3 - 4"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kegiatan / Materi Pembelajaran *</label>
                    <textarea name="activity_material" x-model="form.activity_material" rows="3" required
                              placeholder="Tuliskan materi pokok & kegiatan pembelajaran yang dilaksanakan..."
                              class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
                </div>

                {{-- Panel Sinkronisasi Kehadiran Otomatis dari Menu Absensi --}}
                <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-bold text-emerald-950">Jumlah Murid (Tersinkronisasi dengan Menu Absensi)</div>
                        <button type="button" @click="fetchAttendanceForDate(true)"
                                class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-[11px] font-bold hover:bg-emerald-700">
                            Tarik dari Absensi
                        </button>
                    </div>
                    <p class="text-[11px] text-emerald-800" x-text="syncMessage"></p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-emerald-900 mb-1">Jumlah Hadir (. + Dispen)</label>
                            <input type="number" name="present_count" x-model="form.present_count" min="0"
                                   :readonly="!form.is_attendance_overridden && syncedRecorded > 0"
                                   class="w-full rounded-xl border border-emerald-300 bg-white px-3 py-1.5 font-extrabold text-emerald-800">
                        </div>
                        <div>
                            <label class="block font-semibold text-rose-900 mb-1">Jumlah Tidak Hadir (S + I + A)</label>
                            <input type="number" name="absent_count" x-model="form.absent_count" min="0"
                                   :readonly="!form.is_attendance_overridden && syncedRecorded > 0"
                                   class="w-full rounded-xl border border-rose-300 bg-white px-3 py-1.5 font-extrabold text-rose-800">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-0.5">
                        <input type="checkbox" name="is_attendance_overridden" value="1" x-model="form.is_attendance_overridden"
                               class="rounded border-slate-300 text-emerald-600">
                        <span class="text-[11px] text-slate-700 font-medium">Ubah angka Hadir/Tidak Hadir secara manual (kunci dari auto-sync)</span>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan Kelas / Kejadian Khusus</label>
                    <textarea name="notes" x-model="form.notes" rows="2"
                              placeholder="Misal: Siswa tidak hadir, tindak lanjut pembelajaran, siswa dispen..."
                              class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
                </div>

                {{-- Pilihan Paraf Jurnal --}}
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <label class="block font-bold text-slate-800">Paraf Jurnal Harian</label>
                    <div class="flex flex-wrap gap-3">
                        <template x-if="isEdit && form.has_signature">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="signature_mode" value="keep" x-model="form.signature_mode">
                                <span>Pertahankan Paraf Saat Ini</span>
                            </label>
                        </template>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="signature_mode" value="profile" x-model="form.signature_mode">
                            <span>Gunakan TTD Profil Saya</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="signature_mode" value="canvas" x-model="form.signature_mode" @change="initCanvas()">
                            <span>Goreskan Paraf Baru</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="signature_mode" value="empty" x-model="form.signature_mode">
                            <span>Kosongkan (Paraf Basah)</span>
                        </label>
                    </div>

                    <div x-show="form.signature_mode === 'canvas'" class="pt-2">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[11px] text-slate-600">Goreskan paraf di bawah ini:</span>
                            <button type="button" @click="pad && pad.clear()" class="text-rose-600 font-semibold hover:underline">Bersihkan</button>
                        </div>
                        <canvas x-ref="journalCanvas" width="380" height="100"
                                class="w-full h-24 rounded-xl border-2 border-dashed border-slate-300 bg-white cursor-crosshair touch-none"></canvas>
                        <input type="hidden" name="signature_data" :value="canvasData">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="formModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Jurnal</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Cetak PDF & Download Excel Jurnal Harian --}}
    @include('partials.export-modal', [
        'modalVar' => 'exportModal',
        'modalTitle' => 'Cetak PDF & Download Excel — Jurnal Harian Kelas ' . $teachingClass->class_name,
        'pdfRoute' => route('exports.journal.pdf', $teachingClass),
        'excelRoute' => route('exports.journal.excel', $teachingClass),
        'defaultOrientation' => 'landscape',
        'extraParams' => ['month' => $filterMonth ?? '', 'year' => $filterYear],
        'isJournalExport' => true,
    ])
</div>

@push('scripts')
<script>
function journalWorkspace(cfg) {
    return {
        formModal: false,
        exportModal: false,
        isEdit: false,
        pad: null,
        canvasData: '',
        syncedRecorded: 0,
        syncMessage: 'Memeriksa data Absensi pada tanggal terpilih...',
        form: {
            journal_date: cfg.todayDate,
            lesson_hours: '1 - 2',
            activity_material: '',
            present_count: 0,
            absent_count: 0,
            is_attendance_overridden: false,
            notes: '',
            signature_mode: 'profile',
            has_signature: false,
            update_url: '',
        },
        openCreate() {
            this.isEdit = false;
            this.form = {
                journal_date: cfg.todayDate,
                lesson_hours: '1 - 2',
                activity_material: '',
                present_count: 0,
                absent_count: 0,
                is_attendance_overridden: false,
                notes: '',
                signature_mode: cfg.hasProfileSig ? 'profile' : 'canvas',
                has_signature: false,
                update_url: '',
            };
            this.formModal = true;
            this.fetchAttendanceForDate();
            if (this.form.signature_mode === 'canvas') {
                this.initCanvas();
            }
        },
        openEdit(item) {
            this.isEdit = true;
            this.form = {
                journal_date: item.journal_date,
                lesson_hours: item.lesson_hours,
                activity_material: item.activity_material,
                present_count: item.present_count,
                absent_count: item.absent_count,
                is_attendance_overridden: Boolean(item.is_attendance_overridden),
                notes: item.notes || '',
                signature_mode: item.has_signature ? 'keep' : 'profile',
                has_signature: item.has_signature,
                update_url: item.update_url,
            };
            this.syncMessage = item.is_attendance_overridden
                ? 'Menggunakan angka kehadiran manual.'
                : 'Tersinkronisasi dengan data Absensi.';
            this.formModal = true;
        },
        initCanvas() {
            this.$nextTick(() => {
                this.pad = window.initSignatureCanvas(this.$refs.journalCanvas, (d) => {
                    this.canvasData = d;
                });
            });
        },
        async fetchAttendanceForDate(forceApply = false) {
            if (!this.form.journal_date) return;
            try {
                const res = await fetch(`${cfg.previewUrl}?date=${encodeURIComponent(this.form.journal_date)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.syncedRecorded = data.total_recorded || 0;
                if (data.total_recorded > 0) {
                    this.syncMessage = `Ditemukan data Absensi (${data.total_recorded} murid): Hadir ${data.present_count} (termasuk Dispen ${data.dispen_count}), Tidak Hadir ${data.absent_count} (${data.breakdown}).`;
                    if (!this.form.is_attendance_overridden || forceApply) {
                        this.form.present_count = data.present_count;
                        this.form.absent_count = data.absent_count;
                        if (forceApply) this.form.is_attendance_overridden = false;
                    }
                } else {
                    this.syncMessage = 'Belum ada isian Absensi pada tanggal ini. Anda dapat mengisi Absensi terlebih dahulu atau memasukkan angka sementara.';
                }
            } catch (e) {
                this.syncMessage = 'Gagal memuat pratinjau absensi.';
            }
        }
    };
}
</script>
@endpush
@endsection
