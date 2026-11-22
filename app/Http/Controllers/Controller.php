<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    protected const ALLOWED_RASTER_IMAGE_MIMES = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    protected function authorizeClassAccess(User $user, TeachingClass $teachingClass): void
    {
        if (! $user->isAdmin() && (int) $teachingClass->teacher_id !== (int) $user->id) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses kelas administrasi ini.');
        }
    }

    protected function resolveAcademicYear(Request $request): AcademicYear
    {
        $fromAttr = $request->attributes->get('selectedAcademicYear');
        if ($fromAttr instanceof AcademicYear) {
            return $fromAttr;
        }

        $sessionId = $request->session()->get('selected_academic_year_id');
        if ($sessionId) {
            $found = AcademicYear::query()->find($sessionId);
            if ($found) {
                return $found;
            }
        }

        return AcademicYear::active();
    }

    protected function sanitizeRasterImageDataUri(?string $raw, string $field = 'signature_data'): ?string
    {
        $clean = trim((string) ($raw ?? ''));
        if ($clean === '') {
            return null;
        }

        $compact = preg_replace('/\s+/', '', $clean) ?? '';
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,[A-Za-z0-9+\/=]+$/i', $compact)) {
            throw ValidationException::withMessages([
                $field => 'Format gambar tidak diizinkan. Gunakan hanya gambar raster PNG, JPEG, atau WEBP.',
            ]);
        }

        return $compact;
    }

    protected function uploadedImageToDataUri(?UploadedFile $file, string $field = 'image'): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! in_array($mime, self::ALLOWED_RASTER_IMAGE_MIMES, true)) {
            throw ValidationException::withMessages([
                $field => 'Format berkas gambar tidak diizinkan. Hanya file PNG, JPEG, atau WEBP yang diterima.',
            ]);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
