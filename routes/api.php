<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ClusterController;
use App\Http\Controllers\Api\CustomerLeadController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentSchemeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\FollowUpHistoryController;
use App\Http\Controllers\Api\SystemLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PublicInfoController;
use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Route publik (konsumen) — tanpa autentikasi
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/units', [PublicInfoController::class, 'publicUnits']);
    Route::get('/units/{id}', [PublicInfoController::class, 'publicUnitDetail']);
    Route::get('/payment-schemes', [PublicInfoController::class, 'paymentSchemes']);
    Route::get('/sales-contacts', [PublicInfoController::class, 'salesContacts']);
    Route::get('/news-gallery', [PublicInfoController::class, 'newsAndGallery']);
    Route::get('/featured-units', [PublicInfoController::class, 'featuredUnits']);
    Route::get('/settings', [SettingController::class, 'publicSettings']);
    Route::get('/auth-config', [GoogleAuthController::class, 'config']);
    Route::post('/contact', [PublicInfoController::class, 'submitContactForm']);
});

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Route internal — seluruhnya requires autentikasi Sanctum
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'force.password'])->group(function () {
    // Autentikasi & sesi — daftar sesi hanya milik Super Admin.
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/sessions', [AuthController::class, 'sessions'])->middleware('role:super_admin');

    // Notifikasi — setiap aktor atas namanya sendiri
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Profil mandiri — setiap aktor atas akunnya sendiri (Wireframe 2.47)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update']);

    // Penautan akun Google atas nama sendiri (halaman Profil)
    Route::post('auth/google/link', [GoogleAuthController::class, 'linkStart']);
    Route::delete('auth/google/link', [GoogleAuthController::class, 'unlink']);

    // b. Manajemen Data Unit — Admin Sales hanya melihat, Super Admin yang mengubah.
    Route::middleware('role:super_admin,admin_sales')->group(function () {
        Route::get('units/clusters', [UnitController::class, 'clusters']);
        Route::apiResource('units', UnitController::class)->only(['index', 'show']);
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('units', UnitController::class)->only(['store', 'update', 'destroy']);
    });
    Route::get('units-available', [UnitController::class, 'availableUnits']);

    // b.1 Cluster — Super Admin dapat menambahkan cluster, Admin Sales hanya melihat.
    Route::middleware('role:super_admin,admin_sales')->group(function () {
        Route::get('clusters', [ClusterController::class, 'index']);
        Route::get('projects', [ClusterController::class, 'projects']);
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::post('clusters', [ClusterController::class, 'store']);
    });

    // h. Manajemen Data Sales
    Route::middleware('role:sales,super_admin,admin_sales')
        ->get('sales/{id}/performance', [SalesController::class, 'performance']);

    Route::middleware('role:super_admin,admin_sales')->group(function () {
        Route::post('sales/{id}/reset-password', [SalesController::class, 'resetPassword']);
        Route::apiResource('sales', SalesController::class);
    });

    // e. Calon konsumen, follow up, pemesanan, dan pembayaran.
    // Semua aktor internal boleh membaca; Admin Sales tidak boleh mengelola (hanya lihat),
    // sedangkan Sales tetap pencatat utama follow up, booking, dan pembayaran.
    Route::middleware('role:sales,super_admin,admin_sales')->group(function () {
        Route::apiResource('customer-leads', CustomerLeadController::class)->only(['index', 'show']);
        Route::apiResource('follow-ups', FollowUpHistoryController::class)->only(['index', 'show']);
        Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
        Route::apiResource('payments', PaymentController::class)->only(['index', 'show']);
    });

    Route::middleware('role:sales,super_admin')->group(function () {
        Route::apiResource('customer-leads', CustomerLeadController::class)->except(['index', 'show']);
        Route::apiResource('follow-ups', FollowUpHistoryController::class)->except(['index', 'show', 'update']);
        Route::apiResource('bookings', BookingController::class)->except(['index', 'show']);
        Route::apiResource('payments', PaymentController::class)->except(['index', 'show']);
    });

    Route::put('payments/{id}/verify', [PaymentController::class, 'verify'])
        ->middleware('role:super_admin');

    // Skema pembayaran — daftar dibaca semua peran internal (form booking sales),
    // hanya super admin yang berhak mengubah.
    Route::get('payment-schemes', [PaymentSchemeController::class, 'index'])
        ->middleware('role:sales,super_admin,admin_sales');
    Route::middleware('role:super_admin,admin_sales')
        ->apiResource('payment-schemes', PaymentSchemeController::class)->except(['index']);
    Route::post('payment-schemes/{id}/simulate', [PaymentSchemeController::class, 'simulate']);

    // Log aktivitas sistem — hanya Super Admin.
    Route::apiResource('system-logs', SystemLogController::class)->only(['index', 'store', 'show'])
        ->middleware('role:super_admin');

    // f. Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/super-admin', [DashboardController::class, 'superAdminDashboard'])
            ->middleware('role:super_admin');
        Route::get('/admin-sales', [DashboardController::class, 'adminSalesDashboard'])
            ->middleware('role:admin_sales,super_admin');
        Route::get('/sales', [DashboardController::class, 'salesDashboard'])
            ->middleware('role:sales,super_admin');
    });

    // f. Laporan
    Route::prefix('reports')->middleware('role:super_admin,admin_sales')->group(function () {
        Route::get('/sales', [ReportController::class, 'salesReport']);
        Route::get('/settlement', [ReportController::class, 'settlementReport']);
        Route::get('/performance', [ReportController::class, 'salesPerformanceReport']);
        Route::get('/consumers', [ReportController::class, 'consumerReport']);
        Route::get('/export', [ReportController::class, 'exportReport']);
        Route::post('/summarize', [ReportController::class, 'summarize']);
    });

    // Laporan milik sales yang login (Wireframe 2.46)
    Route::middleware('role:sales')->group(function () {
        Route::get('reports/my-sales', [ReportController::class, 'mySalesReport']);
        Route::get('reports/my-sales/export', [ReportController::class, 'exportMySalesReport']);
    });

    // Pengaturan sistem (Wireframe 2.34) & tampilan halaman publik
    Route::middleware('role:super_admin')->group(function () {
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::post('settings/public-image', [SettingController::class, 'updatePublicImage']);

        Route::apiResource('public-posts', PublicPostController::class)->except(['show']);
    });
});
