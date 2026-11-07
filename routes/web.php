<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\KktpSubjectController;
use App\Http\Controllers\Admin\SchoolAndYearController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SchoolEventController;
use App\Http\Controllers\Admin\SchoolScheduleController;
use App\Http\Controllers\Admin\TeacherAccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyJournalController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\MasterScheduleController;
use App\Http\Controllers\TeachingClassController;
use App\Http\Controllers\TeachingScheduleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('classes.index');
})->name('home');

// Autentikasi
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Logout (Mendukung POST & GET, tidak dibatasi auth agar jika session kedaluwarsa tetap sukses keluar)
Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Area Terautentikasi (Guru & Admin)
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/switch-academic-year', [AuthController::class, 'switchAcademicYear'])->name('academic-year.switch');

    // Profil & Tanda Tangan Digital
    Route::get('/profil', [AuthController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profil', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Panel Khusus Admin (Monitoring, Akun Guru, KKTP, Kop Sekolah & Tahun Ajaran)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Master Guru & Akun Pengguna
        Route::get('/guru', [TeacherAccountController::class, 'index'])->name('teachers.index');
        Route::post('/guru', [TeacherAccountController::class, 'store'])->name('teachers.store');
        Route::put('/guru/{teacher}', [TeacherAccountController::class, 'update'])->name('teachers.update');
        Route::patch('/guru/{teacher}/signature', [TeacherAccountController::class, 'updateSignature'])->name('teachers.signature.update');
        Route::patch('/guru/{teacher}/toggle', [TeacherAccountController::class, 'toggleStatus'])->name('teachers.toggle');
        Route::delete('/guru/{teacher}', [TeacherAccountController::class, 'destroy'])->name('teachers.destroy');

        // Master Rombongan Belajar (Rombel) & Peserta Didik
        Route::get('/rombel', [SchoolClassController::class, 'index'])->name('classes.index');
        Route::post('/rombel', [SchoolClassController::class, 'store'])->name('classes.store');
        Route::get('/rombel/{schoolClass}', [SchoolClassController::class, 'show'])->name('classes.show');
        Route::put('/rombel/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
        Route::delete('/rombel/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('/rombel/{schoolClass}/murid', [SchoolClassController::class, 'storeStudent'])->name('classes.students.store');
        Route::put('/rombel/{schoolClass}/murid/{student}', [SchoolClassController::class, 'updateStudent'])->name('classes.students.update');
        Route::delete('/rombel/{schoolClass}/murid/{student}', [SchoolClassController::class, 'destroyStudent'])->name('classes.students.destroy');
        Route::delete('/rombel/{schoolClass}/murid-massal', [SchoolClassController::class, 'bulkDestroyStudents'])->name('classes.students.bulk-destroy');
        Route::post('/rombel/{schoolClass}/murid/sort-az', [SchoolClassController::class, 'sortStudentsAlphabetically'])->name('classes.students.sort');
        Route::get('/rombel/{schoolClass}/murid/template', [SchoolClassController::class, 'downloadStudentTemplate'])->name('classes.students.template');
        Route::post('/rombel/{schoolClass}/murid/import', [SchoolClassController::class, 'importStudents'])->name('classes.students.import');

        // Master Mata Pelajaran & KKM / KKTP
        Route::get('/kktp', [KktpSubjectController::class, 'index'])->name('kktp.index');
        Route::post('/kktp/subjects', [KktpSubjectController::class, 'storeSubject'])->name('kktp.subjects.store');
        Route::put('/kktp/matrix', [KktpSubjectController::class, 'updateMatrix'])->name('kktp.matrix.update');
        Route::delete('/kktp/subjects/{subject}', [KktpSubjectController::class, 'destroySubject'])->name('kktp.subjects.destroy');

        // Profil Sekolah, Kop Surat & Tahun Ajaran
        Route::get('/pengaturan', [SchoolAndYearController::class, 'index'])->name('settings.index');
        Route::put('/pengaturan/sekolah', [SchoolAndYearController::class, 'updateSchool'])->name('settings.school.update');
        Route::post('/pengaturan/tahun-ajaran', [SchoolAndYearController::class, 'storeAcademicYear'])->name('settings.years.store');
        Route::patch('/pengaturan/tahun-ajaran/{academicYear}/activate', [SchoolAndYearController::class, 'activateAcademicYear'])->name('settings.years.activate');
        Route::delete('/pengaturan/tahun-ajaran/{academicYear}', [SchoolAndYearController::class, 'destroyAcademicYear'])->name('settings.years.destroy');

        // Kalender Akademik, Hari Libur & Agenda Sekolah
        Route::get('/kalender', [SchoolEventController::class, 'index'])->name('events.index');
        Route::post('/kalender', [SchoolEventController::class, 'store'])->name('events.store');
        Route::delete('/kalender/{schoolEvent}', [SchoolEventController::class, 'destroy'])->name('events.destroy');

        // Backup & Restore Database
        Route::get('/backup', [DatabaseBackupController::class, 'index'])->name('backup.index');
        Route::post('/backup', [DatabaseBackupController::class, 'store'])->name('backup.store');
        Route::get('/backup/{filename}/download', [DatabaseBackupController::class, 'download'])->name('backup.download');
        Route::post('/backup/restore', [DatabaseBackupController::class, 'restore'])->name('backup.restore');
        Route::delete('/backup/{filename}', [DatabaseBackupController::class, 'destroy'])->name('backup.destroy');

        // Master Jadwal Pelajaran (Wakasek Kurikulum & Admin)
        Route::get('/jadwal-master', [SchoolScheduleController::class, 'index'])->name('schedules.master.index');
        Route::get('/jadwal-master/builder', [SchoolScheduleController::class, 'builder'])->name('schedules.master.builder');
        Route::post('/jadwal-master/builder/calculate-slots', [SchoolScheduleController::class, 'calculateSlotsAjax'])->name('schedules.master.builder.calculate');
        Route::post('/jadwal-master/builder/generate', [SchoolScheduleController::class, 'saveBuilderSchedule'])->name('schedules.master.builder.generate');
        Route::post('/jadwal-master/create-scratch', [SchoolScheduleController::class, 'createScratch'])->name('schedules.master.scratch');
        Route::post('/jadwal-master/load-wakasek', [SchoolScheduleController::class, 'loadWakasekTemplate'])->name('schedules.master.load-wakasek');
        Route::put('/jadwal-master/item/{item}', [SchoolScheduleController::class, 'updateCell'])->name('schedules.master.update-cell');
        Route::get('/jadwal-master/swap-targets', [SchoolScheduleController::class, 'swapTargets'])->name('schedules.master.swap-targets');
        Route::post('/jadwal-master/swap', [SchoolScheduleController::class, 'swapCells'])->name('schedules.master.swap');
        Route::post('/jadwal-master/sync-classes', [SchoolScheduleController::class, 'syncClasses'])->name('schedules.master.sync-classes');
        Route::delete('/jadwal-master/clear', [SchoolScheduleController::class, 'clearSchedule'])->name('schedules.master.clear');
        Route::get('/jadwal-master/export', [SchoolScheduleController::class, 'export'])->name('schedules.master.export');
    });

    // Menu Baru: Jadwal Utama (Guru & Admin)
    Route::get('/jadwal-utama', [MasterScheduleController::class, 'index'])->name('schedules.main.index');

    // Ruang Kerja Kelas Administrasi (Guru 1..15+ Kelas & Kontrol Admin)
    Route::get('/jadwal-mengajar', [TeachingScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/kelas/schedule-allocations', [TeachingClassController::class, 'scheduleAllocations'])->name('classes.schedule-allocations');
    Route::post('/kelas/sync-from-schedule', [TeachingClassController::class, 'syncFromSchedule'])->name('classes.sync-from-schedule');
    Route::get('/kelas', [TeachingClassController::class, 'index'])->name('classes.index');
    Route::post('/kelas', [TeachingClassController::class, 'store'])->name('classes.store');
    Route::put('/kelas/{teachingClass}', [TeachingClassController::class, 'update'])->name('classes.update');
    Route::delete('/kelas/{teachingClass}', [TeachingClassController::class, 'destroy'])->name('classes.destroy');

    // Pengaturan Buku Kelas & Identitas
    Route::get('/kelas/{teachingClass}/pengaturan', [TeachingClassController::class, 'students'])->name('classes.settings');
    Route::get('/kelas/{teachingClass}/murid', [TeachingClassController::class, 'students'])->name('classes.students');
    Route::post('/kelas/{teachingClass}/murid', [TeachingClassController::class, 'storeStudent'])->name('classes.students.store');
    Route::put('/kelas/{teachingClass}/murid/{student}', [TeachingClassController::class, 'updateStudent'])->name('classes.students.update');
    Route::delete('/kelas/{teachingClass}/murid/{student}', [TeachingClassController::class, 'destroyStudent'])->name('classes.students.destroy');
    Route::post('/kelas/{teachingClass}/murid/sort-az', [TeachingClassController::class, 'sortStudentsAlphabetically'])->name('classes.students.sort');
    Route::post('/kelas/{teachingClass}/murid/copy', [TeachingClassController::class, 'copyStudentsFromClass'])->name('classes.students.copy');
    Route::get('/kelas/{teachingClass}/murid/template', [TeachingClassController::class, 'downloadStudentTemplate'])->name('classes.students.template');
    Route::post('/kelas/{teachingClass}/murid/import', [TeachingClassController::class, 'importStudents'])->name('classes.students.import');

    // Menu 1: Jurnal Harian
    Route::get('/kelas/{teachingClass}/jurnal', [DailyJournalController::class, 'index'])->name('journals.index');
    Route::get('/kelas/{teachingClass}/jurnal/attendance-preview', [DailyJournalController::class, 'attendancePreview'])->name('journals.attendance-preview');
    Route::get('/kelas/{teachingClass}/jurnal/absent-students', [DailyJournalController::class, 'absentStudents'])->name('journals.absent-students');
    Route::post('/kelas/{teachingClass}/jurnal', [DailyJournalController::class, 'store'])->name('journals.store');
    Route::put('/kelas/{teachingClass}/jurnal/{journal}', [DailyJournalController::class, 'update'])->name('journals.update');
    Route::post('/kelas/{teachingClass}/jurnal/{journal}/resync', [DailyJournalController::class, 'resyncAttendance'])->name('journals.resync');
    Route::delete('/kelas/{teachingClass}/jurnal/{journal}', [DailyJournalController::class, 'destroy'])->name('journals.destroy');

    // Menu 2: Absensi
    Route::get('/kelas/{teachingClass}/absensi', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::post('/kelas/{teachingClass}/absensi/bulk-save', [AttendanceController::class, 'bulkSave'])->name('attendances.bulk-save');

    // Menu 3: Nilai
    Route::get('/kelas/{teachingClass}/nilai', [GradebookController::class, 'index'])->name('grades.index');
    Route::patch('/kelas/{teachingClass}/nilai/toggle-admin-access', [GradebookController::class, 'toggleAdminAccess'])->name('grades.toggle-admin-access');
    Route::post('/kelas/{teachingClass}/nilai/bulk-save', [GradebookController::class, 'bulkSaveScores'])->name('grades.bulk-save');
    Route::post('/kelas/{teachingClass}/nilai/columns', [GradebookController::class, 'addColumn'])->name('grades.columns.store');
    Route::put('/kelas/{teachingClass}/nilai/columns/{column}', [GradebookController::class, 'updateColumn'])->name('grades.columns.update');
    Route::delete('/kelas/{teachingClass}/nilai/columns/{column}', [GradebookController::class, 'deleteColumn'])->name('grades.columns.destroy');
    Route::put('/kelas/{teachingClass}/nilai/weights', [GradebookController::class, 'updateWeights'])->name('grades.weights.update');
    Route::get('/kelas/{teachingClass}/nilai/template', [GradebookController::class, 'downloadTemplate'])->name('grades.template');
    Route::post('/kelas/{teachingClass}/nilai/import', [GradebookController::class, 'importExcel'])->name('grades.import');

    // Cetak PDF & Download Excel Resmi (Semua Menu + Bundel Lengkap)
    Route::prefix('/kelas/{teachingClass}/export')->name('exports.')->group(function () {
        Route::get('/jurnal/pdf', [ExportController::class, 'journalPdf'])->name('journal.pdf');
        Route::get('/jurnal/excel', [ExportController::class, 'journalExcel'])->name('journal.excel');

        Route::get('/absensi-bulanan/pdf', [ExportController::class, 'attendanceMonthlyPdf'])->name('attendance-monthly.pdf');
        Route::get('/absensi-bulanan/excel', [ExportController::class, 'attendanceMonthlyExcel'])->name('attendance-monthly.excel');

        Route::get('/absensi-rekap/pdf', [ExportController::class, 'attendanceRecapPdf'])->name('attendance-recap.pdf');
        Route::get('/absensi-rekap/excel', [ExportController::class, 'attendanceRecapExcel'])->name('attendance-recap.excel');

        Route::get('/nilai/pdf', [ExportController::class, 'gradebookPdf'])->name('gradebook.pdf');
        Route::get('/nilai/excel', [ExportController::class, 'gradebookExcel'])->name('gradebook.excel');

        Route::get('/bundel/pdf', [ExportController::class, 'bundlePdf'])->name('bundle.pdf');
        Route::get('/bundel/excel', [ExportController::class, 'bundleExcel'])->name('bundle.excel');
    });
});
