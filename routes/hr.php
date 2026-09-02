<?php

use App\Http\Controllers\Hr\AssetAssignmentController;
use App\Http\Controllers\Hr\AssetCategoryController;
use App\Http\Controllers\Hr\AssetController;
use App\Http\Controllers\Hr\EmployeeCompensationController;
use App\Http\Controllers\Hr\EmployeeContractController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\EmployeeDocumentController;
use App\Http\Controllers\Hr\EmployeeNextOfKinController;
use App\Http\Controllers\Hr\EmployeeRecurringItemController;
use App\Http\Controllers\Hr\LeaveApprovalController;
use App\Http\Controllers\Hr\LeaveBalanceController;
use App\Http\Controllers\Hr\LeaveCalendarController;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Http\Controllers\Hr\LeaveSettingController;
use App\Http\Controllers\Hr\LeaveTypeController;
use App\Http\Controllers\Hr\Me\LeaveController as MyLeaveController;
use App\Http\Controllers\Hr\Me\PayslipController as MyPayslipController;
use App\Http\Controllers\Hr\Me\ProfileController;
use App\Http\Controllers\Hr\PayrollPeriodController;
use App\Http\Controllers\Hr\PayslipController;
use App\Http\Controllers\Hr\PerformanceCycleController;
use App\Http\Controllers\Hr\PerformanceGoalController;
use App\Http\Controllers\Hr\PerformanceReviewController;
use App\Http\Controllers\Hr\PublicHolidayController;
use App\Http\Controllers\Hr\StatutoryRateSetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:api-writes'])->prefix('hr')->name('hr.')->group(function () {
    // Employee self-service — any user with a linked employee record.
    Route::get('me/profile', [ProfileController::class, 'show'])->name('me.profile');
    Route::get('me/documents/{document}', [ProfileController::class, 'downloadDocument'])->name('me.documents.download');
    Route::get('me/leave', [MyLeaveController::class, 'index'])->name('me.leave');
    Route::get('me/payslips', [MyPayslipController::class, 'index'])->name('me.payslips');
    Route::get('me/payslips/{payslip}/download', [MyPayslipController::class, 'download'])->name('me.payslips.download');

    // Leave
    Route::get('leave', [LeaveRequestController::class, 'index'])->name('leave.index');
    Route::get('leave/calendar', [LeaveCalendarController::class, 'index'])->name('leave.calendar');
    Route::post('leave/requests', [LeaveRequestController::class, 'store'])->name('leave.requests.store');
    Route::get('leave/requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave.requests.show');
    Route::post('leave/requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave.requests.cancel');
    Route::post('leave/requests/{leaveRequest}/decision', [LeaveApprovalController::class, 'store'])->name('leave.requests.decide');

    Route::get('leave/settings', [LeaveSettingController::class, 'edit'])->name('leave.settings.edit');
    Route::patch('leave/settings', [LeaveSettingController::class, 'update'])->name('leave.settings.update');

    Route::get('leave/types', [LeaveTypeController::class, 'index'])->name('leave.types.index');
    Route::post('leave/types', [LeaveTypeController::class, 'store'])->name('leave.types.store');
    Route::patch('leave/types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave.types.update');
    Route::delete('leave/types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave.types.destroy');

    Route::get('leave/holidays', [PublicHolidayController::class, 'index'])->name('leave.holidays.index');
    Route::post('leave/holidays', [PublicHolidayController::class, 'store'])->name('leave.holidays.store');
    Route::patch('leave/holidays/{holiday}', [PublicHolidayController::class, 'update'])->name('leave.holidays.update');
    Route::delete('leave/holidays/{holiday}', [PublicHolidayController::class, 'destroy'])->name('leave.holidays.destroy');

    Route::get('leave/balances', [LeaveBalanceController::class, 'index'])->name('leave.balances.index');
    Route::post('leave/balances/{employee}/provision', [LeaveBalanceController::class, 'provision'])->name('leave.balances.provision');
    Route::patch('leave/balances/{balance}', [LeaveBalanceController::class, 'update'])->name('leave.balances.update');

    // Employees
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::post('employees/{employee}/next-of-kin', [EmployeeNextOfKinController::class, 'store'])->name('employees.next-of-kin.store');
    Route::patch('employees/{employee}/next-of-kin/{nextOfKin}', [EmployeeNextOfKinController::class, 'update'])->name('employees.next-of-kin.update');
    Route::delete('employees/{employee}/next-of-kin/{nextOfKin}', [EmployeeNextOfKinController::class, 'destroy'])->name('employees.next-of-kin.destroy');

    Route::post('employees/{employee}/renew-contract', [EmployeeContractController::class, 'renew'])->name('employees.renew-contract');
    Route::post('employees/{employee}/contracts', [EmployeeContractController::class, 'store'])->name('employees.contracts.store');
    Route::patch('employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'update'])->name('employees.contracts.update');
    Route::delete('employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'destroy'])->name('employees.contracts.destroy');

    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');

    Route::post('employees/{employee}/compensation', [EmployeeCompensationController::class, 'store'])->name('employees.compensation.store');
    Route::patch('employees/{employee}/compensation/{compensation}', [EmployeeCompensationController::class, 'update'])->name('employees.compensation.update');
    Route::delete('employees/{employee}/compensation/{compensation}', [EmployeeCompensationController::class, 'destroy'])->name('employees.compensation.destroy');

    Route::post('employees/{employee}/recurring-items', [EmployeeRecurringItemController::class, 'store'])->name('employees.recurring-items.store');
    Route::patch('employees/{employee}/recurring-items/{item}', [EmployeeRecurringItemController::class, 'update'])->name('employees.recurring-items.update');
    Route::delete('employees/{employee}/recurring-items/{item}', [EmployeeRecurringItemController::class, 'destroy'])->name('employees.recurring-items.destroy');

    // Payroll
    Route::get('payroll', [PayrollPeriodController::class, 'index'])->name('payroll.index');
    Route::post('payroll', [PayrollPeriodController::class, 'store'])->name('payroll.store');
    Route::get('payroll/rate-sets', [StatutoryRateSetController::class, 'index'])->name('payroll.rate-sets.index');
    Route::post('payroll/rate-sets', [StatutoryRateSetController::class, 'store'])->name('payroll.rate-sets.store');
    Route::patch('payroll/rate-sets/{rateSet}', [StatutoryRateSetController::class, 'update'])->name('payroll.rate-sets.update');
    Route::get('payroll/{payrollPeriod}', [PayrollPeriodController::class, 'show'])->name('payroll.show');
    Route::post('payroll/{payrollPeriod}/process', [PayrollPeriodController::class, 'process'])->name('payroll.process');
    Route::post('payroll/{payrollPeriod}/approve', [PayrollPeriodController::class, 'approve'])->name('payroll.approve');
    Route::post('payroll/{payrollPeriod}/mark-paid', [PayrollPeriodController::class, 'markPaid'])->name('payroll.mark-paid');
    Route::get('payroll/{payrollPeriod}/export/{report}', [PayrollPeriodController::class, 'export'])->name('payroll.export');
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/download', [PayslipController::class, 'download'])->name('payslips.download');

    // Performance
    Route::get('performance', [PerformanceCycleController::class, 'index'])->name('performance.index');
    Route::post('performance/cycles', [PerformanceCycleController::class, 'store'])->name('performance.cycles.store');
    Route::patch('performance/cycles/{cycle}', [PerformanceCycleController::class, 'update'])->name('performance.cycles.update');
    Route::post('performance/cycles/{cycle}/activate', [PerformanceCycleController::class, 'activate'])->name('performance.cycles.activate');
    Route::get('performance/reviews/{review}', [PerformanceReviewController::class, 'show'])->name('performance.reviews.show');
    Route::patch('performance/reviews/{review}', [PerformanceReviewController::class, 'update'])->name('performance.reviews.update');
    Route::post('performance/reviews/{review}/transition', [PerformanceReviewController::class, 'transition'])->name('performance.reviews.transition');

    Route::post('employees/{employee}/goals', [PerformanceGoalController::class, 'store'])->name('employees.goals.store');
    Route::patch('employees/{employee}/goals/{goal}', [PerformanceGoalController::class, 'update'])->name('employees.goals.update');
    Route::delete('employees/{employee}/goals/{goal}', [PerformanceGoalController::class, 'destroy'])->name('employees.goals.destroy');

    // Assets
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::patch('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    Route::post('asset-categories', [AssetCategoryController::class, 'store'])->name('asset-categories.store');
    Route::patch('asset-categories/{category}', [AssetCategoryController::class, 'update'])->name('asset-categories.update');

    Route::post('assets/{asset}/assignments', [AssetAssignmentController::class, 'store'])->name('assets.assignments.store');
    Route::patch('assets/{asset}/assignments/{assignment}', [AssetAssignmentController::class, 'update'])->name('assets.assignments.update');
});
