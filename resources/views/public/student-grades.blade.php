<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>
        @if($report)
            Nilai {{ $teachingClass->class_name }} ({{ $teachingClass->subject?->name }}) — {{ $school->school_name }}
        @else
            Cek Nilai Mandiri Murid — {{ $teachingClass->class_name }}
        @endif
    </title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $antiSs = (bool) ($antiScreenshotEnabled ?? true);
    @endphp
    @if($report && $antiSs)
        <style>
            .anti-capture-surface {
                -webkit-user-select: none !important;
                -moz-user-select: none !important;
                -ms-user-select: none !important;
                user-select: none !important;
                -webkit-touch-callout: none !important;
                -webkit-user-drag: none !important;
            }
            .hold-reveal-btn {
                touch-action: none !important;
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                user-select: none !important;
            }
            @media print {
                .grade-protected-root {
                    display: none !important;
                }
                .print-blocked-notice {
                    display: flex !important;
                }
            }
        </style>
    @endif
</head>
<body class="min-h-screen font-sans antialiased bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 text-slate-900">
@php
    $appLogo = $school?->resolvedAppLogo();
    $logoSrc = $appLogo['url'] ?? ($appLogo['data_uri'] ?? null);
@endphp

@if(! $report)
    {{-- GERBANG VERIFIKASI NIS / NISN MURID --}}
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden">
            <div class="bg-slate-900 text-white p-6 sm:p-7 space-y-3">
                <div class="flex items-center gap-3">
                    @if(!empty($logoSrc))
                        <div class="p-1.5 rounded-xl bg-white/10 border border-white/15 shrink-0">
                            <img src="{{ $logoSrc }}" alt="Logo {{ $school->school_name }}" class="h-11 w-auto object-contain">
                        </div>
                    @endif
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold">
                            Portal Nilai Mandiri Murid
                        </span>
                        <h1 class="mt-1 text-base sm:text-lg font-extrabold tracking-tight truncate">
                            {{ $school->school_name ?? 'Sistem Administrasi Akademik Guru' }}
                        </h1>
                    </div>
                </div>

                <div class="pt-3 border-t border-white/10 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-300">
                    <div>
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 block">Kelas &amp; Mapel</span>
                        <strong class="text-white">Kelas {{ $teachingClass->class_name }} &mdash; {{ $teachingClass->subject?->name ?? '-' }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 block">Guru Pengampu</span>
                        <strong class="text-white">{{ $teachingClass->teacher?->name ?? '-' }}</strong>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 block">Tahun Ajaran / Semester</span>
                        <strong class="text-emerald-300">{{ $teachingClass->academicYear?->full_label ?? '-' }}</strong>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-7 space-y-4">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">Verifikasi Identitas Siswa</h2>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Masukkan <strong>NIS / NISN</strong> Anda untuk membuka rincian nilai pribadi Anda. Nilai teman sekelas tidak dapat diakses dari halaman ini.
                    </p>
                </div>

                @if(session('status'))
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 text-xs text-emerald-800 font-semibold">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-2.5 text-xs text-rose-800 font-semibold">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('student-grades.verify', $slug) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="nis_nisn_input" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nomor Induk Siswa / NISN *
                        </label>
                        <input id="nis_nisn_input"
                               type="text"
                               name="nis_nisn"
                               value="{{ old('nis_nisn') }}"
                               required
                               autofocus
                               autocomplete="off"
                               placeholder="Contoh: 0098120001"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                    </div>

                    <button type="submit"
                            class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-sm transition cursor-pointer">
                        Lihat Nilai Saya
                    </button>
                </form>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-[11px] text-slate-600 leading-relaxed flex items-start gap-2">
                    <span class="shrink-0">{{ $antiSs ? '🛡️' : '🔒' }}</span>
                    @if($antiSs)
                        <span>Halaman nilai dilindungi <strong>Mode Privasi Anti-Screenshot</strong> (nilai tertutup blur hingga ditahan, watermark identitas bergerak, &amp; penguncian otomatis bila berpindah layar).</span>
                    @else
                        <span>Anda hanya dapat melihat rincian nilai pribadi Anda sendiri melalui verifikasi <strong>NIS / NISN</strong> pada halaman ini.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    {{-- HALAMAN HASIL NILAI MURID TERVERIFIKASI DENGAN ANTI-SCREENSHOT SHIELD --}}
    @php
        $student = $report['student'];
        $weight = $report['weight'];
        $attRef = $report['attendance_ref'];
        $maskedNis = $student->nis_nisn ?: '-';
    @endphp

    @if($antiSs)
        {{-- Pesan saat user memaksa Print / Save PDF --}}
        <div class="print-blocked-notice hidden min-h-screen items-center justify-center p-8 text-center bg-white text-slate-900">
            <div class="max-w-md space-y-2 border-2 border-rose-300 rounded-2xl p-6">
                <h1 class="text-lg font-extrabold text-rose-700">Pencetakan &amp; Simpan PDF Dinonaktifkan</h1>
                <p class="text-xs text-slate-600">Demi privasi peserta didik, rincian nilai mandiri hanya dapat dilihat secara langsung pada portal dengan verifikasi NIS/NISN.</p>
            </div>
        </div>
    @endif

    <div class="{{ $antiSs ? 'grade-protected-root anti-capture-surface select-none' : '' }} relative min-h-screen py-5 px-3 sm:px-6 overflow-x-hidden"
         @if($antiSs)
         x-data="studentGradeShield({
            studentName: @js($student->full_name),
            nisNisn: @js($maskedNis),
            classLabel: @js('Kelas ' . $teachingClass->class_name . ' • ' . ($teachingClass->subject?->name ?? '')),
            verifiedAt: @js($verifiedAt ?: now()->format('d/m/Y H:i:s'))
         })"
         @endif>

        @if($antiSs)
            {{-- Lapisan Watermark Diagonal Dinamis (Selalu Ada & Mengikuti Waktu Real-Time) --}}
            <div class="fixed inset-0 z-40 pointer-events-none overflow-hidden select-none"
                 :class="isRevealed ? 'opacity-[0.16]' : 'opacity-[0.08]'"
                 aria-hidden="true">
                <div class="w-[140vw] h-[140vh] -ml-[20vw] -mt-[20vh] grid grid-cols-2 sm:grid-cols-3 gap-10 p-6 transition-transform duration-1000 ease-linear"
                     :style="`transform: rotate(-24deg) translate(${driftX}px, ${driftY}px)`">
                    @for($wm = 0; $wm < 15; $wm++)
                        <div class="flex flex-col items-center justify-center text-center text-slate-900 font-mono"
                             style="text-shadow: 0 0 1px rgba(255,255,255,0.65);">
                            <div class="text-xs sm:text-sm font-extrabold tracking-wide">{{ $student->full_name }}</div>
                            <div class="text-[11px] font-bold">NIS/NISN: {{ $maskedNis }}</div>
                            <div class="text-[10px]">{{ $teachingClass->class_name }} &bull; {{ $teachingClass->subject?->code ?: $teachingClass->subject?->name }}</div>
                            <div class="text-[10px] font-bold" x-text="watermarkClock"></div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Tirai Privasi Otomatis saat Fokus Layar Hilang / Pindah Aplikasi / Shortcut SS --}}
            <div x-show="curtainLocked"
                 x-cloak
                 class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-md flex items-center justify-center p-4 text-center text-white">
                <div class="max-w-sm w-full bg-slate-900 border border-slate-700 rounded-3xl p-6 space-y-4 shadow-2xl">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-500/20 border border-amber-400/40 text-amber-300 flex items-center justify-center text-2xl">
                        🔒
                    </div>
                    <div class="space-y-1.5">
                        <h2 class="text-base font-extrabold">Layar Nilai Dikunci Sementara</h2>
                        <p class="text-xs text-slate-300 leading-relaxed" x-text="curtainReason"></p>
                    </div>
                    <button type="button"
                            @click="unlockCurtain()"
                            class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer transition">
                        Saya Kembali ke Halaman Ini
                    </button>
                </div>
            </div>
        @endif

        <div class="relative z-10 max-w-4xl mx-auto space-y-4">
            {{-- Kartu Header Identitas Sekolah, Kelas & Siswa --}}
            <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
                <div class="bg-slate-900 text-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        @if(!empty($logoSrc))
                            <div class="p-1.5 rounded-xl bg-white/10 border border-white/15 shrink-0">
                                <img src="{{ $logoSrc }}" alt="Logo {{ $school->school_name }}" class="h-11 w-auto object-contain">
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-md bg-emerald-500 text-slate-950 font-black text-[10px]">
                                    KELAS {{ $teachingClass->class_name }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-white/10 text-emerald-200 font-bold text-[10px]">
                                    {{ $teachingClass->academicYear?->full_label ?? '-' }}
                                </span>
                            </div>
                            <h1 class="mt-1 text-base sm:text-lg font-extrabold truncate">
                                {{ $teachingClass->subject?->name ?? '-' }}
                            </h1>
                            <p class="text-xs text-slate-300 truncate">
                                Guru Pengampu: <strong class="text-white">{{ $teachingClass->teacher?->name ?? '-' }}</strong>
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('student-grades.logout', $slug) }}" class="shrink-0">
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto px-4 py-2 rounded-xl bg-white/10 hover:bg-rose-600 border border-white/20 text-white text-xs font-bold transition cursor-pointer">
                            Tutup &amp; Keluar
                        </button>
                    </form>
                </div>

                <div class="p-4 sm:p-5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-sm">
                            #{{ $report['no'] }}
                        </div>
                        <div>
                            <div class="font-extrabold text-sm text-slate-900">{{ $student->full_name }}</div>
                            <div class="text-[11px] text-slate-600 font-semibold">
                                NIS / NISN: <span class="font-mono text-slate-900">{{ $maskedNis }}</span>
                                &bull; L/P: <strong>{{ $student->gender }}</strong>
                            </div>
                        </div>
                    </div>
                    @if($antiSs)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] font-bold">
                            <span>🛡️</span>
                            <span>Anti-Screenshot &amp; Watermark Aktif</span>
                        </div>
                    @else
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-50 border border-sky-200 text-sky-900 text-[11px] font-bold">
                            <span>✓</span>
                            <span>Mode Tampilan Normal (Boleh Screenshot)</span>
                        </div>
                    @endif
                </div>
            </div>

            @if($antiSs)
                {{-- Panel Kontrol Buka Nilai (Hold-to-Reveal & Intip 8 Detik) --}}
                <div class="sticky top-2 z-30 bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-emerald-200 p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5 text-xs">
                        <div class="font-extrabold flex items-center gap-2"
                             :class="isRevealed ? 'text-emerald-700' : 'text-slate-800'">
                            <span class="w-2.5 h-2.5 rounded-full"
                                  :class="isRevealed ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                            <span x-text="isRevealed ? (peekSeconds > 0 ? `Nilai Terbuka Sementara (${peekSeconds} detik lagi terkunci)` : 'Nilai Terbuka — Lepas tombol untuk langsung menyembunyikan') : 'Angka Nilai Sedang Disembunyikan (Mode Privasi)'"></span>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Tekan &amp; tahan tombol biru/hijau dengan satu jari, atau gunakan <strong>Intip 8 Detik</strong>. Nilai otomatis buram kembali jika Anda melepas tombol atau berpindah aplikasi.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button type="button"
                                @pointerdown.prevent="startHold()"
                                @pointerup="stopHold()"
                                @pointercancel="stopHold()"
                                @mouseleave="stopHold()"
                                @touchend="stopHold()"
                                @touchcancel="stopHold()"
                                @contextmenu.prevent
                                class="hold-reveal-btn px-4 py-2.5 rounded-xl text-xs font-extrabold shadow-sm transition cursor-pointer flex-1 sm:flex-none text-center"
                                :class="holding ? 'bg-emerald-700 text-white ring-4 ring-emerald-300/60 scale-[0.99]' : 'bg-emerald-600 hover:bg-emerald-700 text-white'">
                            <span x-text="holding ? 'Sedang Dibuka (Lepas untuk Blur)' : 'Tekan & Tahan untuk Lihat Nilai'">Tekan &amp; Tahan untuk Lihat Nilai</span>
                        </button>

                        <button type="button"
                                @click="togglePeek()"
                                class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                                :class="peekSeconds > 0 ? 'bg-amber-500 text-white border-amber-500' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100'">
                            <span x-text="peekSeconds > 0 ? `Tutup (${peekSeconds}d)` : 'Intip 8 Detik'">Intip 8 Detik</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Kontainer Rincian Nilai --}}
            <div class="space-y-4 {{ $antiSs ? 'transition-all duration-150' : '' }}"
                 @if($antiSs) :class="isRevealed ? 'blur-none opacity-100' : 'blur-md opacity-60 pointer-events-none'" @endif>

                {{-- Ringkasan Nilai Akhir (NA), KKTP & Status Ketuntasan --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Nilai Akhir (NA)</div>
                        <div class="mt-1 text-3xl font-black {{ $report['is_tuntas'] ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $report['formatted_final_score'] }}
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">Pembulatan {{ $report['rounding_precision'] }} desimal</div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Standar KKM / KKTP</div>
                        <div class="mt-1 text-3xl font-black text-indigo-700">{{ $report['kktp'] }}</div>
                        <div class="mt-1 text-[11px] text-slate-500">Kriteria ketercapaian tujuan pembelajaran</div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex flex-col justify-center">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Status Ketuntasan</div>
                        <div class="mt-2">
                            @if($report['is_tuntas'])
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-100 text-emerald-900 border border-emerald-300 text-xs font-extrabold">
                                    ✓ Tuntas (Memenuhi KKTP)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-100 text-rose-900 border border-rose-300 text-xs font-extrabold">
                                    ! Perlu Remedial / Pendampingan
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Komposisi Bobot Kontrak Belajar --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm space-y-2.5">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-600">Komposisi Bobot Nilai Akhir</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
                        <div class="rounded-xl bg-sky-50 border border-sky-200 p-2.5">
                            <div class="text-[10px] font-bold text-sky-700">Rata-rata PH</div>
                            <div class="text-sm font-black text-sky-950">{{ (float) $weight->weight_ph }}%</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-2.5">
                            <div class="text-[10px] font-bold text-emerald-700">Kehadiran</div>
                            <div class="text-sm font-black text-emerald-950">{{ (float) $weight->weight_attendance }}%</div>
                        </div>
                        <div class="rounded-xl bg-purple-50 border border-purple-200 p-2.5">
                            <div class="text-[10px] font-bold text-purple-700">Rata-rata NT</div>
                            <div class="text-sm font-black text-purple-950">{{ (float) $weight->weight_nt }}%</div>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-2.5">
                            <div class="text-[10px] font-bold text-amber-700">Nilai STS</div>
                            <div class="text-sm font-black text-amber-950">{{ (float) $weight->weight_sts }}%</div>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-2.5 col-span-2 sm:col-span-1">
                            <div class="text-[10px] font-bold text-amber-700">Nilai SAS</div>
                            <div class="text-sm font-black text-amber-950">{{ (float) $weight->weight_sas }}%</div>
                        </div>
                    </div>
                </div>

                {{-- Rincian Penilaian Harian (PH) & Nilai Tugas (NT) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Penilaian Harian (PH) --}}
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                        <div class="px-4 py-3 bg-sky-50 border-b border-sky-200 flex items-center justify-between">
                            <h2 class="text-xs font-extrabold text-sky-950 uppercase">Penilaian Harian (PH)</h2>
                            <span class="px-2.5 py-0.5 rounded-full bg-white border border-sky-200 text-[11px] font-extrabold text-sky-800">
                                Rata-rata: {{ number_format((float) $report['avg_ph'], 2) }}
                            </span>
                        </div>
                        <div class="divide-y divide-slate-100 text-xs">
                            @forelse($report['ph_items'] as $item)
                                <div class="px-4 py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900">{{ $item['label'] }}</div>
                                        @if(!empty($item['topic_note']) || !empty($item['assessment_date_label']))
                                            <div class="text-[11px] text-slate-500 truncate">
                                                {{ $item['topic_note'] ?: 'Penilaian Harian' }}
                                                @if(!empty($item['assessment_date_label']))
                                                    &bull; {{ $item['assessment_date_label'] }}
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="shrink-0">
                                        @if($item['score'] !== null)
                                            <span class="px-3 py-1 rounded-lg bg-slate-100 font-extrabold text-slate-900">{{ $item['score'] }}</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-bold text-amber-800">Belum Dinilai</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-center text-slate-400">Belum ada kolom Penilaian Harian.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Nilai Tugas (NT) --}}
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                        <div class="px-4 py-3 bg-purple-50 border-b border-purple-200 flex items-center justify-between">
                            <h2 class="text-xs font-extrabold text-purple-950 uppercase">Nilai Tugas (NT)</h2>
                            <span class="px-2.5 py-0.5 rounded-full bg-white border border-purple-200 text-[11px] font-extrabold text-purple-800">
                                Rata-rata: {{ number_format((float) $report['avg_nt'], 2) }}
                            </span>
                        </div>
                        <div class="divide-y divide-slate-100 text-xs">
                            @forelse($report['nt_items'] as $item)
                                <div class="px-4 py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900">{{ $item['label'] }}</div>
                                        @if(!empty($item['topic_note']))
                                            <div class="text-[11px] text-slate-500 truncate">{{ $item['topic_note'] }}</div>
                                        @endif
                                    </div>
                                    <div class="shrink-0">
                                        @if($item['score'] !== null)
                                            <span class="px-3 py-1 rounded-lg bg-slate-100 font-extrabold text-slate-900">{{ $item['score'] }}</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-bold text-amber-800">Belum Dinilai</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-center text-slate-400">Belum ada kolom Tugas / NT.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Rincian Kehadiran, STS, dan SAS --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="rounded-xl bg-emerald-50/70 border border-emerald-200 p-3.5 space-y-1">
                        <div class="font-bold text-emerald-900">Nilai Kehadiran ({{ $report['is_auto_attendance'] ? 'Otomatis' : 'Manual' }})</div>
                        <div class="text-2xl font-black text-emerald-800">
                            {{ $report['attendance_score'] !== null ? $report['attendance_score'] : '-' }}
                        </div>
                        @if(($attRef['total'] ?? 0) > 0)
                            <div class="text-[11px] text-emerald-800 font-semibold">
                                H: {{ $attRef['present'] }} (Dispen: {{ $attRef['dispen'] }}) &bull;
                                S: {{ $attRef['sakit'] }} &bull;
                                I: {{ $attRef['izin'] }} &bull;
                                A: {{ $attRef['alfa'] }}
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl bg-amber-50/70 border border-amber-200 p-3.5 space-y-1">
                        <div class="font-bold text-amber-900">Sumatif Tengah Semester (STS)</div>
                        <div class="text-2xl font-black text-amber-800">
                            {{ $report['sts_score'] !== null ? $report['sts_score'] : '-' }}
                        </div>
                        <div class="text-[11px] text-amber-700">Bobot {{ (float) $weight->weight_sts }}%</div>
                    </div>

                    <div class="rounded-xl bg-amber-50/70 border border-amber-200 p-3.5 space-y-1">
                        <div class="font-bold text-amber-900">Sumatif Akhir Semester (SAS)</div>
                        <div class="text-2xl font-black text-amber-800">
                            {{ $report['sas_score'] !== null ? $report['sas_score'] : '-' }}
                        </div>
                        <div class="text-[11px] text-amber-700">Bobot {{ (float) $weight->weight_sas }}%</div>
                    </div>
                </div>
            </div>

            {{-- Footer Privasi --}}
            <div class="text-center text-[11px] text-slate-300/80 px-2 pb-4 leading-relaxed">
                Halaman ini menampilkan data nilai khusus milik <strong>{{ $student->full_name }}</strong> ({{ $maskedNis }}).
                @if($antiSs)
                    Dilarang mengambil atau menyebarkan tangkapan layar tanpa izin guru pengampu.
                @endif
            </div>
        </div>
    </div>

    @if($antiSs)
        <script>
            function studentGradeShield(cfg) {
            return {
                holding: false,
                peekSeconds: 0,
                _peekTimer: null,
                _clockTimer: null,
                _driftTimer: null,
                curtainLocked: false,
                curtainReason: '',
                watermarkClock: cfg.verifiedAt || '',
                driftX: 0,
                driftY: 0,
                _lastWidth: window.innerWidth,

                get isRevealed() {
                    return !this.curtainLocked && (this.holding || this.peekSeconds > 0);
                },

                init() {
                    this.updateClock();
                    this._clockTimer = setInterval(() => this.updateClock(), 1000);
                    this._driftTimer = setInterval(() => {
                        this.driftX = (this.driftX + 18) % 54;
                        this.driftY = (this.driftY + 14) % 42;
                    }, 3000);

                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            this.lockWithCurtain('Halaman disembunyikan atau Anda berpindah aplikasi/tab.');
                        }
                    });

                    window.addEventListener('blur', () => {
                        this.lockWithCurtain('Fokus layar berpindah keluar dari halaman nilai.');
                    });

                    window.addEventListener('pagehide', () => {
                        this.stopAllReveal();
                    });

                    window.addEventListener('resize', () => {
                        const widthDiff = Math.abs(window.innerWidth - this._lastWidth);
                        this._lastWidth = window.innerWidth;
                        if (widthDiff > 120 && this.isRevealed) {
                            this.lockWithCurtain('Perubahan ukuran layar / split-screen terdeteksi saat nilai terbuka.');
                        }
                    });

                    ['contextmenu', 'selectstart', 'copy', 'cut', 'dragstart'].forEach((evt) => {
                        document.addEventListener(evt, (e) => {
                            e.preventDefault();
                        });
                    });

                    const guardKey = (e) => {
                        const key = (e.key || '').toLowerCase();
                        const isPrintScreen = e.key === 'PrintScreen' || e.code === 'PrintScreen' || e.keyCode === 44;
                        const isModifierCombo = (e.ctrlKey || e.metaKey) && ['p', 's', 'c', 'u', 'a'].includes(key);
                        const isDevTools = key === 'f12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && ['i', 'j', 'c'].includes(key));
                        const isMacScreenshot = e.metaKey && e.shiftKey && ['3', '4', '5'].includes(key);

                        if (isPrintScreen || isModifierCombo || isDevTools || isMacScreenshot) {
                            e.preventDefault();
                            if (isPrintScreen && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                                navigator.clipboard.writeText('Penyalinan & tangkapan layar nilai dinonaktifkan.').catch(() => {});
                            }
                            this.lockWithCurtain('Percobaan cetak, salin halaman, atau tangkapan layar terdeteksi dan diblokir.');
                        }
                    };

                    window.addEventListener('keydown', guardKey);
                    window.addEventListener('keyup', (e) => {
                        if (e.key === 'PrintScreen' || e.code === 'PrintScreen' || e.keyCode === 44) {
                            guardKey(e);
                        }
                    });
                },

                updateClock() {
                    const d = new Date();
                    const pad = (n) => String(n).padStart(2, '0');
                    this.watermarkClock = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                },

                startHold() {
                    if (this.curtainLocked) return;
                    this.holding = true;
                },

                stopHold() {
                    this.holding = false;
                },

                togglePeek() {
                    if (this.curtainLocked) return;
                    if (this.peekSeconds > 0) {
                        this.stopPeek();
                        return;
                    }
                    this.peekSeconds = 8;
                    clearInterval(this._peekTimer);
                    this._peekTimer = setInterval(() => {
                        if (this.peekSeconds <= 1) {
                            this.stopPeek();
                        } else {
                            this.peekSeconds--;
                        }
                    }, 1000);
                },

                stopPeek() {
                    clearInterval(this._peekTimer);
                    this._peekTimer = null;
                    this.peekSeconds = 0;
                },

                stopAllReveal() {
                    this.holding = false;
                    this.stopPeek();
                },

                lockWithCurtain(reason) {
                    this.stopAllReveal();
                    this.curtainReason = reason || 'Demi keamanan privasi, tampilan nilai dikunci otomatis.';
                    this.curtainLocked = true;
                },

                    unlockCurtain() {
                        this.stopAllReveal();
                        this.curtainLocked = false;
                        this.curtainReason = '';
                    }
                };
            }
        </script>
    @endif
@endif
</body>
</html>
