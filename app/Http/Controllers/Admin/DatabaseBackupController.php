<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    /**
     * Menampilkan dashboard Backup & Restore Database.
     */
    public function index(DatabaseBackupService $service): View
    {
        $stats = $service->getDatabaseStats();
        $backups = $service->listBackups();

        return view('admin.backup.index', [
            'stats' => $stats,
            'backups' => $backups,
        ]);
    }

    /**
     * Membuat backup database baru secara instan.
     */
    public function store(DatabaseBackupService $service): RedirectResponse
    {
        try {
            $result = $service->createBackup('backup');

            return redirect()
                ->route('admin.backup.index')
                ->with('success', "Backup database berhasil dibuat: {$result['filename']} ({$result['size_formatted']})");
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backup.index')
                ->with('error', 'Gagal membuat backup database: ' . $e->getMessage());
        }
    }

    /**
     * Mengunduh file backup tertentu.
     */
    public function download(string $filename, DatabaseBackupService $service): BinaryFileResponse
    {
        $path = $service->getBackupPath($filename);

        if (! $path || ! file_exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return response()->download($path, basename($path), [
            'Content-Type' => str_ends_with(strtolower($path), '.gz') ? 'application/gzip' : 'application/sql',
        ]);
    }

    /**
     * Memulihkan database dari file server atau upload file baru.
     */
    public function restore(Request $request, DatabaseBackupService $service): RedirectResponse
    {
        $request->validate([
            'source' => ['required', 'in:server,upload'],
            'filename' => ['required_if:source,server', 'nullable', 'string'],
            'backup_file' => ['required_if:source,upload', 'nullable', 'file', 'max:25600'], // max 25MB
            'confirm_restore' => ['required', 'accepted'],
        ], [
            'confirm_restore.accepted' => 'Anda wajib mencentang persetujuan konfirmasi resiko sebelum memulihkan database.',
            'backup_file.required_if' => 'Silakan pilih file backup yang ingin di-upload.',
            'backup_file.max' => 'Ukuran file backup maksimal adalah 25 MB.',
        ]);

        try {
            $source = $request->input('source');
            $restorePath = null;

            if ($source === 'server') {
                $filename = (string) $request->input('filename');
                $restorePath = $service->getBackupPath($filename);

                if (! $restorePath) {
                    return back()->with('error', 'File backup di server tidak ditemukan.');
                }
            } else {
                $file = $request->file('backup_file');
                $ext = strtolower($file->getClientOriginalExtension());

                if (! in_array($ext, ['sql', 'gz'], true) && ! str_ends_with(strtolower($file->getClientOriginalName()), '.sql.gz')) {
                    return back()->with('error', 'Format file tidak didukung. File harus berformat .sql atau .sql.gz.');
                }

                $restorePath = $file->getRealPath();
            }

            $result = $service->restoreFromFile($restorePath);

            $msg = $result['message'];
            if (! empty($result['safety_backup'])) {
                $msg .= ". Sistem telah secara otomatis membuat cadangan darurat terkini: {$result['safety_backup']}";
            }

            return redirect()
                ->route('admin.backup.index')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memulihkan database: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus file backup tertentu dari storage server.
     */
    public function destroy(string $filename, DatabaseBackupService $service): RedirectResponse
    {
        $safeName = basename($filename);
        $deleted = $service->deleteBackup($safeName);

        if ($deleted) {
            return redirect()
                ->route('admin.backup.index')
                ->with('success', "File backup {$safeName} berhasil dihapus dari server.");
        }

        return redirect()
            ->route('admin.backup.index')
            ->with('error', "Gagal menghapus file backup {$safeName} atau file tidak ditemukan.");
    }
}
