<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return Auth::user()->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('schedules.index');
        }

        return view('auth.login', [
            'school' => SchoolSetting::current(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['login']);
        $throttleKey = 'login:' . mb_strtolower($identifier) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('login'))->withErrors([
                'login' => "Terlalu banyak percobaan login gagal. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $normalizedNip = preg_replace('/\s+/', '', $identifier);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('nip', $identifier)
            ->orWhereRaw("REPLACE(COALESCE(nip, ''), ' ', '') = ?", [$normalizedNip])
            ->first();

        $passwordMatches = $user && Hash::check($credentials['password'], $user->password);

        if (! $user || ! $passwordMatches) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withInput($request->only('login'))->withErrors([
                'login' => 'Email / NIP atau kata sandi tidak sesuai.',
            ]);
        }

        if (! $user->is_active) {
            return back()->withInput($request->only('login'))->withErrors([
                'login' => 'Akun Anda sedang dinonaktifkan. Hubungi Administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $user->isAdmin()
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->intended(route('schedules.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar dari sistem.');
    }

    public function switchAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
        ]);

        $year = AcademicYear::query()->findOrFail($validated['academic_year_id']);
        $request->session()->put('selected_academic_year_id', $year->id);

        return back()->with('success', "Periode tampilan diubah ke {$year->full_label}.");
    }

    public function editProfile(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'nip' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'default_subject' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'signature_data' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'clear_signature' => ['nullable', 'boolean'],
        ]);

        $signature = $user->signature_data;
        if ($request->boolean('clear_signature')) {
            $signature = null;
        } elseif ($request->hasFile('signature_file')) {
            $signature = $this->uploadedImageToDataUri($request->file('signature_file'), 'signature_file');
        } elseif ($request->filled('signature_data')) {
            $signature = $this->sanitizeRasterImageDataUri($validated['signature_data'] ?? null, 'signature_data');
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'default_subject' => $validated['default_subject'] ?? null,
            'signature_data' => $signature,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return back()->with('success', 'Profil & Tanda Tangan Digital berhasil diperbarui.');
    }
}
