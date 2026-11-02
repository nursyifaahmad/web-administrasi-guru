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
        <div class="lg:col-span-6 bg-slate-900 text-white p-8 sm:p-10 flex flex-col justify-between relative overflow-hidden">
            <div>
                <div class="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-bold mb-6">
                    <span>Platform Administrasi Akademik Guru</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug">
                    Sistem Administrasi Guru Terpadu
                </h1>
                <p class="mt-2 text-sm text-slate-300 font-medium">
                    {{ $school->school_name }}
                </p>

                <div class="mt-8 space-y-3.5 text-xs text-slate-300">
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shrink-0">1</span>
                        <div>
                            <strong class="text-white">Jurnal Harian Otomatis</strong> — Tersinkronisasi langsung dengan jumlah kehadiran murid di menu Absensi & mendukung Paraf Digital.
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shrink-0">2</span>
                        <div>
                            <strong class="text-white">Matriks Absensi Bulanan & Semester</strong> — Input cepat kode <code class="text-emerald-300">. / s / i / a / d</code> (Dispensasi tetap dihitung Hadir).
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shrink-0">3</span>
                        <div>
                            <strong class="text-white">Daftar Nilai Dinamis & Multi-Kelas (15+ Kelas)</strong> — Kolom PH & NT dinamis, rumus kontrak belajar fleksibel, standar KKTP Admin, serta Cetak PDF & Excel resmi ber-NIP.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-slate-800 text-[11px] text-slate-400">
                Mendukung pencetakan resmi ukuran F4 (Folio) & A4 lengkap dengan Titimangsa, Wali Kelas, Guru Mapel, dan Kepala Sekolah.
            </div>
        </div>

        {{-- Form Login Kanan --}}
        <div class="lg:col-span-6 p-8 sm:p-10 flex flex-col justify-center">
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

            <div class="mt-6 rounded-xl bg-slate-50 border border-slate-200 p-3.5 text-[11px] text-slate-600 space-y-1">
                <div class="font-bold text-slate-800">Akun Demo Awal:</div>
                <div>&bull; <strong>Admin:</strong> <code>admin@sekolah.sch.id</code> | Sandi: <code>password</code></div>
                <div>&bull; <strong>Guru (5 Kelas):</strong> <code>guru@sekolah.sch.id</code> | Sandi: <code>password</code></div>
            </div>
        </div>
    </div>
</body>
</html>
