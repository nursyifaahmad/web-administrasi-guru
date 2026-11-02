<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

abstract class Controller
{
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

    protected function uploadedImageToDataUri(?UploadedFile $file): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $mime = $file->getMimeType() ?: 'image/png';
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
