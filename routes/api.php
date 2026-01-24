<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\API\Admin\StudentController;
use App\Http\Controllers\API\Admin\FamilyController;
use App\Http\Controllers\API\Admin\TeacherController;
use App\Http\Controllers\API\Admin\CourseController;
use App\Http\Controllers\API\Admin\TimetableController;
use App\Http\Controllers\API\Admin\ClassController;
use App\Http\Controllers\API\Admin\TrialClassController;
use App\Http\Controllers\API\Admin\LeadController;
use App\Http\Controllers\API\Admin\PackageController;
use App\Http\Controllers\API\Admin\ActivityController;
use App\Http\Controllers\API\Admin\SalaryController;
use App\Http\Controllers\API\Admin\FinancialController;
use App\Http\Controllers\API\Admin\NotificationController;
use App\Http\Controllers\API\Admin\ReportController;
use App\Http\Controllers\API\Admin\AnalyticsController;
use App\Http\Controllers\API\Teacher\TeacherPanelController;
use App\Http\Controllers\API\Support\SupportAlertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::get('/roles', [AuthController::class, 'getRoles']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Protected routes - require authentication
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Support app routes
    Route::prefix('support')->middleware('auth:api')->group(function () {
        Route::get('/dashboard', [SupportAlertController::class, 'dashboard']);
        Route::get('/class-alerts', [SupportAlertController::class, 'classAlerts']);
        Route::get('/pending-bills', [SupportAlertController::class, 'pendingBills']);
        Route::post('/register-device', [SupportAlertController::class, 'registerDevice']);
        Route::post('/test-notification', [SupportAlertController::class, 'testNotification']);
    });

    // Admin routes - require authentication only (permissions will be added later)
    Route::prefix('admin')->middleware('auth:api')->group(function () {
        // Students routes
        Route::get('/students/stats', [StudentController::class, 'stats']);
        Route::get('/students', [StudentController::class, 'index']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/{id}', [StudentController::class, 'show']);
        Route::put('/students/{id}', [StudentController::class, 'update']);
        Route::delete('/students/{id}', [StudentController::class, 'destroy']);
        
        // Families routes
        Route::get('/families', [FamilyController::class, 'index']);
        Route::get('/families/search', [FamilyController::class, 'search']);
        Route::post('/families', [FamilyController::class, 'store']);
        Route::get('/families/{id}', [FamilyController::class, 'show']);
        Route::put('/families/{id}', [FamilyController::class, 'update']);
        Route::delete('/families/{id}', [FamilyController::class, 'destroy']);
        
        // Teachers routes
        Route::get('/teachers/stats', [TeacherController::class, 'stats']);
        Route::get('/teachers', [TeacherController::class, 'index']);
        Route::post('/teachers', [TeacherController::class, 'store']);
        Route::get('/teachers/{id}', [TeacherController::class, 'show']);
        Route::get('/teachers/{id}/performance', [TeacherController::class, 'performance']);
        Route::get('/teachers/{id}/monthly-stats', [TeacherController::class, 'monthlyStats']);
        Route::put('/teachers/{id}', [TeacherController::class, 'update']);
        Route::delete('/teachers/{id}', [TeacherController::class, 'destroy']);
        Route::post('/teachers/{id}/courses', [TeacherController::class, 'assignCourses']);
        
        // Courses routes
        Route::get('/courses/stats', [CourseController::class, 'stats']);
        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::get('/courses/{id}', [CourseController::class, 'show']);
        Route::put('/courses/{id}', [CourseController::class, 'update']);
        Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
        Route::post('/courses/{id}/teachers', [CourseController::class, 'assignTeachers']);
        
        // Timetables routes
        Route::get('/timetables', [TimetableController::class, 'index']);
        Route::post('/timetables', [TimetableController::class, 'store']);
        Route::get('/timetables/{id}', [TimetableController::class, 'show']);
        Route::put('/timetables/{id}', [TimetableController::class, 'update']);
        Route::delete('/timetables/{id}', [TimetableController::class, 'destroy']);
        Route::post('/timetables/{id}/generate-classes', [TimetableController::class, 'generateClasses']);
        Route::post('/timetables/{id}/pause', [TimetableController::class, 'pause']);
        Route::post('/timetables/{id}/resume', [TimetableController::class, 'resume']);
        
        // Classes routes
        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/classes/{id}', [ClassController::class, 'show']);
        Route::put('/classes/{id}/status', [ClassController::class, 'updateStatus']);
        Route::put('/classes/{id}', [ClassController::class, 'update']);
        Route::delete('/classes/{id}', [ClassController::class, 'destroy']);
        Route::delete('/classes/{id}/future', [ClassController::class, 'deleteFuture']);
        
        // Trial Classes routes
        Route::get('/trials/stats', [TrialClassController::class, 'stats']);
        Route::get('/trials', [TrialClassController::class, 'index']);
        Route::post('/trials', [TrialClassController::class, 'store']);
        Route::get('/trials/{id}', [TrialClassController::class, 'show']);
        Route::put('/trials/{id}', [TrialClassController::class, 'update']);
        Route::put('/trials/{id}/status', [TrialClassController::class, 'updateStatus']);
        Route::post('/trials/{id}/review', [TrialClassController::class, 'reviewTrial']);
        Route::post('/trials/{id}/convert', [TrialClassController::class, 'convert']);
        Route::delete('/trials/{id}', [TrialClassController::class, 'destroy']);
        
        // Leads routes
        Route::get('/leads/stats', [LeadController::class, 'stats']);
        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::get('/leads/{id}', [LeadController::class, 'show']);
        Route::put('/leads/{id}', [LeadController::class, 'update']);
        Route::put('/leads/{id}/status', [LeadController::class, 'updateStatus']);
        Route::post('/leads/bulk-status', [LeadController::class, 'bulkStatus']);
        Route::post('/leads/{id}/convert', [LeadController::class, 'convert']);
        Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
        
        // Packages routes
        Route::get('/packages/finished', [PackageController::class, 'finished']);
        Route::get('/packages/unnotified-count', [PackageController::class, 'getUnnotifiedCount']);
        Route::post('/packages/bulk-notify', [PackageController::class, 'bulkNotify']);
        Route::get('/packages/{id}/bills', [PackageController::class, 'bills']);
        Route::get('/packages/{id}/pdf', [PackageController::class, 'downloadPdf']);
        Route::get('/packages/{id}/classes', [PackageController::class, 'getPackageClasses']);
        Route::post('/packages/{id}/notify', [PackageController::class, 'notify']);
        Route::post('/packages/{id}/reactivate', [PackageController::class, 'reactivate']);
        Route::apiResource('packages', PackageController::class);
        
        // Activity routes
        Route::get('/activity', [ActivityController::class, 'index']);
        Route::get('/activity/stats', [ActivityController::class, 'stats']);
        Route::get('/activity/recent', [ActivityController::class, 'recent']);
        Route::get('/activity/students', [ActivityController::class, 'getStudents']);
        Route::post('/activity/reactivate/{studentId}', [ActivityController::class, 'reactivate']);
        
        // Salaries routes
        Route::prefix('salaries')->group(function () {
            Route::get('/', [SalaryController::class, 'index']);
            Route::get('/statistics', [SalaryController::class, 'statistics']);
            Route::get('/all-history', [SalaryController::class, 'allHistory']);
            Route::get('/{id}', [SalaryController::class, 'show']);
            Route::get('/{id}/breakdown', [SalaryController::class, 'breakdown']);
            Route::get('/{id}/history', [SalaryController::class, 'history']);
        });
        
        // Notifications routes
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::put('/class-cancellation/{id}/approve', [NotificationController::class, 'approveCancellation']);
            Route::put('/class-cancellation/{id}/reject', [NotificationController::class, 'rejectCancellation']);
        });
        
        // Financials routes
        Route::get('/financials/summary', [FinancialController::class, 'summary']);
        Route::get('/financials/income', [FinancialController::class, 'income']);
        Route::get('/financials/expenses', [FinancialController::class, 'expenseBreakdown']);
        Route::get('/financials/trends', [FinancialController::class, 'trends']);
        
        // Expenses CRUD
        Route::get('/expenses', [FinancialController::class, 'index']);
        Route::post('/expenses', [FinancialController::class, 'store']);
        Route::get('/expenses/{id}', [FinancialController::class, 'show']);
        Route::put('/expenses/{id}', [FinancialController::class, 'update']);
        Route::delete('/expenses/{id}', [FinancialController::class, 'destroy']);
        
        // Currency statistics and conversion
        Route::get('/financials/income-by-currency', [FinancialController::class, 'incomeByCurrency']);
        Route::post('/financials/convert-currency', [FinancialController::class, 'convertCurrency']);
        
        // Reports routes
        Route::prefix('reports')->group(function () {
            Route::post('/generate', [ReportController::class, 'generate']);
            Route::get('/', [ReportController::class, 'index']);
            Route::get('/{id}', [ReportController::class, 'show']);
            Route::get('/{id}/download', [ReportController::class, 'download']);
            Route::post('/{id}/send-whatsapp', [ReportController::class, 'sendWhatsApp']);
            Route::delete('/{id}', [ReportController::class, 'destroy']);
        });
        
        // Analytics routes
        Route::prefix('analytics')->group(function () {
            Route::get('/revenue', [AnalyticsController::class, 'revenue']);
            Route::get('/attendance', [AnalyticsController::class, 'attendance']);
            Route::get('/courses', [AnalyticsController::class, 'courses']);
            Route::get('/overview', [AnalyticsController::class, 'overview']);
        });
        
        // More admin routes will be added in later phases
    });

    // Teacher panel routes (accessible only to teachers)
    Route::prefix('teacher')->middleware('auth:api')->group(function () {
        Route::get('/dashboard', [TeacherPanelController::class, 'dashboard']);
        Route::get('/classes', [TeacherPanelController::class, 'getClasses']);
        Route::get('/classes/{id}', [TeacherPanelController::class, 'getClass']);
        Route::put('/classes/{id}/status', [TeacherPanelController::class, 'updateClassStatus']);
        Route::post('/classes/{id}/enter-meet', [TeacherPanelController::class, 'enterMeet']);
        Route::post('/classes/{id}/end', [TeacherPanelController::class, 'endClass']);
        Route::put('/classes/{id}', [TeacherPanelController::class, 'updateClassDetails']);
        Route::post('/classes/{id}/cancel', [TeacherPanelController::class, 'cancelClass']);
        Route::get('/students', [TeacherPanelController::class, 'getStudents']);
        Route::get('/duties', [TeacherPanelController::class, 'duties']);
        Route::get('/profile', [TeacherPanelController::class, 'profile']);
        Route::get('/performance', [TeacherPanelController::class, 'performance']);
        Route::get('/calendar', [TeacherPanelController::class, 'getCalendar']);
        Route::get('/evaluation-options', [TeacherPanelController::class, 'getEvaluationOptions']);
        
        // Teacher trials routes
        Route::get('/trials', [TeacherPanelController::class, 'getTrials']);
        Route::get('/trials/{id}', [TeacherPanelController::class, 'getTrial']);
        Route::post('/trials/{id}/submit-review', [TeacherPanelController::class, 'submitTrialForReview']);
        
        // Teacher availability routes
        Route::get('/availability', [TeacherPanelController::class, 'getAvailability']);
        Route::post('/availability', [TeacherPanelController::class, 'updateAvailability']);
    });
    
    // Admin routes to get teacher availability
    Route::prefix('admin')->middleware('auth:api')->group(function () {
        Route::get('/teachers/{id}/availability', [TeacherController::class, 'getAvailability']);
    });

    // External/public routes (token-based, no auth required)
    Route::prefix('external')->group(function () {
        // External routes will be added in later phases
    });
});
