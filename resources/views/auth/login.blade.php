<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Sistem Administrasi Guru</title>
    <!-- Favicon / Logo Tab Browser -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950">
    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800/20">
        {{-- Panel Branding Kiri --}}
        <div class="lg:col-span-5 bg-slate-900 text-white p-8 sm:p-10 flex flex-col justify-center relative overflow-hidden">
            @php
                $resolvedLeftLogo = $school?->resolvedLogoBox('left');
                $hasLogo = !empty($resolvedLeftLogo['data_uri']);
            @endphp
            <div>
                <div class="inline-flex items-center gap-2.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-[11px] font-bold mb-4">
                    <span>Platform Administrasi Akademik Guru</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug">
                    Sistem Administrasi Guru Terpadu
                </h1>
                <p class="mt-2 text-sm text-slate-300 font-medium">
                    {{ $school->school_name }}
                </p>
                <div class="mt-4 text-xs text-slate-400 leading-relaxed">
                    Portal terpadu untuk pencatatan Jurnal Harian KBM, Absensi, dan Pengelolaan Nilai Peserta Didik.
                </div>
            </div>
        </div>

        {{-- Form Login Kanan --}}
        <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-center">
            <h2 class="text-xl font-extrabold text-slate-900">Masuk ke Akun Anda</h2>
            <p class="text-xs text-slate-500 mt-1">Gunakan Email atau NIP yang terdaftar oleh Administrator.</p>

            @if(session('status'))
                <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 text-xs text-emerald-800 font-medium">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-2.5 text-xs text-rose-800 font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Email atau NIP</label>
                    <input type="text" name="login" value="{{ old('login') }}" required autofocus
                           placeholder="contoh: guru@sekolah.sch.id atau NIP"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Kata Sandi</label>
                    <input type="password" name="password" required
                           placeholder="Masukkan kata sandi"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Ingat sesi saya di perangkat ini</span>
                </label>

                <button type="submit"
                        class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition">
                    Masuk Sekarang
                </button>
            </form>
        </div>
    </div>
</body>
</html>
