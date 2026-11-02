<?php

namespace App\Services;

use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseBackupService
{
    /**
     * Direktori penyimpanan file backup.
     */
    public function getStorageDir(): string
    {
        $dir = storage_path('app/backups');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        return $dir;
    }

    /**
     * Membuat backup database ke file .sql atau .sql.gz.
     */
    public function createBackup(?string $prefix = 'backup'): array
    {
        $storageDir = $this->getStorageDir();
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $rawDb = (string) config("database.connections.{$connection->getName()}.database", 'database');
        $safeDb = preg_replace('/[^a-zA-Z0-9_\-]/', '_', basename($rawDb)) ?: 'db';

        $baseFilename = "{$prefix}_{$safeDb}_{$timestamp}.sql";
        $tempSqlPath = $storageDir . DIRECTORY_SEPARATOR . $baseFilename;

        $fp = fopen($tempSqlPath, 'w');
        if (! $fp) {
            throw new RuntimeException("Gagal membuka file backup untuk penulisan: {$tempSqlPath}");
        }

        try {
            if ($driver === 'sqlite') {
                $this->dumpSqlite($fp, $connection, $pdo);
            } else {
                $this->dumpMysql($fp, $connection, $pdo, $rawDb);
            }
        } finally {
            fclose($fp);
        }

        // Kompresi ke format Gzip (.sql.gz) jika ekstensi zlib tersedia
        $finalPath = $tempSqlPath;
        $finalFilename = $baseFilename;

        if (function_exists('gzencode')) {
            $gzPath = $tempSqlPath . '.gz';
            $sqlContent = file_get_contents($tempSqlPath);
            $gzipped = gzencode($sqlContent, 9);

            if ($gzipped !== false) {
                file_put_contents($gzPath, $gzipped);
                @unlink($tempSqlPath);
                $finalPath = $gzPath;
                $finalFilename = $baseFilename . '.gz';
            }
        }

        $fileSize = (int) filesize($finalPath);

        return [
            'success' => true,
            'filename' => $finalFilename,
            'path' => $finalPath,
            'size' => $fileSize,
            'size_formatted' => $this->formatBytes($fileSize),
            'created_at' => Carbon::now(),
        ];
    }

    /**
     * Memulihkan database dari file .sql atau .sql.gz.
     * Secara otomatis membuat safety backup terlebih dahulu sebelum restore dieksekusi.
     */
    public function restoreFromFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File backup tidak ditemukan: {$filePath}");
        }

        // 1. Buat safety backup otomatis dari database saat ini
        $safetyBackup = null;
        try {
            $safetyBackup = $this->createBackup('safety_auto_before_restore');
        } catch (\Throwable $e) {
            // Jika safety backup gagal, hentikan demi keamanan
            throw new RuntimeException('Gagal membuat cadangan darurat (safety backup) sebelum memulihkan: ' . $e->getMessage());
        }

        // 2. Baca isi file backup (decompress jika .gz)
        $isGz = str_ends_with(strtolower($filePath), '.gz');
        $rawBytes = file_get_contents($filePath);

        if ($rawBytes === false) {
            throw new RuntimeException('Gagal membaca file backup.');
        }

        $sqlContent = $isGz ? gzdecode($rawBytes) : $rawBytes;

        if ($sqlContent === false || trim($sqlContent) === '') {
            throw new RuntimeException('File backup rusak atau tidak dapat didekompresi.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // 3. Eksekusi restore dengan menonaktifkan foreign key checks
        try {
            if ($driver === 'sqlite') {
                $connection->statement('PRAGMA foreign_keys = OFF;');
                $connection->unprepared($sqlContent);
                $connection->statement('PRAGMA foreign_keys = ON;');
            } else {
                $connection->statement('SET FOREIGN_KEY_CHECKS=0;');
                $connection->unprepared($sqlContent);
                $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        } catch (\Throwable $e) {
            throw new RuntimeException('Terjadi kesalahan saat mengeksekusi SQL dump: ' . $e->getMessage());
        }

        // 4. Bersihkan cache aplikasi & route
        try {
            Artisan::call('cache:clear');
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            // Ignore cache clear error
        }

        return [
            'success' => true,
            'safety_backup' => $safetyBackup['filename'] ?? null,
            'message' => 'Database berhasil dipulihkan dari file ' . basename($filePath),
        ];
    }

    /**
     * Mengambil daftar seluruh file backup di storage server.
     */
    public function listBackups(): Collection
    {
        $dir = $this->getStorageDir();
        if (! File::isDirectory($dir)) {
            return collect();
        }

        $files = File::files($dir);
        $backups = collect();

        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            $name = $file->getFilename();

            if (! in_array($ext, ['sql', 'gz'], true)) {
                continue;
            }

            $size = $file->getSize();
            $mtime = Carbon::createFromTimestamp($file->getMTime());
            $isSafetyAuto = str_starts_with($name, 'safety_auto_');

            $backups->push([
                'filename' => $name,
                'path' => $file->getPathname(),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => $mtime,
                'created_at_label' => IndonesianDate::formatLong($mtime) . ' ' . $mtime->format('H:i:s') . ' WIB',
                'is_safety_auto' => $isSafetyAuto,
                'type_label' => $isSafetyAuto ? 'Otomatis (Pra-Restore)' : 'Manual',
                'is_gzip' => str_ends_with(strtolower($name), '.gz'),
            ]);
        }

        return $backups->sortByDesc('created_at')->values();
    }

    /**
     * Mengambil path lengkap file backup yang valid dan aman dari path traversal.
     */
    public function getBackupPath(string $filename): ?string
    {
        $safeName = basename($filename);
        $fullPath = $this->getStorageDir() . DIRECTORY_SEPARATOR . $safeName;

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Menghapus file backup tertentu.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getBackupPath($filename);
        if ($path && file_exists($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * Mengambil statistik ringkas database saat ini.
     */
    public function getDatabaseStats(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $dbName = (string) config("database.connections.{$connection->getName()}.database", 'database');

        $tableCount = 0;
        if ($driver === 'sqlite') {
            $tableCount = (int) DB::table('sqlite_master')->where('type', 'table')->where('name', 'not like', 'sqlite_%')->count();
        } else {
            $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
            $tableCount = count($tables);
        }

        $backups = $this->listBackups();
        $totalBackupBytes = $backups->sum('size');
        $lastBackup = $backups->first();

        return [
            'driver' => strtoupper($driver),
            'database_name' => $dbName,
            'table_count' => $tableCount,
            'total_backups_count' => $backups->count(),
            'total_backup_bytes' => $totalBackupBytes,
            'total_backup_size_formatted' => $this->formatBytes($totalBackupBytes),
            'last_backup_time' => $lastBackup['created_at_label'] ?? 'Belum pernah backup',
        ];
    }

    /**
     * Dump database MySQL / MariaDB via PDO streaming.
     */
    protected function dumpMysql($fp, $connection, \PDO $pdo, string $dbName): void
    {
        fwrite($fp, "-- ==============================================================\n");
        fwrite($fp, "-- Backup Database: {$dbName}\n");
        fwrite($fp, "-- Aplikasi: Sistem Administrasi Guru\n");
        fwrite($fp, "-- Waktu Dibuat: " . Carbon::now()->toDateTimeString() . "\n");
        fwrite($fp, "-- ==============================================================\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($fp, "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($fp, "SET time_zone = \"+00:00\";\n");
        fwrite($fp, "SET NAMES utf8mb4;\n\n");

        $rawTables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tables = [];
        foreach ($rawTables as $row) {
            $arr = array_values((array) $row);
            if (! empty($arr[0])) {
                $tables[] = $arr[0];
            }
        }

        foreach ($tables as $table) {
            // Write Create Table
            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "-- Struktur Tabel: `{$table}`\n");
            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");

            $createRow = DB::select("SHOW CREATE TABLE `{$table}`");
            if (! empty($createRow[0])) {
                $createArr = (array) $createRow[0];
                $createSql = $createArr['Create Table'] ?? '';
                fwrite($fp, $createSql . ";\n\n");
            }

            // Write Table Data in chunks
            $this->dumpTableData($fp, $table, $pdo);
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * Dump database SQLite via PDO streaming.
     */
    protected function dumpSqlite($fp, $connection, \PDO $pdo): void
    {
        fwrite($fp, "-- ==============================================================\n");
        fwrite($fp, "-- Backup Database SQLite\n");
        fwrite($fp, "-- Aplikasi: Sistem Administrasi Guru\n");
        fwrite($fp, "-- Waktu Dibuat: " . Carbon::now()->toDateTimeString() . "\n");
        fwrite($fp, "-- ==============================================================\n\n");
        fwrite($fp, "PRAGMA foreign_keys = OFF;\n\n");

        $tables = DB::table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'not like', 'sqlite_%')
            ->get();

        foreach ($tables as $row) {
            $table = $row->name;
            $sql = $row->sql;

            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "-- Struktur Tabel: \"{$table}\"\n");
            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "DROP TABLE IF EXISTS \"{$table}\";\n");
            fwrite($fp, $sql . ";\n\n");

            $this->dumpTableData($fp, $table, $pdo, true);
        }

        fwrite($fp, "PRAGMA foreign_keys = ON;\n");
    }

    /**
     * Dump data dari suatu tabel dalam batch 500 baris.
     */
    protected function dumpTableData($fp, string $table, \PDO $pdo, bool $isSqlite = false): void
    {
        $query = DB::table($table);
        $totalRows = $query->count();
        if ($totalRows === 0) {
            return;
        }

        $quoteChar = $isSqlite ? '"' : '`';
        $columns = [];

        DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($fp, $table, $pdo, $quoteChar, &$columns) {
            if ($rows->isEmpty()) {
                return;
            }

            if (empty($columns)) {
                $firstRow = (array) $rows->first();
                $columns = array_keys($firstRow);
            }

            $colList = implode(', ', array_map(fn ($c) => "{$quoteChar}{$c}{$quoteChar}", $columns));

            fwrite($fp, "INSERT INTO {$quoteChar}{$table}{$quoteChar} ({$colList}) VALUES\n");

            $rowCount = count($rows);
            foreach ($rows as $idx => $row) {
                $rowArr = (array) $row;
                $values = [];

                foreach ($columns as $col) {
                    $val = $rowArr[$col] ?? null;
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_bool($val)) {
                        $values[] = $val ? '1' : '0';
                    } elseif (is_int($val) || is_float($val)) {
                        $values[] = (string) $val;
                    } else {
                        $values[] = $pdo->quote((string) $val);
                    }
                }

                $valStr = '(' . implode(', ', $values) . ')';
                $separator = ($idx === $rowCount - 1) ? ";\n\n" : ",\n";
                fwrite($fp, $valStr . $separator);
            }
        });
    }

    /**
     * Format byte ke string human-readable (KB, MB, GB).
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
