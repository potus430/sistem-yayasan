<?php

use App\Http\Controllers\PayrollSlipPdfController;
use App\Http\Controllers\PayrollExcelController;
use App\Http\Controllers\ReportDownloadController;
use App\Livewire\AccountProfile;
use App\Livewire\AttendanceManager;
use App\Livewire\Auth\Login;
use App\Livewire\BranchDirectory;
use App\Livewire\Dashboard;
use App\Livewire\EmployeeDirectory;
use App\Livewire\IncentiveSettings;
use App\Livewire\OrganizationSettingsManager;
use App\Livewire\PayrollManager;
use App\Livewire\PositionCompensationManager;
use App\Livewire\RosterGenerator;
use App\Livewire\ReportsDashboard;
use App\Livewire\SchoolStructureManager;
use App\Livewire\SppDashboard;
use App\Livewire\StudentDirectory;
use App\Livewire\UserDirectory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::post('/logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin_pusat,admin_cabang,keuangan,guru,staff'])->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/roster', RosterGenerator::class)->name('roster');
    Route::get('/profile', AccountProfile::class)->name('profile');
});

Route::middleware(['auth', 'role:admin_pusat,admin_cabang'])->group(function (): void {
    Route::get('/employees', EmployeeDirectory::class)->name('employees');
    Route::get('/students', StudentDirectory::class)->name('students');
    Route::get('/school-structure', SchoolStructureManager::class)->name('school-structure');
});

Route::middleware(['auth', 'role:admin_pusat'])->group(function (): void {
    Route::get('/branches', BranchDirectory::class)->name('branches');
    Route::get('/users', UserDirectory::class)->name('users');
    Route::get('/organization-settings', OrganizationSettingsManager::class)->name('organization-settings');
});

Route::middleware(['auth', 'role:admin_pusat,admin_cabang'])->group(function (): void {
    Route::get('/attendance', AttendanceManager::class)->name('attendance');
});

Route::middleware(['auth', 'role:admin_pusat,keuangan'])->group(function (): void {
    Route::get('/payroll', PayrollManager::class)->name('payroll');
    Route::get('/incentives', IncentiveSettings::class)->name('incentives');
    Route::get('/payroll/export.xlsx', PayrollExcelController::class)->name('payroll.export.xlsx');
    Route::get('/payroll/slips.pdf', PayrollSlipPdfController::class)->name('payroll.slips.pdf');
    Route::get('/spp', SppDashboard::class)->name('spp');
});

Route::middleware(['auth', 'role:admin_pusat,admin_cabang,keuangan'])
    ->group(function (): void {
        Route::get('/reports', ReportsDashboard::class)->name('reports');
        Route::get('/reports/{report}', ReportDownloadController::class)->name('reports.download');
    });

Route::middleware(['auth', 'role:admin_pusat,admin_cabang,keuangan'])
    ->get('/position-compensation', PositionCompensationManager::class)
    ->name('position-compensation');
