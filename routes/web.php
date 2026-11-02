<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\KktpSubjectController;
use App\Http\Controllers\Admin\SchoolAndYearController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\TeacherAccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyJournalController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\TeachingClassController;
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

// Area Terautentikasi (Guru & Admin)
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-academic-year', [AuthController::class, 'switchAcademicYear'])->name('academic-year.switch');

    // Profil & Tanda Tangan Digital
    Route::get('/profil', [AuthController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profil', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Panel Khusus Admin (Monitoring, Akun Guru, KKTP, Kop Sekolah & Tahun Ajaran)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Manajemen Akun Guru & Admin
        Route::get('/guru', [TeacherAccountController::class, 'index'])->name('teachers.index');
        Route::post('/guru', [TeacherAccountController::class, 'store'])->name('teachers.store');
        Route::put('/guru/{teacher}', [TeacherAccountController::class, 'update'])->name('teachers.update');
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
    });

    // Ruang Kerja Kelas Administrasi (Guru 1..15+ Kelas & Kontrol Admin)
    Route::get('/kelas', [TeachingClassController::class, 'index'])->name('classes.index');
    Route::post('/kelas', [TeachingClassController::class, 'store'])->name('classes.store');
    Route::put('/kelas/{teachingClass}', [TeachingClassController::class, 'update'])->name('classes.update');
    Route::delete('/kelas/{teachingClass}', [TeachingClassController::class, 'destroy'])->name('classes.destroy');

    // Pengaturan Buku Kelas & Identitas
    Route::get('/kelas/{teachingClass}/pengaturan', [TeachingClassController::class, 'students'])->name('classes.settings');
    Route::get('/kelas/{teachingClass}/murid', [TeachingClassController::class, 'students'])->name('classes.students');
    Route::post('/kelas/{teachingClass}/nama-panggilan', [TeachingClassController::class, 'updateNicknames'])->name('classes.nicknames.update');
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
    Route::post('/kelas/{teachingClass}/jurnal', [DailyJournalController::class, 'store'])->name('journals.store');
    Route::put('/kelas/{teachingClass}/jurnal/{journal}', [DailyJournalController::class, 'update'])->name('journals.update');
    Route::post('/kelas/{teachingClass}/jurnal/{journal}/resync', [DailyJournalController::class, 'resyncAttendance'])->name('journals.resync');
    Route::delete('/kelas/{teachingClass}/jurnal/{journal}', [DailyJournalController::class, 'destroy'])->name('journals.destroy');

    // Menu 2: Absensi
    Route::get('/kelas/{teachingClass}/absensi', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::post('/kelas/{teachingClass}/absensi/bulk-save', [AttendanceController::class, 'bulkSave'])->name('attendances.bulk-save');
    Route::get('/kelas/{teachingClass}/absensi/template', [AttendanceController::class, 'downloadTemplate'])->name('attendances.template');
    Route::post('/kelas/{teachingClass}/absensi/import', [AttendanceController::class, 'importExcel'])->name('attendances.import');

    // Menu 3: Nilai
    Route::get('/kelas/{teachingClass}/nilai', [GradebookController::class, 'index'])->name('grades.index');
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
