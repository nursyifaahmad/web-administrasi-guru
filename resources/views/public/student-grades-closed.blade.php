<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses Nilai Murid Ditutup — {{ $school->school_name ?? 'Sistem Administrasi Guru' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-7 sm:p-9 text-center space-y-5">
        @php
            $appLogo = $school?->resolvedAppLogo();
            $logoSrc = $appLogo['url'] ?? ($appLogo['data_uri'] ?? null);
        @endphp

        @if(!empty($logoSrc))
            <div class="inline-flex p-2 rounded-2xl bg-slate-50 border border-slate-200 mx-auto">
                <img src="{{ $logoSrc }}" alt="Logo {{ $school->school_name }}" class="h-12 w-auto object-contain">
            </div>
        @endif

        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center text-3xl shadow-xs {{ ($reason ?? 'disabled') === 'not_found' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800' }}">
            {{ ($reason ?? 'disabled') === 'not_found' ? '🔗' : '🔒' }}
        </div>

        <div class="space-y-2">
            @if(($reason ?? 'disabled') === 'not_found')
                <h1 class="text-lg font-extrabold text-slate-900">Link Nilai Tidak Ditemukan</h1>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Link kelas yang Anda buka tidak terdaftar atau sudah diperbarui dengan link baru oleh guru pengampu. Silakan hubungi guru mata pelajaran Anda untuk mendapatkan link terbaru.
                </p>
            @else
                <h1 class="text-lg font-extrabold text-slate-900">Akses Lihat Nilai Ditutup Sementara</h1>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Sharing nilai murid untuk kelas
                    <strong>{{ $teachingClass?->class_name ?? '-' }} &mdash; {{ $teachingClass?->subject?->name ?? '-' }}</strong>
                    (Guru: <strong>{{ $teachingClass?->teacher?->name ?? '-' }}</strong>)
                    saat ini sedang dinonaktifkan oleh guru pengampu.
                </p>
            @endif
        </div>

        <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400">
            {{ $school->school_name ?? 'Sistem Administrasi Akademik Guru' }}
        </div>
    </div>
</body>
</html>
