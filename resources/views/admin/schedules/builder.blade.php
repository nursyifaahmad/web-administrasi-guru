@extends('layouts.app')

@section('title', 'Smart Timetable Builder — Buat Jadwal Baru dari Nol')
@section('page_title', 'Smart Timetable Builder')
@section('page_subtitle', 'Wizard penyusunan jadwal sekolah dari awal: model shift, konfigurasi waktu, kegiatan non-KBM, beban mengajar, generator anti-bentrok, dan sinkronisasi.')

@section('content')
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{
        step: 1,
        maxStep: 6,
        academicYearLabel: @js($academicYear ? $academicYear->full_label : '-'),

        // Step 1: Model Shift Belajar
        shiftMode: 'single', // 'single' | 'double'
        classesSiang: @js($defaultClassesSiang ?? ['8A', '8B', '8C', '8D', '8E', '8F', '8G']),

        // Step 2: Waktu Belajar
        jpDuration: {{ (int) $defaultConfig['jp_duration'] }},
        startTime: '{{ $defaultConfig['start_time'] }}',
        startTimeSiang: '{{ $defaultConfig['start_time_siang'] ?? '12:15' }}',
        daysCount: {{ (int) $defaultConfig['days_count'] }},
        dailyJp: {
            1: {{ (int) ($defaultConfig['daily_jp'][1] ?? 7) }},
            2: {{ (int) ($defaultConfig['daily_jp'][2] ?? 8) }},
            3: {{ (int) ($defaultConfig['daily_jp'][3] ?? 8) }},
            4: {{ (int) ($defaultConfig['daily_jp'][4] ?? 8) }},
            5: {{ (int) ($defaultConfig['daily_jp'][5] ?? 5) }},
            6: {{ (int) ($defaultConfig['daily_jp'][6] ?? 7) }},
        },

        // Step 3: Kegiatan Non-KBM
        activities: @js($defaultConfig['activities']),
        previewDay: 1,
        previewShiftTab: 'pagi', // 'pagi' | 'siang'
        gridPreview: @js($initialGrid),
        isCalculating: false,

        // Step 4: Rombel & Beban Guru
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

        // Step 5: Metode Penjadwalan
        generationMode: 'auto', // 'auto' | 'manual'

        init() {
            this.recalculateGrid();
        },

        // Helper: Toggle Kelas Siang/Pagi
        toggleShiftClass(cls) {
            let idx = this.classesSiang.indexOf(cls);
            if (idx >= 0) {
                this.classesSiang.splice(idx, 1);
            } else {
                this.classesSiang.push(cls);
            }
            this.classesSiang.sort();
        },

        isSiangClass(cls) {
            return this.shiftMode === 'double' && this.classesSiang.includes(cls);
        },

        setGradeShift(grade, toSiang) {
            this.availableClasses.filter(c => c.startsWith(grade)).forEach(c => {
                let idx = this.classesSiang.indexOf(c);
                if (toSiang && idx < 0) {
                    this.classesSiang.push(c);
                } else if (!toSiang && idx >= 0) {
                    this.classesSiang.splice(idx, 1);
                }
            });
            this.classesSiang.sort();
        },

        resetDefaultShiftClasses() {
            this.classesSiang = ['8A', '8B', '8C', '8D', '8E', '8F', '8G'];
        },

        // Helper: Terapkan JP seragam ke semua hari
        applyUniformJp(val) {
            val = parseInt(val) || 8;
            for (let d = 1; d <= 6; d++) {
                this.dailyJp[d] = (d === 5) ? Math.min(val, 5) : val;
            }
            this.recalculateGrid();
        },

        // Helper: Hitung jam selesai hari yang dipilih di preview
        getCurrentDayEndTime() {
            let day = this.gridPreview[this.previewDay];
            if (!day) return '';
            let slots = (this.shiftMode === 'double' && this.previewShiftTab === 'siang')
                ? (day.slots_siang || [])
                : (day.slots || []);
            if (slots.length === 0) return '';
            let lastSlot = slots[slots.length - 1];
            if (!lastSlot || !lastSlot.time_range) return '';
            let parts = lastSlot.time_range.split('-');
            return (parts[1] || '').trim();
        },

        // AJAX recalculate time grid
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
                        start_time_siang: this.startTimeSiang,
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

        selectOnlyPagiClasses() {
            if (this.shiftMode === 'double') {
                this.selectedClasses = this.availableClasses.filter(c => !this.classesSiang.includes(c));
            } else {
                this.selectedClasses = [...this.availableClasses];
            }
        },

        selectOnlySiangClasses() {
            if (this.shiftMode === 'double') {
                this.selectedClasses = this.availableClasses.filter(c => this.classesSiang.includes(c));
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

            this.wlClasses = [];
        },

        removeWorkload(idx) {
            this.workloads.splice(idx, 1);
        },

        getTotalJpAllocated() {
            return this.workloads.reduce((sum, w) => sum + (parseInt(w.jp_count) || 0), 0);
        },

        teacherSearchQuery: '',

        getTeacherSummary() {
            let map = {};
            for (let w of this.workloads) {
                let key = w.teacher_name;
                if (!map[key]) {
                    map[key] = {
                        user_id: w.user_id,
                        name: w.teacher_name,
                        code: w.teacher_code,
                        subjects: {},
                        classes: [],
                        pagiJp: 0,
                        siangJp: 0,
                        totalJp: 0,
                    };
                }
                let jp = parseInt(w.jp_count) || 0;
                if (!map[key].subjects[w.subject_name]) {
                    map[key].subjects[w.subject_name] = 0;
                }
                map[key].subjects[w.subject_name] += jp;
                map[key].totalJp += jp;

                if (!map[key].classes.includes(w.class_name)) {
                    map[key].classes.push(w.class_name);
                }

                if (this.isSiangClass(w.class_name)) {
                    map[key].siangJp += jp;
                } else {
                    map[key].pagiJp += jp;
                }
            }

            let list = Object.values(map).sort((a, b) => a.name.localeCompare(b.name));

            if (this.teacherSearchQuery && this.teacherSearchQuery.trim() !== '') {
                let q = this.teacherSearchQuery.toLowerCase().trim();
                list = list.filter(t => t.name.toLowerCase().includes(q) || (t.code && t.code.toLowerCase().includes(q)));
            }

            return list;
        },

        removeTeacherWorkloads(teacherName) {
            if (confirm('Hapus seluruh alokasi jam mengajar untuk guru ' + teacherName + '?')) {
                this.workloads = this.workloads.filter(w => w.teacher_name !== teacherName);
            }
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
                    Wizard penyusunan jadwal KBM sekolah dari awal: tentukan model shift, durasi JP &amp; jam belajar, kegiatan non-KBM, beban mengajar guru, lalu generate otomatis tanpa bentrok.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.schedules.master.index') }}"
                   class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                    Batal
                </a>
            </div>
        </div>
    </div>

    {{-- PROGRESS STEPPER 6 LANGKAH --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs space-y-3.5">
        {{-- Progress Header & Status --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider">
                    <span>⚡ Wizard</span>
                </span>
                <span class="text-xs font-bold text-slate-600">
                    Langkah <strong class="text-slate-900 font-black" x-text="step"></strong> dari 6:
                    <span class="text-emerald-700 font-extrabold"
                          x-text="['Model Shift Belajar', 'Waktu Belajar & Durasi JP', 'Kegiatan Non-KBM & Istirahat', 'Beban Jam Mengajar Guru', 'Pilihan Metode Penjadwalan', 'Konfirmasi & Terapkan Jadwal'][step - 1]"></span>
                </span>
            </div>
            <div class="flex items-center gap-2.5 text-xs text-slate-500 font-bold shrink-0">
                <div class="w-32 sm:w-40 bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200/60">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full transition-all duration-300"
                         :style="'width: ' + ((step / 6) * 100) + '%'"></div>
                </div>
                <span class="text-[11px] font-black text-slate-700" x-text="Math.round((step / 6) * 100) + '%'"></span>
            </div>
        </div>

        {{-- 6 Step Buttons Flow --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
            {{-- Step 1 --}}
            <button type="button" @click="step = 1"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 1
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : (step > 1
                            ? 'bg-white border-emerald-200 text-slate-800 hover:bg-emerald-50/50'
                            : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600')">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 1
                          ? 'bg-emerald-600 text-white shadow-xs'
                          : (step > 1
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-200 text-slate-600')">
                    <template x-if="step > 1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="step <= 1">
                        <span>1</span>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 1 ? 'text-emerald-700' : (step > 1 ? 'text-emerald-600' : 'text-slate-400')">Step 1</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 1 ? 'text-slate-900 font-extrabold' : (step > 1 ? 'text-slate-800 font-bold' : 'text-slate-500')">Model Shift</div>
                </div>
            </button>

            {{-- Step 2 --}}
            <button type="button" @click="step = 2; recalculateGrid()"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 2
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : (step > 2
                            ? 'bg-white border-emerald-200 text-slate-800 hover:bg-emerald-50/50'
                            : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600')">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 2
                          ? 'bg-emerald-600 text-white shadow-xs'
                          : (step > 2
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-200 text-slate-600')">
                    <template x-if="step > 2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="step <= 2">
                        <span>2</span>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 2 ? 'text-emerald-700' : (step > 2 ? 'text-emerald-600' : 'text-slate-400')">Step 2</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 2 ? 'text-slate-900 font-extrabold' : (step > 2 ? 'text-slate-800 font-bold' : 'text-slate-500')">Waktu Belajar</div>
                </div>
            </button>

            {{-- Step 3 --}}
            <button type="button" @click="step = 3; recalculateGrid()"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 3
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : (step > 3
                            ? 'bg-white border-emerald-200 text-slate-800 hover:bg-emerald-50/50'
                            : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600')">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 3
                          ? 'bg-emerald-600 text-white shadow-xs'
                          : (step > 3
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-200 text-slate-600')">
                    <template x-if="step > 3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="step <= 3">
                        <span>3</span>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 3 ? 'text-emerald-700' : (step > 3 ? 'text-emerald-600' : 'text-slate-400')">Step 3</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 3 ? 'text-slate-900 font-extrabold' : (step > 3 ? 'text-slate-800 font-bold' : 'text-slate-500')">Non-KBM</div>
                </div>
            </button>

            {{-- Step 4 --}}
            <button type="button" @click="step = 4"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 4
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : (step > 4
                            ? 'bg-white border-emerald-200 text-slate-800 hover:bg-emerald-50/50'
                            : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600')">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 4
                          ? 'bg-emerald-600 text-white shadow-xs'
                          : (step > 4
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-200 text-slate-600')">
                    <template x-if="step > 4">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="step <= 4">
                        <span>4</span>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 4 ? 'text-emerald-700' : (step > 4 ? 'text-emerald-600' : 'text-slate-400')">Step 4</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 4 ? 'text-slate-900 font-extrabold' : (step > 4 ? 'text-slate-800 font-bold' : 'text-slate-500')">Beban Guru</div>
                </div>
            </button>

            {{-- Step 5 --}}
            <button type="button" @click="step = 5"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 5
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : (step > 5
                            ? 'bg-white border-emerald-200 text-slate-800 hover:bg-emerald-50/50'
                            : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600')">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 5
                          ? 'bg-emerald-600 text-white shadow-xs'
                          : (step > 5
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-200 text-slate-600')">
                    <template x-if="step > 5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="step <= 5">
                        <span>5</span>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 5 ? 'text-emerald-700' : (step > 5 ? 'text-emerald-600' : 'text-slate-400')">Step 5</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 5 ? 'text-slate-900 font-extrabold' : (step > 5 ? 'text-slate-800 font-bold' : 'text-slate-500')">Metode</div>
                </div>
            </button>

            {{-- Step 6 --}}
            <button type="button" @click="step = 6"
                    class="p-2.5 rounded-xl border text-left transition-all duration-200 cursor-pointer flex items-center gap-2.5 group"
                    :class="step === 6
                        ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 shadow-xs'
                        : 'bg-slate-50/60 border-slate-200 text-slate-400 hover:bg-white hover:text-slate-600'">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition"
                      :class="step === 6 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-600'">
                    <span>6</span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider leading-none"
                         :class="step === 6 ? 'text-emerald-700' : 'text-slate-400'">Step 6</div>
                    <div class="text-xs font-bold truncate leading-tight mt-1"
                         :class="step === 6 ? 'text-slate-900 font-extrabold' : 'text-slate-500'">Konfirmasi</div>
                </div>
            </button>
        </div>
    </div>

    {{-- MAIN FORM MULTI-STEP --}}
    <form method="POST" action="{{ route('admin.schedules.master.builder.generate') }}" id="builderForm">
        @csrf

        {{-- Hidden JSON inputs untuk dikirim ke backend --}}
        <input type="hidden" name="time_config" :value="JSON.stringify({ jp_duration: jpDuration, start_time: startTime, start_time_siang: startTimeSiang, days_count: daysCount, daily_jp: dailyJp, activities: activities })">
        <input type="hidden" name="selected_classes" :value="JSON.stringify(selectedClasses)">
        <input type="hidden" name="classes_siang" :value="JSON.stringify(classesSiang)">
        <input type="hidden" name="workloads" :value="JSON.stringify(workloads)">
        <input type="hidden" name="generation_mode" :value="generationMode">
        <input type="hidden" name="shift_mode" :value="shiftMode">
        <input type="hidden" name="start_time_siang" :value="startTimeSiang">

        {{-- ======================================================== --}}
        {{-- LANGKAH 1: MODEL SHIFT BELAJAR & PEMBAGIAN ROMBEL       --}}
        {{-- ======================================================== --}}
        <div x-show="step === 1" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 1: Tentukan Model Shift Belajar Sekolah</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Pilih apakah proses KBM berlangsung dalam 1 shift (seluruh siswa masuk pagi) atau 2 shift (pagi &amp; siang). Anda dapat mengatur pembagian rombel pagi vs siang secara bebas.
                    </p>
                </div>

                {{-- Kartu Pilihan Shift --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Pilihan 1: 1 Shift Reguler --}}
                    <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-4"
                           :class="shiftMode === 'single' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40 shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
                        <input type="radio" name="shift_select" value="single" x-model="shiftMode" class="mt-1 text-emerald-600">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-extrabold text-[10px]">
                                KONDISI NORMAL SEKOLAH
                            </div>
                            <h3 class="text-sm font-black text-slate-900">🏢 1 Shift Reguler (Semua Kelas Pagi)</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Seluruh rombel siswa kelas VII, VIII, dan IX masuk serempak di pagi hari pada ruang kelas masing-masing. Hanya menggunakan 1 jadwal waktu KBM pagi.
                            </p>
                            <div class="pt-1 flex items-center gap-2 text-[11px] font-bold text-emerald-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>21 Rombel (7A s.d. 9G) Aktif Pagi</span>
                            </div>
                        </div>
                    </label>

                    {{-- Pilihan 2: 2 Shift Pagi & Siang --}}
                    <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-4"
                           :class="shiftMode === 'double' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40 shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
                        <input type="radio" name="shift_select" value="double" x-model="shiftMode" class="mt-1 text-emerald-600">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-extrabold text-[10px]">
                                RENOVASI / RUANG TERBATAS
                            </div>
                            <h3 class="text-sm font-black text-slate-900">🔄 2 Shift: Pagi &amp; Siang</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Sebagian rombel masuk pagi (Shift Pagi) dan sebagian rombel lainnya masuk siang (Shift Siang). Jam mulai dan timeline kedua shift dapat diatur secara terpisah.
                            </p>
                            <div class="pt-1 flex items-center gap-2 text-[11px] font-bold text-amber-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="classesSiang.length + ' Rombel Siang, ' + (availableClasses.length - classesSiang.length) + ' Rombel Pagi'"></span>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Panel Kustomisasi Pembagian Rombel (Hanya Tampil Jika 2 Shift) --}}
                <div x-show="shiftMode === 'double'" x-transition class="p-5 rounded-2xl bg-amber-50/60 border border-amber-200 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-amber-200/60 pb-3">
                        <div>
                            <h4 class="text-xs font-black text-slate-900 flex items-center gap-2">
                                <span>🔀 Atur Pembagian Rombel Pagi &amp; Siang (Bisa Diubah Bebas)</span>
                            </h4>
                            <p class="text-[11px] text-slate-600 mt-0.5">
                                Klik tombol kelas di bawah untuk memindahkan kelas antara <strong>🌅 Shift Pagi</strong> atau <strong>☀️ Shift Siang</strong>.
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[11px] font-bold text-slate-600">Pintasan Cepat:</span>
                            <button type="button" @click="resetDefaultShiftClasses()"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white border border-amber-300 text-amber-900 hover:bg-amber-100/60 transition cursor-pointer">
                                Standar (Kelas 8 Siang)
                            </button>
                            <button type="button" @click="setGradeShift('8', true); setGradeShift('9', true)"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white border border-amber-300 text-amber-900 hover:bg-amber-100/60 transition cursor-pointer">
                                8 &amp; 9 Siang
                            </button>
                            <button type="button" @click="classesSiang = []"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                                Kosongkan Siang
                            </button>
                        </div>
                    </div>

                    {{-- Grid Kelas dengan Toggle Pill --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach(['7' => 'Kelas VII', '8' => 'Kelas VIII', '9' => 'Kelas IX'] as $grade => $gradeLabel)
                            <div class="p-3.5 rounded-xl bg-white border border-amber-200/80 space-y-2.5 shadow-2xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-extrabold text-slate-800">{{ $gradeLabel }}</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="setGradeShift('{{ $grade }}', false)"
                                                class="text-[10px] font-bold text-emerald-700 hover:underline cursor-pointer">Semua Pagi</button>
                                        <span class="text-slate-300 text-[10px]">&bull;</span>
                                        <button type="button" @click="setGradeShift('{{ $grade }}', true)"
                                                class="text-[10px] font-bold text-amber-800 hover:underline cursor-pointer">Semua Siang</button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="c in availableClasses.filter(x => x.startsWith('{{ $grade }}'))" :key="c">
                                        <button type="button" @click="toggleShiftClass(c)"
                                                class="px-2.5 py-1 rounded-lg text-xs font-black transition flex items-center gap-1.5 border cursor-pointer"
                                                :class="classesSiang.includes(c)
                                                    ? 'bg-amber-500 text-white border-amber-600 shadow-2xs hover:bg-amber-600'
                                                    : 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100'">
                                            <span x-text="c"></span>
                                            <span class="text-[9px] uppercase px-1 py-0.2 rounded font-bold"
                                                  :class="classesSiang.includes(c) ? 'bg-amber-700/50 text-white' : 'bg-emerald-200/80 text-emerald-900'"
                                                  x-text="classesSiang.includes(c) ? 'Siang' : 'Pagi'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Ringkasan Rombel Per Shift --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 text-xs">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="font-bold text-slate-700">Shift Pagi:</span>
                                <span class="font-extrabold text-emerald-800" x-text="(availableClasses.length - classesSiang.length) + ' Rombel'"></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <span class="font-bold text-slate-700">Shift Siang:</span>
                                <span class="font-extrabold text-amber-800" x-text="classesSiang.length + ' Rombel'"></span>
                            </div>
                        </div>
                        <span class="text-[11px] text-slate-500 italic">*Setiap kelas otomatis dialokasikan ke jadwal waktu shift yang bersangkutan.</span>
                    </div>
                </div>

                {{-- Navigasi Bawah Langkah 1 --}}
                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <span class="text-xs text-slate-500">Langkah 1 dari 6</span>
                    <button type="button" @click="step = 2; recalculateGrid()"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs flex items-center gap-2 cursor-pointer transition">
                        <span>Lanjut ke Langkah 2: Waktu Belajar</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 2: STRUKTUR WAKTU BELAJAR                       --}}
        {{-- ======================================================== --}}
        <div x-show="step === 2" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 2: Tentukan Waktu Belajar (Durasi 1 JP &amp; Jam Mulai Sekolah)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Tentukan durasi satu jam pelajaran (JP), waktu dimulainya KBM (Pagi &amp; Siang), dan alokasi jumlah JP pada setiap hari belajar.
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
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border transition cursor-pointer"
                                    :class="jpDuration === 35 ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                35 Menit
                            </button>
                            <button type="button" @click="jpDuration = 40; recalculateGrid()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border transition cursor-pointer"
                                    :class="jpDuration === 40 ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                40 Menit (Standar)
                            </button>
                            <button type="button" @click="jpDuration = 45; recalculateGrid()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold border transition cursor-pointer"
                                    :class="jpDuration === 45 ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                45 Menit
                            </button>
                        </div>
                    </div>

                    {{-- 2. Jam Mulai Belajar Pagi --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-800 flex items-center justify-between">
                            <span>🌅 Jam Mulai Shift Pagi</span>
                            <span class="text-[10px] text-emerald-700 font-bold bg-emerald-100 px-2 py-0.5 rounded-full">Bisa diubah</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="text" placeholder="07:00" x-model="startTime" @change="recalculateGrid()"
                                   class="w-28 rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-black text-center text-slate-900 bg-white focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-600">WIB</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Waktu dimulainya KBM pagi (Upacara / Dhuha / JP 1).</p>
                    </div>

                    {{-- 3. Jam Mulai Belajar Siang (Hanya jika 2 Shift) atau Jumlah Hari Belajar --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3"
                         x-show="shiftMode === 'double'">
                        <label class="block text-xs font-bold text-amber-900 flex items-center justify-between">
                            <span>☀️ Jam Mulai Shift Siang</span>
                            <span class="text-[10px] text-amber-800 font-bold bg-amber-100 px-2 py-0.5 rounded-full">Bisa diubah</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="text" placeholder="12:15" x-model="startTimeSiang" @change="recalculateGrid()"
                                   class="w-28 rounded-xl border border-amber-300 px-3.5 py-2 text-sm font-black text-center text-amber-950 bg-white focus:ring-amber-500">
                            <span class="text-xs font-bold text-amber-800">WIB</span>
                        </div>
                        <div class="flex flex-wrap gap-1 pt-0.5">
                            <button type="button" @click="startTimeSiang = '12:00'; recalculateGrid()"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                    :class="startTimeSiang === '12:00' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                12:00
                            </button>
                            <button type="button" @click="startTimeSiang = '12:15'; recalculateGrid()"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                    :class="startTimeSiang === '12:15' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                12:15 (Default)
                            </button>
                            <button type="button" @click="startTimeSiang = '12:30'; recalculateGrid()"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                    :class="startTimeSiang === '12:30' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                12:30
                            </button>
                            <button type="button" @click="startTimeSiang = '13:00'; recalculateGrid()"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                    :class="startTimeSiang === '13:00' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'">
                                13:00
                            </button>
                        </div>
                    </div>

                    {{-- 4. Jumlah Hari Belajar --}}
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3"
                         :class="shiftMode === 'double' ? 'sm:col-span-3' : ''">
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
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 cursor-pointer transition">
                                7 JP Rata
                            </button>
                            <button type="button" @click="applyUniformJp(8)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 cursor-pointer transition">
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

                <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                    <button type="button" @click="step = 1"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Langkah 1</span>
                    </button>
                    <button type="button" @click="step = 3; recalculateGrid()"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition">
                        <span>Lanjut ke Langkah 3: Kegiatan Non-KBM</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 3: KEGIATAN DI LUAR JP MENGAJAR & ISTIRAHAT      --}}
        {{-- ======================================================== --}}
        <div x-show="step === 3" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        <span>Langkah 3: Tentukan Kegiatan di Luar JP (Upacara, Dhuha, Istirahat) &amp; Posisinya</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Slot kegiatan khusus akan otomatis terkunci di jadwal sehingga tidak akan tertimpa atau bentrok dengan jam pelajaran guru.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {{-- Form Daftar Kegiatan Non-KBM (Kiri, Luas dan Lega) --}}
                    <div class="lg:col-span-7 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-extrabold text-slate-900 flex items-center gap-2">
                                <span>📋 Daftar Kegiatan Terkunci:</span>
                                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-black"
                                      x-text="activities.length + ' Kegiatan'"></span>
                            </h3>
                            <button type="button" @click="addActivity()"
                                    class="px-3.5 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer shadow-2xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span>Tambah Kegiatan</span>
                            </button>
                        </div>

                        {{-- Card Kegiatan yang Responsif & Rapi --}}
                        <div class="space-y-3">
                            <template x-for="(act, idx) in activities" :key="idx">
                                <div class="p-4 rounded-2xl border border-slate-200 bg-white hover:border-slate-300 shadow-2xs space-y-3 transition">
                                    {{-- Baris 1: Nama Kegiatan & Tombol Hapus --}}
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shrink-0"></span>
                                            <input type="text" x-model="act.name" @input.debounce.300ms="recalculateGrid()"
                                                   placeholder="Nama Kegiatan (misal: UPACARA BENDERA, SHOLAT DHUHA, ISTIRAHAT)"
                                                   class="w-full rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-900 uppercase bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                                        </div>
                                        <button type="button" @click="removeActivity(idx)" title="Hapus Kegiatan"
                                                class="p-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>

                                    {{-- Baris 2: Detail Pengaturan (Hari, Posisi, Durasi) --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-slate-100 text-xs">
                                        <div>
                                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider block mb-1">Berlaku Hari</label>
                                            <select x-model="act.day" @change="recalculateGrid()"
                                                    class="w-full rounded-xl border border-slate-200 py-1.5 px-2.5 text-xs font-bold bg-slate-50 text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="all">Setiap Hari</option>
                                                <option value="1">Senin</option>
                                                <option value="2">Selasa</option>
                                                <option value="3">Rabu</option>
                                                <option value="4">Kamis</option>
                                                <option value="5">Jum'at</option>
                                                <option value="6">Sabtu</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider block mb-1">Posisi Jam</label>
                                            <select x-model="act.position" @change="recalculateGrid()"
                                                    class="w-full rounded-xl border border-slate-200 py-1.5 px-2.5 text-xs font-bold bg-slate-50 text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="before_jp_1">Sebelum JP 1</option>
                                                <option value="after_jp_1">Setelah JP 1</option>
                                                <option value="after_jp_2">Setelah JP 2</option>
                                                <option value="after_jp_3">Setelah JP 3</option>
                                                <option value="after_jp_4">Setelah JP 4</option>
                                                <option value="after_jp_5">Setelah JP 5</option>
                                                <option value="after_jp_6">Setelah JP 6</option>
                                                <option value="after_jp_7">Setelah JP 7</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider block mb-1">Durasi Waktu</label>
                                            <div class="flex items-center gap-1.5">
                                                <input type="number" min="5" max="120" x-model.number="act.duration" @input.debounce.300ms="recalculateGrid()"
                                                       class="w-full rounded-xl border border-slate-200 py-1.5 px-2.5 text-center text-xs font-black bg-slate-50 text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <span class="text-[11px] font-bold text-slate-500 shrink-0">Menit</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Live Preview Timeline Waktu (Kanan, Clean & Modern) --}}
                    <div class="lg:col-span-5 space-y-3 sticky top-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-extrabold text-slate-900 flex items-center gap-1.5">
                                <span>⏱️ Live Preview Timeline:</span>
                                <span x-show="isCalculating" class="inline-block animate-spin text-[10px] text-emerald-600">⌛</span>
                            </h3>
                            <div class="flex items-center gap-1">
                                <template x-for="d in daysCount" :key="d">
                                    <button type="button" @click="previewDay = d"
                                            class="w-7 h-7 rounded-lg text-[11px] font-extrabold transition cursor-pointer"
                                            :class="previewDay === d ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                            x-text="['Sen','Sel','Rab','Kam','Jum','Sab'][d-1]"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Tab Switcher Shift Pagi vs Siang (Jika 2 Shift) --}}
                        <div x-show="shiftMode === 'double'" class="flex items-center rounded-xl bg-slate-100 p-1 text-xs font-bold">
                            <button type="button" @click="previewShiftTab = 'pagi'"
                                    class="flex-1 py-1.5 rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer"
                                    :class="previewShiftTab === 'pagi' ? 'bg-white text-emerald-800 shadow-2xs font-extrabold' : 'text-slate-500 hover:text-slate-800'">
                                <span>🌅 Shift Pagi (<span x-text="startTime"></span>)</span>
                            </button>
                            <button type="button" @click="previewShiftTab = 'siang'"
                                    class="flex-1 py-1.5 rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer"
                                    :class="previewShiftTab === 'siang' ? 'bg-white text-amber-800 shadow-2xs font-extrabold' : 'text-slate-500 hover:text-slate-800'">
                                <span>☀️ Shift Siang (<span x-text="startTimeSiang"></span>)</span>
                            </button>
                        </div>

                        {{-- Tabel Timeline Preview --}}
                        <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white shadow-2xs">
                            <div class="px-4 py-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-slate-800" x-text="gridPreview[previewDay]?.day_name || 'Senin'"></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black"
                                          :class="shiftMode === 'double' && previewShiftTab === 'siang' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                                          x-text="shiftMode === 'double' && previewShiftTab === 'siang' ? 'Shift Siang' : 'Shift Pagi'"></span>
                                </div>
                                <span class="font-mono text-[11px] font-bold text-slate-600"
                                      x-text="shiftMode === 'double' && previewShiftTab === 'siang' ? 'Mulai ' + startTimeSiang + ' WIB' : 'Mulai ' + startTime + ' WIB'"></span>
                            </div>

                            <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                                {{-- Preview Pagi --}}
                                <template x-if="shiftMode === 'single' || previewShiftTab === 'pagi'">
                                    <div>
                                        <template x-for="(slot, sIdx) in (gridPreview[previewDay]?.slots || [])" :key="'pagi_' + sIdx">
                                            <div class="px-4 py-2.5 flex items-center justify-between text-xs transition"
                                                 :class="slot.is_activity ? 'bg-amber-50/60 text-amber-950 font-bold border-l-4 border-amber-400' : 'hover:bg-slate-50'">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-black shrink-0"
                                                          :class="slot.is_activity ? 'bg-amber-200 text-amber-900' : 'bg-slate-100 text-slate-700'"
                                                          x-text="slot.is_activity ? '★' : slot.period_order"></span>
                                                    <span class="truncate font-semibold"
                                                          :class="slot.is_activity ? 'text-amber-950' : 'text-slate-800'"
                                                          x-text="slot.is_activity ? slot.activity : ('Jam Pelajaran Ke-' + slot.period_order)"></span>
                                                </div>
                                                <span class="font-mono text-[11px] font-extrabold shrink-0"
                                                      :class="slot.is_activity ? 'text-amber-900' : 'text-slate-600'"
                                                      x-text="slot.time_range"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Preview Siang --}}
                                <template x-if="shiftMode === 'double' && previewShiftTab === 'siang'">
                                    <div>
                                        <template x-for="(slot, sIdx) in (gridPreview[previewDay]?.slots_siang || [])" :key="'siang_' + sIdx">
                                            <div class="px-4 py-2.5 flex items-center justify-between text-xs transition"
                                                 :class="slot.is_activity ? 'bg-amber-50/60 text-amber-950 font-bold border-l-4 border-amber-400' : 'hover:bg-slate-50'">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-black shrink-0"
                                                          :class="slot.is_activity ? 'bg-amber-200 text-amber-900' : 'bg-amber-100 text-amber-900'"
                                                          x-text="slot.is_activity ? '★' : slot.period_order"></span>
                                                    <span class="truncate font-semibold text-amber-950"
                                                          x-text="slot.is_activity ? slot.activity : ('Jam Pelajaran Ke-' + slot.period_order)"></span>
                                                </div>
                                                <span class="font-mono text-[11px] font-extrabold text-amber-900 shrink-0" x-text="slot.time_range"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Footer Ringkasan Timeline --}}
                            <div class="px-4 py-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
                                <span x-text="(gridPreview[previewDay]?.total_jp || 0) + ' Jam Pelajaran'"></span>
                                <span class="font-bold text-slate-700"
                                      x-text="'Selesai Pukul: ' + (getCurrentDayEndTime() || '-') + ' WIB'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                    <button type="button" @click="step = 2"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Langkah 2</span>
                    </button>
                    <button type="button" @click="step = 4"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition">
                        <span>Lanjut ke Langkah 4: Beban Guru</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 4: PEMETAAN ROMBEL & BEBAN MENGAJAR GURU        --}}
        {{-- ======================================================== --}}
        <div x-show="step === 4" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 4: Tentukan Beban Jam Pelajaran (JP) per Guru &amp; Rombel</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Tentukan penugasan guru, mata pelajaran yang diajar, rombel sasaran, dan alokasi JP per rombel (misal: Ahmad Nursyifa, Informatika = 3 JP di 7A, 3 JP di 7B).
                    </p>
                </div>

                {{-- FORM INPUT BEBAN GURU --}}
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                    <h3 class="text-xs font-extrabold text-slate-900 flex items-center gap-2">
                        <span>➕ Form Penugasan Guru ke Rombel</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5">
                        {{-- 1. Pilih Guru --}}
                        <div class="sm:col-span-4 space-y-1">
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-bold text-slate-700">1. Nama Guru Pengampu</label>
                                <span x-show="wlTeacherName" class="text-[10px] text-emerald-800 font-bold bg-emerald-100 px-2 py-0.2 rounded-full"
                                      x-text="'Alokasi: ' + (getTeacherSummary().find(x => x.name === wlTeacherName)?.totalJp || 0) + ' JP'"></span>
                            </div>
                            <select x-model="wlTeacherId" @change="onSelectTeacher($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 py-2 px-3 text-xs font-bold bg-white focus:ring-emerald-500">
                                <option value="">-- Pilih Guru --</option>
                                <template x-for="t in teachersList" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 2. Pilih Mata Pelajaran --}}
                        <div class="sm:col-span-4 space-y-1">
                            <label class="text-[11px] font-bold text-slate-700">2. Mata Pelajaran</label>
                            <select x-model="wlSubjectId" @change="onSelectSubject($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 py-2 px-3 text-xs font-bold bg-white focus:ring-emerald-500">
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <template x-for="s in subjectsList" :key="s.id">
                                    <option :value="s.id" x-text="s.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 3. Bobot JP per Kelas --}}
                        <div class="sm:col-span-4 space-y-1">
                            <label class="text-[11px] font-bold text-slate-700">3. Beban per Kelas (JP)</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" max="10" x-model.number="wlJp"
                                       class="w-20 rounded-xl border border-slate-300 py-2 text-center text-xs font-black bg-white">
                                <span class="text-xs font-bold text-slate-500">JP / Kelas</span>
                            </div>
                        </div>

                        {{-- 4. Pilih Rombel Sasaran dengan Badge Shift --}}
                        <div class="sm:col-span-12 space-y-2 pt-2 border-t border-slate-200">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <label class="text-[11px] font-bold text-slate-700">
                                    4. Pilih Rombel Sasaran Mengajar (Bisa pilih lebih dari satu):
                                </label>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" @click="wlClasses = [...availableClasses]"
                                            class="text-[10px] font-bold text-slate-600 hover:text-emerald-700 bg-white px-2 py-0.5 rounded border border-slate-200 cursor-pointer">
                                        Pilih Semua (21)
                                    </button>
                                    <button type="button" @click="wlClasses = availableClasses.filter(c => !classesSiang.includes(c))"
                                            x-show="shiftMode === 'double'"
                                            class="text-[10px] font-bold text-emerald-800 hover:text-emerald-900 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 cursor-pointer">
                                        Hanya Kelas Pagi
                                    </button>
                                    <button type="button" @click="wlClasses = availableClasses.filter(c => classesSiang.includes(c))"
                                            x-show="shiftMode === 'double'"
                                            class="text-[10px] font-bold text-amber-800 hover:text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 cursor-pointer">
                                        Hanya Kelas Siang
                                    </button>
                                    <button type="button" @click="wlClasses = []"
                                            class="text-[10px] font-bold text-slate-400 hover:text-slate-600 bg-white px-2 py-0.5 rounded border border-slate-200 cursor-pointer">
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 sm:grid-cols-7 gap-2">
                                <template x-for="c in availableClasses" :key="c">
                                    <label class="p-2 rounded-xl border flex items-center justify-between cursor-pointer transition text-xs font-extrabold"
                                           :class="wlClasses.includes(c) ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" :value="c" x-model="wlClasses" class="rounded text-emerald-600">
                                            <span x-text="c"></span>
                                        </div>
                                        <span class="text-[9px] px-1 py-0.2 rounded font-black shrink-0"
                                              :class="wlClasses.includes(c)
                                                  ? 'bg-white/20 text-white'
                                                  : (isSiangClass(c) ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900')"
                                              x-text="isSiangClass(c) ? '☀️ Siang' : '🌅 Pagi'"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" @click="addWorkloadItem()"
                                class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-xs flex items-center gap-2 cursor-pointer transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambahkan ke Matriks Beban</span>
                        </button>
                    </div>
                </div>

                {{-- TABEL MATRIKS BEBAN TERDAFTAR --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-extrabold text-slate-900 flex items-center gap-2">
                            <span>📋 Matriks Beban Mengajar Terdaftar</span>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black"
                                  x-text="workloads.length + ' Penugasan'"></span>
                        </h3>
                        <span class="text-xs font-black text-slate-900"
                              x-text="'Total Alokasi: ' + getTotalJpAllocated() + ' JP'"></span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2 text-center w-10">No</th>
                                    <th class="px-3 py-2">Nama Guru</th>
                                    <th class="px-3 py-2 text-center w-16">Kode</th>
                                    <th class="px-3 py-2">Mata Pelajaran</th>
                                    <th class="px-3 py-2 text-center w-28">Kelas &amp; Shift</th>
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
                                            <span class="px-2 py-0.5 rounded font-extrabold text-[10px]"
                                                  :class="isSiangClass(w.class_name) ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-900'"
                                                  x-text="w.class_name + (isSiangClass(w.class_name) ? ' (Siang)' : ' (Pagi)')"></span>
                                        </td>
                                        <td class="px-3 py-2 text-center font-black text-slate-900" x-text="w.jp_count + ' JP'"></td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" @click="removeWorkload(idx)"
                                                    class="text-slate-400 hover:text-rose-600 transition cursor-pointer">
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

                {{-- TABEL REKAPITULASI JUMLAH JP MASING-MASING GURU --}}
                <div class="space-y-3 pt-4 border-t border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-extrabold text-slate-900 flex items-center gap-2">
                                <span>📊 Rekapitulasi Beban Jam Mengajar per Guru</span>
                                <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-black"
                                      x-text="getTeacherSummary().length + ' Guru Terdaftar'"></span>
                            </h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Akumulasi total jam mengajar masing-masing guru beserta status pemenuhan beban minimal sertifikasi (24 JP).
                            </p>
                        </div>

                        {{-- Search Input Cepat Guru --}}
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input type="text" x-model="teacherSearchQuery" placeholder="Cari nama / kode guru..."
                                       class="w-56 rounded-xl border border-slate-200 pl-8 pr-3 py-1.5 text-xs font-bold bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-2xs">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2.5 text-center w-10">No</th>
                                    <th class="px-3 py-2.5">Nama Guru</th>
                                    <th class="px-3 py-2.5 text-center w-16">Kode</th>
                                    <th class="px-3 py-2.5">Mata Pelajaran &amp; JP</th>
                                    <th class="px-3 py-2.5">Rombel Sasaran</th>
                                    <template x-if="shiftMode === 'double'">
                                        <th class="px-3 py-2.5 text-center w-32">Rincian Shift</th>
                                    </template>
                                    <th class="px-3 py-2.5 text-center w-28">Total JP</th>
                                    <th class="px-3 py-2.5 text-center w-36">Status Beban (24 JP)</th>
                                    <th class="px-3 py-2.5 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="(t, tIdx) in getTeacherSummary()" :key="t.name">
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="px-3 py-2.5 text-center font-bold text-slate-400" x-text="tIdx + 1"></td>
                                        <td class="px-3 py-2.5 font-bold text-slate-900">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center shrink-0"
                                                     x-text="t.name.charAt(0).toUpperCase()"></div>
                                                <span x-text="t.name"></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-mono font-extrabold text-emerald-800" x-text="t.code || '-'"></td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="(jp, sName) in t.subjects" :key="sName">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-800 font-bold text-[10px]">
                                                        <span x-text="sName"></span>
                                                        <span class="text-emerald-700 font-extrabold" x-text="'(' + jp + ' JP)'"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex flex-wrap gap-1 items-center">
                                                <template x-for="c in t.classes" :key="c">
                                                    <span class="px-1.5 py-0.2 rounded font-extrabold text-[10px]"
                                                          :class="isSiangClass(c) ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-900'"
                                                          x-text="c"></span>
                                                </template>
                                                <span class="text-[10px] text-slate-400 font-bold ml-1" x-text="'(' + t.classes.length + ' Kelas)'"></span>
                                            </div>
                                        </td>
                                        <template x-if="shiftMode === 'double'">
                                            <td class="px-3 py-2.5 text-center text-[11px] font-bold">
                                                <span class="text-emerald-700" x-text="'🌅 ' + t.pagiJp + ' JP'"></span>
                                                <span class="text-slate-300 mx-1">|</span>
                                                <span class="text-amber-800" x-text="'☀️ ' + t.siangJp + ' JP'"></span>
                                            </td>
                                        </template>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="px-2.5 py-1 rounded-xl font-black text-xs inline-block"
                                                  :class="t.totalJp >= 24 ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-900'"
                                                  x-text="t.totalJp + ' JP'"></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span x-show="t.totalJp >= 24"
                                                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-[10px]">
                                                <span>✅ Terpenuhi</span>
                                                <span class="text-[9px] opacity-75">(≥ 24)</span>
                                            </span>
                                            <span x-show="t.totalJp < 24"
                                                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-extrabold text-[10px]">
                                                <span>⚠️ Kurang</span>
                                                <span class="text-[9px] opacity-75" x-text="'(-' + (24 - t.totalJp) + ' JP)'"></span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <button type="button" @click="removeTeacherWorkloads(t.name)" title="Hapus seluruh alokasi guru ini"
                                                    class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="getTeacherSummary().length === 0">
                                    <td colspan="9" class="px-4 py-6 text-center text-xs text-slate-400 italic">
                                        Belum ada guru yang dialokasikan jam mengajar.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot x-show="getTeacherSummary().length > 0" class="bg-slate-50 font-bold text-xs text-slate-700 border-t border-slate-200">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-slate-500 font-semibold"
                                        x-text="'Total Guru: ' + getTeacherSummary().length + ' Orang'"></td>
                                    <td colspan="2" class="px-3 py-2 text-right text-slate-500">
                                        Rata-rata: <span class="font-extrabold text-slate-800" x-text="Math.round(getTotalJpAllocated() / (getTeacherSummary().length || 1)) + ' JP / Guru'"></span>
                                    </td>
                                    <template x-if="shiftMode === 'double'">
                                        <td></td>
                                    </template>
                                    <td class="px-3 py-2 text-center font-black text-emerald-800"
                                        x-text="getTotalJpAllocated() + ' JP'"></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                    <button type="button" @click="step = 3"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Langkah 3</span>
                    </button>
                    <button type="button" @click="step = 5"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition">
                        <span>Lanjut ke Langkah 5: Pilihan Metode</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 5: PILIHAN METODE PENJADWALAN                    --}}
        {{-- ======================================================== --}}
        <div x-show="step === 5" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <span>Langkah 5: Pilih Metode Pembuatan Jadwal Pelajaran</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Pilih apakah sistem akan mengalokasikan jam secara otomatis tanpa bentrok atau membuat kerangka kosong untuk diisi manual oleh Wakasek Kurikulum.
                    </p>
                </div>

                {{-- Opsi Metode Pembuatan --}}
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-800">Pilihan Metode Pengisian:</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Opsi A: Generate Otomatis Anti-Bentrok --}}
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-3.5"
                               :class="generationMode === 'auto' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40 shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="gen_choice" value="auto" x-model="generationMode" class="mt-1 text-emerald-600">
                            <div class="space-y-1.5">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-extrabold text-[10px]">
                                    DIREKOMENDASIKAN
                                </div>
                                <h4 class="text-sm font-black text-slate-900">⚡ Generate Otomatis (Smart Timetable Scheduler)</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Sistem menggunakan algoritma anti-bentrok cerdas (CSP) untuk memetakan penugasan guru ke kelas-kelas dalam bentuk blok jam berurutan (2–3 JP) dan menyebarkan jam secara proporsional.
                                </p>
                                <p x-show="shiftMode === 'double'" class="text-[11px] text-emerald-800 font-bold bg-emerald-50 p-2.5 rounded-xl border border-emerald-200">
                                    ✨ <strong>Multi-Shift Aware:</strong> Guru yang mengajar di Shift Pagi JP 1 dan Shift Siang JP 1 tidak dihitung bentrok karena jam fisiknya berbeda.
                                </p>
                            </div>
                        </label>

                        {{-- Opsi B: Kerangka Kosong --}}
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition flex items-start gap-3.5"
                               :class="generationMode === 'manual' ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-300/40 shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
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

                <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                    <button type="button" @click="step = 4"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Langkah 4</span>
                    </button>
                    <button type="button" @click="step = 6"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition">
                        <span>Lanjut ke Langkah 6: Konfirmasi</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- LANGKAH 6: KONFIRMASI, REVIEW & EKSEKUSI JADWAL          --}}
        {{-- ======================================================== --}}
        <div x-show="step === 6" class="space-y-6" x-cloak>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xs space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Langkah 6: Konfirmasi Ringkasan Jadwal &amp; Eksekusi Pembuatan</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Periksa kembali ringkasan konfigurasi sebelum sistem memproses dan membuat jadwal pelajaran sekolah.
                    </p>
                </div>

                {{-- Card Ringkasan Konfigurasi Modern --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                    {{-- 1. Model Shift --}}
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 hover:border-emerald-300 hover:shadow-xs transition space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Model Shift</span>
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-sm">🏢</span>
                        </div>
                        <div>
                            <div class="text-sm font-black text-slate-900"
                                 x-text="shiftMode === 'double' ? '2 Shift (Pagi & Siang)' : '1 Shift Reguler'"></div>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5 truncate"
                               x-text="shiftMode === 'double' ? (classesSiang.length + ' Siang, ' + (availableClasses.length - classesSiang.length) + ' Pagi') : 'Semua 21 Rombel Pagi'"></p>
                        </div>
                    </div>

                    {{-- 2. Waktu Belajar --}}
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 hover:border-blue-300 hover:shadow-xs transition space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Waktu Belajar</span>
                            <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center text-sm">⏱️</span>
                        </div>
                        <div>
                            <div class="text-sm font-black text-slate-900" x-text="jpDuration + ' Menit / JP'"></div>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5 truncate"
                               x-text="shiftMode === 'double' ? ('Pagi ' + startTime + ' | Siang ' + startTimeSiang) : ('Mulai ' + startTime + ' WIB')"></p>
                        </div>
                    </div>

                    {{-- 3. Kegiatan & Istirahat --}}
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 hover:border-amber-300 hover:shadow-xs transition space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Kegiatan Khusus</span>
                            <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-sm">☕</span>
                        </div>
                        <div>
                            <div class="text-sm font-black text-slate-900" x-text="activities.length + ' Kegiatan Khusus'"></div>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5 truncate">Terkunci di jadwal</p>
                        </div>
                    </div>

                    {{-- 4. Rombel & Beban JP --}}
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 hover:border-purple-300 hover:shadow-xs transition space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Beban Guru</span>
                            <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-800 flex items-center justify-center text-sm">👥</span>
                        </div>
                        <div>
                            <div class="text-sm font-black text-slate-900" x-text="getTeacherSummary().length + ' Guru Terdaftar'"></div>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5 truncate" x-text="getTotalJpAllocated() + ' JP terdistribusi'"></p>
                        </div>
                    </div>

                    {{-- 5. Metode Pembuatan --}}
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 hover:border-teal-300 hover:shadow-xs transition space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Metode Penjadwalan</span>
                            <span class="w-7 h-7 rounded-lg bg-teal-100 text-teal-800 flex items-center justify-center text-sm">⚡</span>
                        </div>
                        <div>
                            <div class="text-sm font-black text-emerald-800" x-text="generationMode === 'auto' ? 'Generate Otomatis' : 'Kerangka Kosong'"></div>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5 truncate" x-text="generationMode === 'auto' ? 'Anti-Bentrok CSP' : 'Isi Manual'"></p>
                        </div>
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

                <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                    <button type="button" @click="step = 5"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Langkah 5</span>
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-black text-sm shadow-md hover:shadow-lg transition cursor-pointer">
                        <span class="text-base">🚀</span>
                        <span>Buat &amp; Terapkan Jadwal Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
@endsection
