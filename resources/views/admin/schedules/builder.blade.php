@extends('layouts.app')

@section('title', 'Smart Timetable Builder — Buat Jadwal Baru dari Nol')
@section('page_title', 'Smart Timetable Builder')
@section('page_subtitle', 'Wizard penyusunan jadwal sekolah dari awal: konfigurasi waktu, kegiatan non-KBM, beban mengajar, generator anti-bentrok, dan sinkronisasi.')

@section('content')
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{
        step: 1,
        maxStep: 5,
        academicYearLabel: @js($academicYear ? $academicYear->full_label : '-'),

        // Step 1: Waktu Belajar
        jpDuration: {{ (int) $defaultConfig['jp_duration'] }},
        startTime: '{{ $defaultConfig['start_time'] }}',
        daysCount: {{ (int) $defaultConfig['days_count'] }},
        dailyJp: {
            1: {{ (int) ($defaultConfig['daily_jp'][1] ?? 7) }},
            2: {{ (int) ($defaultConfig['daily_jp'][2] ?? 8) }},
            3: {{ (int) ($defaultConfig['daily_jp'][3] ?? 8) }},
            4: {{ (int) ($defaultConfig['daily_jp'][4] ?? 8) }},
            5: {{ (int) ($defaultConfig['daily_jp'][5] ?? 5) }},
            6: {{ (int) ($defaultConfig['daily_jp'][6] ?? 7) }},
        },

        // Step 2: Kegiatan Non-KBM
        activities: @js($defaultConfig['activities']),
        previewDay: 1,
        gridPreview: @js($initialGrid),
        isCalculating: false,

        // Step 3: Rombel & Beban Guru
        availableClasses: [
            '7A', '7B', '7C', '7D', '7E', '7F', '7G',
            '8A', '8B', '8C', '8D', '8E', '8F', '8G',
            '9A', '9B', '9C', '9D', '9E', '9F', '9G'
        ],
        selectedClasses: [
            '7A', '7B', '7C', '7D', '7E', '7F', '7G',
            '8A', '8B', '8C', '8D', '8E', '8F', '8G',
            '9A', '9B', '9C', '9D', '9E', '9F', '9G'
        ],
        teachersList: @js($teachers->values()),
        scheduleTeachers: @js($scheduleTeachers->values()),
        subjectsList: @js($subjects->values()),

        // Input form workload
        wlTeacherId: '',
        wlTeacherName: '',
        wlTeacherCode: '',
        wlSubjectId: '',
        wlSubjectName: '',
        wlClasses: [],
        wlJp: 3,

        workloads: [],

        // Step 4: Metode
        generationMode: 'auto', // 'auto' | 'manual'
        shiftMode: 'single', // 'single' | 'double'

        init() {
            // Inisialisasi preset beban jika kosong
            this.recalculateGrid();
        },

        // Helper: Terapkan JP seragam ke semua hari
        applyUniformJp(val) {
            val = parseInt(val) || 8;
            for (let d = 1; d <= 6; d++) {
                this.dailyJp[d] = (d === 5) ? Math.min(val, 5) : val; // Jumat maksimal 5 JP sebelum sholat jumat
            }
            this.recalculateGrid();
        },

        // AJAX / Local recalculate time grid
        async recalculateGrid() {
            this.isCalculating = true;
            try {
                let resp = await fetch('{{ route('admin.schedules.master.builder.calculate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        jp_duration: this.jpDuration,
                        start_time: this.startTime,
                        days_count: this.daysCount,
                        daily_jp: this.dailyJp,
                        activities: this.activities
                    })
                });
                let data = await resp.json();
                if (data.success && data.grid) {
                    this.gridPreview = data.grid;
                }
            } catch (err) {
                console.error('Gagal kalkulasi grid waktu:', err);
            } finally {
                this.isCalculating = false;
            }
        },

        // Tambah kegiatan non-KBM baru
        addActivity() {
            this.activities.push({
                day: 'all',
                name: 'ISTIRAHAT 2',
                position: 'after_jp_6',
                duration: 20
            });
            this.recalculateGrid();
        },

        removeActivity(idx) {
            this.activities.splice(idx, 1);
            this.recalculateGrid();
        },

        // Handler saat memilih guru di form workload
        onSelectTeacher(tId) {
            this.wlTeacherId = tId;
            let t = this.teachersList.find(x => String(x.id) === String(tId));
            if (t) {
                this.wlTeacherName = t.name;
                // Cari kode guru dari scheduleTeachers
                let st = this.scheduleTeachers.find(s => s.user_id === t.id || s.name.toLowerCase().includes(t.name.toLowerCase()));
                this.wlTeacherCode = st ? st.code : t.name.replace(/[^a-zA-Z]/g, '').substring(0, 4).toUpperCase();
            } else {
                this.wlTeacherName = '';
                this.wlTeacherCode = '';
            }
        },

        onSelectSubject(sId) {
            this.wlSubjectId = sId;
            let s = this.subjectsList.find(x => String(x.id) === String(sId));
            this.wlSubjectName = s ? s.name : '';
        },

        toggleAllClasses() {
            if (this.selectedClasses.length === this.availableClasses.length) {
                this.selectedClasses = [];
            } else {
                this.selectedClasses = [...this.availableClasses];
            }
        },

        // Tambah beban mengajar
        addWorkloadItem() {
            if (!this.wlTeacherName) {
                alert('Silakan pilih guru terlebih dahulu.');
                return;
            }
            if (!this.wlSubjectName) {
                alert('Silakan pilih mata pelajaran terlebih dahulu.');
                return;
            }
            if (this.wlClasses.length === 0) {
                alert('Pilih minimal satu rombel kelas yang diajar.');
                return;
            }
            let jp = parseInt(this.wlJp) || 0;
            if (jp <= 0) {
                alert('Jumlah JP per kelas harus lebih besar dari 0.');
                return;
            }

            for (let c of this.wlClasses) {
                // Cek apakah sudah ada guru lain atau guru yang sama mengajar mapel ini di kelas ini
                let existingIdx = this.workloads.findIndex(w => w.class_name === c && w.subject_name === this.wlSubjectName);
                if (existingIdx >= 0) {
                    this.workloads[existingIdx] = {
                        user_id: this.wlTeacherId ? parseInt(this.wlTeacherId) : null,
                        teacher_name: this.wlTeacherName,
                        teacher_code: this.wlTeacherCode,
                        subject_id: this.wlSubjectId ? parseInt(this.wlSubjectId) : null,
                        subject_name: this.wlSubjectName,
                        class_name: c,
                        jp_count: jp
                    };
                } else {
                    this.workloads.push({
                        user_id: this.wlTeacherId ? parseInt(this.wlTeacherId) : null,
                        teacher_name: this.wlTeacherName,
                        teacher_code: this.wlTeacherCode,
                        subject_id: this.wlSubjectId ? parseInt(this.wlSubjectId) : null,
                        subject_name: this.wlSubjectName,
                        class_name: c,
                        jp_count: jp
                    });
                }
            }

            // Reset pilihan kelas
            this.wlClasses = [];
        },

        removeWorkload(idx) {
            this.workloads.splice(idx, 1);
        },

        getTotalJpAllocated() {
            return this.workloads.reduce((sum, w) => sum + (parseInt(w.jp_count) || 0), 0);
        },

        getTeacherSummary() {
            let map = {};
            for (let w of this.workloads) {
                let key = w.teacher_name;
                if (!map[key]) {
                    map[key] = {
                        name: w.teacher_name,
                        code: w.teacher_code,
                        subjects: {},
                        totalJp: 0,
                        classesCount: 0
                    };
                }
                if (!map[key].subjects[w.subject_name]) {
                    map[key].subjects[w.subject_name] = 0;
                }
                map[key].subjects[w.subject_name] += (parseInt(w.jp_count) || 0);
                map[key].totalJp += (parseInt(w.jp_count) || 0);
                map[key].classesCount++;
            }
            return Object.values(map);
        }
     }">

    {{-- TOP BAR ACTION & TITLE --}}
    <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="space-y-1">
                <a href="{{ route('admin.schedules.master.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Kembali ke Master Jadwal</span>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span>⚡ Smart Timetable Builder</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold font-sans">
                        Tahun Ajaran {{ $academicYear->full_label }}
                    </span>
                </h1>
                <p class="text-xs text-slate-500 max-w-2xl">
                    Wizard pembuatan jadwal KBM dari nol: tentukan durasi JP, atur kegiatan non-KBM, tetapkan beban JP guru, lalu generate otomatis tanpa bentrok.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.schedules.master.index') }}"
                   class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </a>
            </div>
        </div>
    </div>

    {{-- PROGRESS STEPPER 5 LANGKAH --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
            <button type="button" @click="step = 1"
                    class="p-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 border"
                    :class="step === 1 ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : (step > 1 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200')">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black"
                      :class="step === 1 ? 'bg-white text-emerald-800' : (step > 1 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600')">1</span>
                <span>1. Waktu Belajar</span>
            </button>

            <button type="button" @click="step = 2; recalculateGrid()"
                    class="p-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 border"
                    :class="step === 2 ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : (step > 2 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200')">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black"
                      :class="step === 2 ? 'bg-white text-emerald-800' : (step > 2 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600')">2</span>
                <span>2. Kegiatan Non-KBM</span>
            </button>

            <button type="button" @click="step = 3"
                    class="p-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 border"
                    :class="step === 3 ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : (step > 3 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200')">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black"
                      :class="step === 3 ? 'bg-white text-emerald-800' : (step > 3 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600')">3</span>
                <span>3. Beban Guru</span>
            </button>

            <button type="button" @click="step = 4"
                    class="p-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 border"
                    :class="step === 4 ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : (step > 4 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200')">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black"
                      :class="step === 4 ? 'bg-white text-emerald-800' : (step > 4 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600')">4</span>
                <span>4. Pilihan Metode</span>
            </button>

            <button type="button" @click="step = 5"
                    class="p-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 border col-span-2 sm:col-span-1"
                    :class="step === 5 ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-slate-50 text-slate-400 border-slate-200'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black"
                      :class="step === 5 ? 'bg-white text-emerald-800' : 'bg-slate-200 text-slate-600'">5</span>
                <span>5. Konfirmasi</span>
            </button>
        </div>
    </div>

    {{-- MAIN FORM MULTI-STEP --}}
    <form method="POST" action="{{ route('admin.schedules.master.builder.generate') }}" id="builderForm">
        @csrf

        {{-- Hidden JSON inputs untuk dikirim ke backend --}}
        <input type="hidden" name="time_config" :value="JSON.stringify({ jp_duration: jpDuration, start_time: startTime, days_count: daysCount, daily_jp: dailyJp, activities: activities })">
        <input type="hidden" name="selected_classes" :value="JSON.stringify(selectedClasses)">
        <input type="hidden" name="workloads" :value="JSON.stringify(workloads)">
        <input type="hidden" name="generation_mode" :value="generationMode">
        <input type="hidden" name="shift_mode" :value="shiftMode">

        {{-- ======================================================== --}}
        {{-- LANGKAH 1: STRUKTUR WAKTU BELAJAR                       --}}
        {{-- ======================================================== --}}
        <div x-show="step === 1" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 1: Tentukan Waktu Belajar (Durasi 1 JP & Jam per Hari)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Tentukan durasi satu jam pelajaran (JP), waktu dimulainya KBM di pagi hari, dan alokasi jumlah JP pada setiap hari belajar.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    {{-- 1. Durasi 1 JP --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-800">
                            ⏱️ Waktu 1 JP Berapa Menit?
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="20" max="60" x-model.number="jpDuration" @change="recalculateGrid()"
                                   class="w-24 rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-black text-center text-slate-900 bg-white focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-600">Menit / JP</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button type="button" @click="jpDuration = 35; recalculateGrid()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border"
                                    :class="jpDuration === 35 ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                35 Menit
                            </button>
                            <button type="button" @click="jpDuration = 40; recalculateGrid()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border"
                                    :class="jpDuration === 40 ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                40 Menit (Standar)
                            </button>
                            <button type="button" @click="jpDuration = 45; recalculateGrid()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border"
                                    :class="jpDuration === 45 ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                45 Menit
                            </button>
                        </div>
                    </div>

                    {{-- 2. Jam Mulai Belajar --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-800">
                            🌅 Jam Mulai Sekolah Pagi
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="text" placeholder="07:00" x-model="startTime" @change="recalculateGrid()"
                                   class="w-28 rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-black text-center text-slate-900 bg-white focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-600">WIB</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Waktu dimulainya kegiatan pertama (Upacara / Dhuha / JP 1).</p>
                    </div>

                    {{-- 3. Jumlah Hari Belajar --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-800">
                            📅 Jumlah Hari Belajar per Minggu
                        </label>
                        <select x-model.number="daysCount" @change="recalculateGrid()"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs font-bold bg-white focus:ring-emerald-500">
                            <option value="6">6 Hari Kerja (Senin s.d. Sabtu) — SMPN 2 Lemahabang</option>
                            <option value="5">5 Hari Kerja (Senin s.d. Jumat)</option>
                        </select>
                        <p class="text-[11px] text-slate-500">Menentukan apakah hari Sabtu aktif pembelajaran KBM.</p>
                    </div>
                </div>

                {{-- Konfigurasi JP per Hari --}}
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-extrabold text-slate-900">Alokasi Jumlah JP per Hari</h3>
                            <p class="text-[11px] text-slate-500">Tentukan berapa JP yang dijalankan pada masing-masing hari.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-600">Pintasan Cepat:</span>
                            <button type="button" @click="applyUniformJp(7)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-100">
                                7 JP Rata
                            </button>
                            <button type="button" @click="applyUniformJp(8)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-100">
                                8 JP Rata
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                        @php
                            $daysMap = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jum\'at', 6 => 'Sabtu'];
                        @endphp
                        @foreach($daysMap as $dNum => $dName)
                            <div class="p-3 rounded-xl bg-white border border-slate-200 text-center space-y-2"
                                 x-show="{{ $dNum }} <= daysCount">
                                <span class="text-xs font-extrabold text-slate-700 block">{{ $dName }}</span>
                                <input type="number" min="1" max="10" x-model.number="dailyJp[{{ $dNum }}]" @change="recalculateGrid()"
                                       class="w-16 rounded-lg border border-slate-300 py-1.5 text-center text-sm font-black text-slate-900">
                                <span class="text-[10px] text-slate-400 block">Jam Pelajaran</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <span class="text-xs text-slate-500">Langkah 1 dari 5</span>
                    <button type="button" @click="step = 2; recalculateGrid()"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs flex items-center gap-2">
                        <span>Lanjut ke Langkah 2: Kegiatan Non-KBM</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 2: KEGIATAN DI LUAR JP MENGAJAR & ISTIRAHAT      --}}
        {{-- ======================================================== --}}
        <div x-show="step === 2" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        <span>Langkah 2: Tentukan Kegiatan di Luar JP (Upacara, Dhuha, Istirahat) & Posisinya</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Slot kegiatan khusus akan otomatis terkunci di jadwal sehingga tidak akan tertimpa atau bentrok dengan pelajaran guru.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {{-- Form Daftar Kegiatan Non-KBM (Kiri 7 Col) --}}
                    <div class="lg:col-span-7 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Daftar Kegiatan & Istirahat</h3>
                            <button type="button" @click="addActivity()"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200 flex items-center gap-1.5 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>+ Tambah Kegiatan Baru</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(act, idx) in activities" :key="idx">
                                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3 relative group">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 flex-1">
                                            <span class="w-6 h-6 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-extrabold text-xs" x-text="idx + 1"></span>
                                            <input type="text" x-model="act.name" @change="recalculateGrid()"
                                                   placeholder="Nama kegiatan (e.g. ISTIRAHAT)"
                                                   class="flex-1 rounded-xl bg-white border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-900 focus:ring-emerald-500">
                                        </div>
                                        <button type="button" @click="removeActivity(idx)"
                                                class="text-slate-400 hover:text-rose-600 p-1" title="Hapus kegiatan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                                        {{-- Hari Berlakunya --}}
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Berlaku di Hari</label>
                                            <select x-model="act.day" @change="recalculateGrid()"
                                                    class="w-full rounded-lg bg-white border border-slate-300 px-2 py-1.5 text-xs font-semibold">
                                                <option value="all">Semua Hari (Senin-Sabtu)</option>
                                                <option value="1">Hari Senin</option>
                                                <option value="2">Hari Selasa</option>
                                                <option value="3">Hari Rabu</option>
                                                <option value="4">Hari Kamis</option>
                                                <option value="5">Hari Jum'at</option>
                                                <option value="6">Hari Sabtu</option>
                                            </select>
                                        </div>

                                        {{-- Posisi Jam --}}
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Ditempatkan di</label>
                                            <select x-model="act.position" @change="recalculateGrid()"
                                                    class="w-full rounded-lg bg-white border border-slate-300 px-2 py-1.5 text-xs font-semibold">
                                                <option value="before_jp_1">Sebelum JP 1 (Pagi Awal)</option>
                                                <option value="after_jp_2">Setelah JP 2</option>
                                                <option value="after_jp_3">Setelah JP 3</option>
                                                <option value="after_jp_4">Setelah JP 4 (Standar Istirahat)</option>
                                                <option value="after_jp_5">Setelah JP 5</option>
                                                <option value="after_jp_6">Setelah JP 6</option>
                                                <option value="after_jp_7">Setelah JP 7</option>
                                            </select>
                                        </div>

                                        {{-- Durasi --}}
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Durasi Kegiatan</label>
                                            <div class="flex items-center gap-1.5">
                                                <input type="number" min="10" max="90" x-model.number="act.duration" @change="recalculateGrid()"
                                                       class="w-full rounded-lg bg-white border border-slate-300 px-2 py-1.5 text-xs font-bold text-center">
                                                <span class="text-[11px] font-bold text-slate-500">Mnt</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Live Preview Timeline (Kanan 5 Col) --}}
                    <div class="lg:col-span-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Preview Timeline Jadwal</h3>
                            <select x-model="previewDay" class="rounded-lg border border-slate-300 text-xs font-bold py-1 px-2">
                                <template x-for="d in [1, 2, 3, 4, 5, 6].filter(x => x <= daysCount)" :key="d">
                                    <option :value="d" x-text="['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jum\'at', 'Sabtu'][d]"></option>
                                </template>
                            </select>
                        </div>

                        <div class="bg-slate-900 text-slate-200 rounded-2xl p-4 shadow-inner space-y-2 text-xs">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                                <span class="font-extrabold text-white" x-text="'Jadwal Hari ' + ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jum\'at', 'Sabtu'][previewDay]"></span>
                                <span class="text-[11px] text-emerald-400 font-bold" x-text="(gridPreview[previewDay]?.total_jp || 0) + ' JP KBM'"></span>
                            </div>

                            <div class="space-y-1.5 max-h-[360px] overflow-y-auto pr-1">
                                <template x-if="gridPreview[previewDay]">
                                    <template x-for="slot in gridPreview[previewDay].slots" :key="slot.time_range">
                                        <div class="p-2 rounded-xl flex items-center justify-between transition"
                                             :class="slot.is_activity ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800 text-slate-300'">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-black shrink-0"
                                                      :class="slot.is_activity ? 'bg-amber-500 text-slate-950' : 'bg-slate-700 text-white'"
                                                      x-text="slot.is_activity ? '★' : 'JP ' + slot.period_order"></span>
                                                <span class="font-bold text-[11px]" x-text="slot.is_activity ? slot.activity : 'Jam Pelajaran ' + slot.period_order"></span>
                                            </div>
                                            <span class="font-mono text-[11px] text-slate-400 font-semibold" x-text="slot.time_range"></span>
                                        </div>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="step = 1"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50">
                        &larr; Kembali ke Langkah 1
                    </button>
                    <button type="button" @click="step = 3"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs flex items-center gap-2">
                        <span>Lanjut ke Langkah 3: Beban Guru</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 3: BOBOT JP PER GURU (TEACHER WORKLOAD)          --}}
        {{-- ======================================================== --}}
        <div x-show="step === 3" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                        <span>Langkah 3: Tentukan Bobot JP per Guru & Mapel</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Contoh: <strong>Ahmad Nursyifa</strong> mengajar <em>Informatika</em> di Kelas 7A s.d. 7G (masing-masing 3 JP = 21 JP) &amp; <em>IPA</em> di 8A (4 JP).
                    </p>
                </div>

                {{-- Pilih Rombel Kelas Aktif --}}
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-800">
                            🏫 Rombel Kelas yang Dijadwalkan (<span x-text="selectedClasses.length"></span> Kelas Terpilih)
                        </label>
                        <button type="button" @click="toggleAllClasses()"
                                class="text-xs font-bold text-emerald-700 hover:underline">
                            <span x-text="selectedClasses.length === availableClasses.length ? 'Batal Pilih Semua' : 'Pilih Semua 21 Rombel'"></span>
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="cls in availableClasses" :key="cls">
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold cursor-pointer transition"
                                   :class="selectedClasses.includes(cls) ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-100'">
                                <input type="checkbox" :value="cls" x-model="selectedClasses" class="hidden">
                                <span x-text="'Kelas ' + cls"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Form Tambah Penugasan Guru --}}
                <div class="p-5 rounded-2xl bg-emerald-50/50 border border-emerald-200 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-extrabold text-emerald-950 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            <span>Input Penugasan Mengajar Guru</span>
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        {{-- Pilih Guru --}}
                        <div class="sm:col-span-4">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Pilih Guru</label>
                            <select x-model="wlTeacherId" @change="onSelectTeacher($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold bg-white focus:ring-emerald-500">
                                <option value="">-- Pilih Guru --</option>
                                <template x-for="t in teachersList" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Pilih Mapel --}}
                        <div class="sm:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Mata Pelajaran</label>
                            <select x-model="wlSubjectId" @change="onSelectSubject($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold bg-white focus:ring-emerald-500">
                                <option value="">-- Pilih Mapel --</option>
                                <template x-for="s in subjectsList" :key="s.id">
                                    <option :value="s.id" x-text="s.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Bobot JP per Kelas --}}
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Bobot JP / Kelas</label>
                            <div class="flex items-center gap-1">
                                <input type="number" min="1" max="10" x-model.number="wlJp"
                                       class="w-full rounded-xl border border-slate-300 px-2 py-2 text-xs font-black text-center bg-white focus:ring-emerald-500">
                                <span class="text-xs font-bold text-slate-600">JP</span>
                            </div>
                        </div>

                        {{-- Tombol Tambah --}}
                        <div class="sm:col-span-3">
                            <button type="button" @click="addWorkloadItem()"
                                    class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>+ Tambahkan Penugasan</span>
                            </button>
                        </div>
                    </div>

                    {{-- Pilih Rombel yang Diajar untuk Guru Tersebut --}}
                    <div class="pt-2 border-t border-emerald-200/60">
                        <div class="text-[11px] font-bold text-slate-700 mb-1.5">Centang Kelas Sasaran untuk Penugasan di Atas:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="cls in selectedClasses" :key="cls">
                                <label class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-semibold cursor-pointer transition"
                                       :class="wlClasses.includes(cls) ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'">
                                    <input type="checkbox" :value="cls" x-model="wlClasses" class="hidden">
                                    <span x-text="cls"></span>
                                </label>
                            </template>
                            <button type="button" @click="wlClasses = [...selectedClasses]"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold text-emerald-800 bg-emerald-100 hover:bg-emerald-200">
                                Pilih Semua Kelas
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Tabel Daftar Beban Guru yang Telah Ditambahkan --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Daftar Penugasan Jam Mengajar</h3>
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-bold" x-text="workloads.length + ' item penugasan'"></span>
                        </div>
                        <div class="text-xs font-bold text-emerald-800 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-200">
                            Total Jam Teralokasi: <strong class="text-emerald-950 font-black text-sm" x-text="getTotalJpAllocated()"></strong> JP
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2 text-center w-10">No</th>
                                    <th class="px-3 py-2">Nama Guru</th>
                                    <th class="px-3 py-2 text-center w-16">Kode</th>
                                    <th class="px-3 py-2">Mata Pelajaran</th>
                                    <th class="px-3 py-2 text-center w-20">Kelas</th>
                                    <th class="px-3 py-2 text-center w-20">Beban JP</th>
                                    <th class="px-3 py-2 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(w, idx) in workloads" :key="idx">
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-2 text-center font-bold text-slate-400" x-text="idx + 1"></td>
                                        <td class="px-3 py-2 font-bold text-slate-900" x-text="w.teacher_name"></td>
                                        <td class="px-3 py-2 text-center font-mono font-extrabold text-emerald-800" x-text="w.teacher_code"></td>
                                        <td class="px-3 py-2 text-slate-700" x-text="w.subject_name"></td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-extrabold text-[10px]" x-text="w.class_name"></span>
                                        </td>
                                        <td class="px-3 py-2 text-center font-black text-slate-900" x-text="w.jp_count + ' JP'"></td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" @click="removeWorkload(idx)"
                                                    class="text-slate-400 hover:text-rose-600 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="workloads.length === 0">
                                    <td colspan="7" class="px-4 py-6 text-center text-xs text-slate-400 italic">
                                        Belum ada beban mengajar yang ditambahkan. Gunakan form di atas untuk menentukan jam mengajar guru.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="step = 2"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50">
                        &larr; Kembali ke Langkah 2
                    </button>
                    <button type="button" @click="step = 4"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs flex items-center gap-2">
                        <span>Lanjut ke Langkah 4: Pilihan Metode</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 4: PILIHAN METODE PENJADWALAN                    --}}
        {{-- ======================================================== --}}
        <div x-show="step === 4" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <span>Langkah 4: Pilih Metode Pembuatan & Model Shift Belajar</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Pilih apakah sistem akan mengalokasikan jam secara otomatis tanpa bentrok atau membuat kerangka kosong untuk diisi manual.
                    </p>
                </div>

                {{-- Opsi Metode Pembuatan --}}
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-800">Pilihan Metode Pengisian:</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Opsi A: Generate Otomatis Anti-Bentrok --}}
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-3.5"
                               :class="generationMode === 'auto' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40' : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="gen_choice" value="auto" x-model="generationMode" class="mt-1 text-emerald-600">
                            <div class="space-y-1.5">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-extrabold text-[10px]">
                                    DIREKOMENDASIKAN
                                </div>
                                <h4 class="text-sm font-black text-slate-900">⚡ Generate Otomatis (Smart Timetable Scheduler)</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Sistem menggunakan algoritma anti-bentrok cerdas untuk memetakan penugasan guru ke kelas-kelas dalam bentuk blok jam berurutan (2–3 JP) dan menyebarkan jam secara proporsional.
                                </p>
                            </div>
                        </label>

                        {{-- Opsi B: Kerangka Kosong --}}
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-3.5"
                               :class="generationMode === 'manual' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40' : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="gen_choice" value="manual" x-model="generationMode" class="mt-1 text-emerald-600">
                            <div class="space-y-1.5">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-extrabold text-[10px]">
                                    PENGISIAN MANUAL
                                </div>
                                <h4 class="text-sm font-black text-slate-900">📝 Kerangka Kosong (Mulai dari Nol)</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Sistem hanya membuat tabel jam dan rombel kosong sesuai durasi JP dan waktu istirahat yang sudah ditentukan. Wakasek mengisi guru &amp; mapel per kotak jadwal secara manual.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Model Shift Belajar --}}
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <label class="block text-xs font-bold text-slate-800">Model Shift Belajar Sekolah:</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <label class="flex items-center gap-2.5 p-3 rounded-xl border bg-white cursor-pointer"
                               :class="shiftMode === 'single' ? 'border-emerald-500 ring-1 ring-emerald-300' : 'border-slate-200'">
                            <input type="radio" name="shift_choice" value="single" x-model="shiftMode" class="text-emerald-600">
                            <div>
                                <strong class="text-slate-900 block">1 Shift Reguler (Semua Kelas Pagi)</strong>
                                <span class="text-slate-500 text-[11px]">Kondisi normal sekolah, seluruh rombel 7, 8, dan 9 masuk pagi.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border bg-white cursor-pointer"
                               :class="shiftMode === 'double' ? 'border-emerald-500 ring-1 ring-emerald-300' : 'border-slate-200'">
                            <input type="radio" name="shift_choice" value="double" x-model="shiftMode" class="text-emerald-600">
                            <div>
                                <strong class="text-slate-900 block">2 Shift (Pagi: 7 &amp; 9, Siang: 8)</strong>
                                <span class="text-slate-500 text-[11px]">Selama masa renovasi / perbaikan ruang kelas SMPN 2 Lemahabang.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="step = 3"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50">
                        &larr; Kembali ke Langkah 3
                    </button>
                    <button type="button" @click="step = 5"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs flex items-center gap-2">
                        <span>Lanjut ke Langkah 5: Konfirmasi</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 5: KONFIRMASI, REVIEW & EKSEKUSI JADWAL          --}}
        {{-- ======================================================== --}}
        <div x-show="step === 5" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 5: Konfirmasi Ringkasan Jadwal &amp; Eksekusi Pembuatan</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Periksa kembali ringkasan konfigurasi sebelum sistem memproses dan membuat jadwal pelajaran sekolah.
                    </p>
                </div>

                {{-- Card Ringkasan Konfigurasi --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">WAKTU BELAJAR</span>
                        <div class="text-lg font-black text-slate-900 mt-1" x-text="jpDuration + ' Menit / JP'"></div>
                        <span class="text-xs text-slate-600 font-semibold" x-text="'Mulai ' + startTime + ' WIB &bull; ' + daysCount + ' Hari'"></span>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">KEGIATAN & ISTIRAHAT</span>
                        <div class="text-lg font-black text-slate-900 mt-1" x-text="activities.length + ' Kegiatan Khusus'"></div>
                        <span class="text-xs text-slate-600 font-semibold">Terkunci otomatis di jadwal</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">ROMBEL & BEBAN JP</span>
                        <div class="text-lg font-black text-slate-900 mt-1" x-text="selectedClasses.length + ' Rombel Kelas'"></div>
                        <span class="text-xs text-slate-600 font-semibold" x-text="getTotalJpAllocated() + ' JP terdaftar'"></span>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">METODE PEMBUATAN</span>
                        <div class="text-base font-black text-emerald-800 mt-1" x-text="generationMode === 'auto' ? '⚡ Generate Otomatis' : '📝 Kerangka Kosong'"></div>
                        <span class="text-xs text-slate-600 font-semibold" x-text="shiftMode === 'double' ? '2 Shift' : '1 Shift Reguler'"></span>
                    </div>
                </div>

                {{-- Alert Informasi Tindak Lanjut --}}
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-900 space-y-1.5">
                    <div class="font-extrabold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Fleksibilitas Pindah Jadwal &amp; Sinkronisasi Setelah Pembuatan:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-blue-800/90 pl-1">
                        <li>Setelah jadwal digenerate, Anda tetap bisa <strong>menukar sel jadwal (*swap slot*)</strong> atau mengubah jam jika ada permintaan khusus guru.</li>
                        <li>Panel deteksi bentrok (*collision detector*) akan selalu memantau jika ada jadwal guru yang bertabrakan.</li>
                        <li>Tombol <strong>"Sinkronkan ke Portal Guru"</strong> tersedia di halaman utama master jadwal untuk mendistribusikan jadwal resmi ke masing-masing akun guru &amp; jurnal harian.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="step = 4"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50">
                        &larr; Kembali ke Langkah 4
                    </button>
                    <button type="submit"
                            class="px-8 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-md transition flex items-center gap-2.5 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>🚀 Buat &amp; Terapkan Jadwal Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
@endsection
