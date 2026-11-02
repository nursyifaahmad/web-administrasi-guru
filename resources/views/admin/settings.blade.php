@extends('layouts.app')

@section('title', 'Kop Sekolah & Tahun Ajaran')
@section('page_title', 'Pengaturan Kop Surat Resmi Sekolah & Tahun Ajaran')
@section('page_subtitle', 'Kelola identitas Kop Surat cetakan PDF/Excel, Kepala Sekolah + NIP, serta periode Tahun Ajaran & Semester.')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    {{-- Kolom Kiri: Profil Sekolah & Kop Surat --}}
    <div class="xl:col-span-7">
        <form method="POST" action="{{ route('admin.settings.school.update') }}" enctype="multipart/form-data"
              class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-6 space-y-5 text-xs"
              x-data="{
                hmSigData: '',
                pad: null,
                leftSize: {{ (int) old('logo_left_size', $school->logo_left_size ?: 92) }},
                rightSize: {{ (int) old('logo_right_size', $school->logo_right_size ?: 92) }},
                syncEqualLogoSize: {{ (int) old('logo_left_size', $school->logo_left_size ?: 92) === (int) old('logo_right_size', $school->logo_right_size ?: 92) ? 'true' : 'false' }},
                leftPreview: @js($school->resolvedLogoBox('left')['data_uri'] ?? ''),
                rightPreview: @js($school->resolvedLogoBox('right')['data_uri'] ?? ''),
                clearLeft: false,
                clearRight: false,
                onLeftSizeInput() {
                    if (this.syncEqualLogoSize) {
                        this.rightSize = this.leftSize;
                    }
                },
                onRightSizeInput() {
                    if (this.syncEqualLogoSize) {
                        this.leftSize = this.rightSize;
                    }
                },
                previewFile(event, side) {
                    const file = event.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (side === 'left') { this.leftPreview = e.target.result; this.clearLeft = false; }
                        else { this.rightPreview = e.target.result; this.clearRight = false; }
                    };
                    reader.readAsDataURL(file);
                },
                init() {
                    this.$nextTick(() => {
                        this.pad = window.initSignatureCanvas(this.$refs.hmCanvas, (d) => this.hmSigData = d);
                    });
                }
              }">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-sm font-bold text-slate-900">Identitas Kop Surat Resmi & Titimangsa</h2>
                <p class="text-xs text-slate-500">Ditampilkan otomatis pada header dokumen Cetak PDF dan Download Excel seluruh guru.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Baris Kop 1 (Pemerintah Daerah)</label>
                    <input type="text" name="gov_header" value="{{ old('gov_header', $school->gov_header) }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Baris Kop 2 (Dinas / Yayasan)</label>
                    <input type="text" name="department_header" value="{{ old('department_header', $school->department_header) }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1">Nama Sekolah *</label>
                    <input type="text" name="school_name" value="{{ old('school_name', $school->school_name) }}" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">NPSN</label>
                    <input type="text" name="npsn" value="{{ old('npsn', $school->npsn) }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kota Default Titimangsa *</label>
                    <input type="text" name="default_city" value="{{ old('default_city', $school->default_city) }}" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1">Alamat Lengkap Sekolah</label>
                    <textarea name="address" rows="2" class="w-full rounded-xl border border-slate-300 px-3 py-2">{{ old('address', $school->address) }}</textarea>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Telepon Sekolah</label>
                    <input type="text" name="phone" value="{{ old('phone', $school->phone) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email Sekolah</label>
                    <input type="text" name="email" value="{{ old('email', $school->email) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
            </div>

            {{-- Upload & Ukuran Logo Kiri & Kanan --}}
            <div class="pt-3 border-t border-slate-100 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-emerald-50/70 border border-emerald-200 rounded-xl px-3.5 py-2">
                    <label class="flex items-center gap-2 font-bold text-emerald-950 cursor-pointer">
                        <input type="checkbox" x-model="syncEqualLogoSize" @change="if(syncEqualLogoSize) rightSize = leftSize" class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
                        <span>Kunci Ukuran Presisi Sama Besar (Logo Kiri = Logo Kanan)</span>
                    </label>
                    <span class="text-[11px] text-emerald-800">Margin kosong gambar otomatis dipotong agar tinggi kedua lambang 100% sejajar</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <label class="block font-bold text-slate-700">Logo Kop Kiri (Pemda / Yayasan)</label>
                        @if(!empty($school->logo_left_data))
                            <div class="flex items-center gap-3">
                                <img src="{{ $school->resolvedLogoBox('left')['data_uri'] }}" class="h-14 w-14 object-contain bg-white border rounded p-1" alt="Logo Kiri">
                                <label class="flex items-center gap-1.5 text-rose-600 font-semibold cursor-pointer">
                                    <input type="checkbox" name="clear_logo_left" value="1" x-model="clearLeft"> Hapus Logo Kiri
                                </label>
                            </div>
                        @endif
                        <input type="file" name="logo_left_file" accept="image/*" @change="previewFile($event, 'left')" class="w-full text-xs">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-slate-700">Ukuran Tinggi Logo Kiri:</span>
                                <span class="font-extrabold text-emerald-700" x-text="leftSize + ' px'"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="range" name="logo_left_size" min="40" max="160" step="2" x-model.number="leftSize" @input="onLeftSizeInput()" class="w-full accent-emerald-600">
                                <input type="number" min="40" max="160" x-model.number="leftSize" @input="onLeftSizeInput()" class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-center font-bold">
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <label class="block font-bold text-slate-700">Logo Kop Kanan (Logo Sekolah)</label>
                        @if(!empty($school->logo_right_data))
                            <div class="flex items-center gap-3">
                                <img src="{{ $school->resolvedLogoBox('right')['data_uri'] }}" class="h-14 w-14 object-contain bg-white border rounded p-1" alt="Logo Kanan">
                                <label class="flex items-center gap-1.5 text-rose-600 font-semibold cursor-pointer">
                                    <input type="checkbox" name="clear_logo_right" value="1" x-model="clearRight"> Hapus Logo Kanan
                                </label>
                            </div>
                        @endif
                        <input type="file" name="logo_right_file" accept="image/*" @change="previewFile($event, 'right')" class="w-full text-xs">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-slate-700">Ukuran Tinggi Logo Kanan:</span>
                                <span class="font-extrabold text-emerald-700" x-text="rightSize + ' px'"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="range" name="logo_right_size" min="40" max="160" step="2" x-model.number="rightSize" @input="onRightSizeInput()" class="w-full accent-emerald-600">
                                <input type="number" min="40" max="160" x-model.number="rightSize" @input="onRightSizeInput()" class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-center font-bold">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pratinjau Langsung Kop Surat --}}
                <div class="rounded-xl border border-slate-300 bg-white p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pratinjau Proporsi Kop Surat Cetakan</span>
                        <span class="text-[11px] text-slate-400">Geser slider ukuran di atas untuk menyeimbangkan logo kiri & kanan</span>
                    </div>
                    <div class="border-b-4 border-double border-slate-900 pb-3 flex items-center justify-between gap-3">
                        <div class="shrink-0 flex items-center justify-center" style="min-width: 110px;">
                            <template x-if="leftPreview && !clearLeft">
                                <img :src="leftPreview" :style="'height:' + leftSize + 'px; width: auto; max-width:' + Math.round(leftSize * 1.35) + 'px;'" class="object-contain" alt="Preview Logo Kiri">
                            </template>
                        </div>
                        <div class="text-center flex-1 min-w-0">
                            <div class="text-xs sm:text-sm font-bold uppercase text-slate-900 leading-tight">{{ $school->gov_header ?: 'PEMERINTAH KABUPATEN / KOTA' }}</div>
                            <div class="text-xs sm:text-sm font-bold uppercase text-slate-900 leading-none mt-0.5">{{ $school->department_header ?: 'DINAS PENDIDIKAN' }}</div>
                            <div class="text-base sm:text-lg font-extrabold uppercase text-slate-950 leading-none mt-0.5 mb-0.5">{{ $school->school_name }}</div>
                            <div class="text-[11px] text-slate-600 leading-snug">
                                @if($school->singleLineAddress() !== '')
                                    <div class="whitespace-nowrap overflow-hidden text-ellipsis">{{ $school->singleLineAddress() }}</div>
                                @endif
                                @if($school->contactAndIdentityLine() !== '')
                                    <div class="whitespace-nowrap overflow-hidden text-ellipsis">{{ $school->contactAndIdentityLine() }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center justify-center" style="min-width: 110px;">
                            <template x-if="rightPreview && !clearRight">
                                <img :src="rightPreview" :style="'height:' + rightSize + 'px; width: auto; max-width:' + Math.round(rightSize * 1.35) + 'px;'" class="object-contain" alt="Preview Logo Kanan">
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Data Kepala Sekolah --}}
            <div class="pt-3 border-t border-slate-100 space-y-3">
                <h3 class="text-sm font-bold text-slate-900">Pejabat Pengesah (Kepala Sekolah)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar Kepala Sekolah *</label>
                        <input type="text" name="headmaster_name" value="{{ old('headmaster_name', $school->headmaster_name) }}" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">NIP Kepala Sekolah</label>
                        <input type="text" name="headmaster_nip" value="{{ old('headmaster_nip', $school->headmaster_nip) }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                </div>

                @if(!empty($school->headmaster_signature))
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                        <img src="{{ $school->headmaster_signature }}" class="max-h-14 bg-white border rounded p-1" alt="TTD Kepsek">
                        <label class="flex items-center gap-1.5 text-rose-600 font-semibold">
                            <input type="checkbox" name="clear_headmaster_signature" value="1"> Hapus TTD Kepsek
                        </label>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-semibold text-slate-700">Kanvas TTD Kepala Sekolah:</span>
                            <button type="button" @click="pad && pad.clear()" class="text-rose-600 font-semibold hover:underline">Bersihkan</button>
                        </div>
                        <canvas x-ref="hmCanvas" width="320" height="110"
                                class="w-full h-24 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 cursor-crosshair touch-none"></canvas>
                        <input type="hidden" name="headmaster_signature_data" :value="hmSigData">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Atau Upload PNG TTD Kepala Sekolah:</label>
                        <input type="file" name="headmaster_signature_file" accept="image/*" class="w-full text-xs">
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition">
                    Simpan Pengaturan Sekolah & Kop
                </button>
            </div>
        </form>
    </div>

    {{-- Kolom Kanan: Manajemen Tahun Ajaran & Semester --}}
    <div class="xl:col-span-5 space-y-5">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-6 text-xs">
            <h2 class="text-sm font-bold text-slate-900 mb-1">Tambah Periode Tahun Ajaran & Semester</h2>
            <p class="text-slate-500 mb-4">Setiap pergantian semester, arsip semester sebelumnya tetap tersimpan aman.</p>

            <form method="POST" action="{{ route('admin.settings.years.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tahun Ajaran *</label>
                        <input type="text" name="year_name" placeholder="2027/2028" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Semester *</label>
                        <select name="semester" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer pt-1">
                    <input type="checkbox" name="set_active" value="1" class="rounded border-slate-300 text-emerald-600">
                    <span class="font-medium text-slate-700">Jadikan sebagai Semester Aktif saat ini</span>
                </label>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition">
                    + Tambah Tahun Ajaran
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden text-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 font-bold text-sm text-slate-900">
                Daftar Tahun Ajaran & Arsip Semester
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($academicYears as $ay)
                    <div class="px-5 py-3.5 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900 flex items-center gap-2">
                                <span>TA {{ $ay->year_name }} — Semester {{ $ay->semester }}</span>
                                @if($ay->is_active)
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-extrabold">SEMESTER AKTIF</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                Total Buku Kelas: <strong>{{ $ay->teaching_classes_count }}</strong> kelas
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if(!$ay->is_active)
                                <form method="POST" action="{{ route('admin.settings.years.activate', $ay) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold hover:bg-emerald-100">
                                        Aktifkan
                                    </button>
                                </form>
                                @if($ay->teaching_classes_count === 0)
                                    <form method="POST" action="{{ route('admin.settings.years.destroy', $ay) }}"
                                          onsubmit="return confirm('Hapus periode ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
