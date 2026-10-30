<?php

use App\Http\Controllers\Api\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Api\Admin\CashOrderController as AdminCashOrderController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Api\Admin\InvoicingSettingsController as AdminInvoicingSettingsController;
use App\Http\Controllers\Api\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\Admin\ModuleController as AdminModuleController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LearnController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlaybackController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\ReflexSheetController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

// Public catalog
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course:slug}', [CourseController::class, 'show']);

// Approved reviews + rating summary (public; writing requires an enrolment)
Route::get('/courses/{course:slug}/reviews', [ReviewController::class, 'index']);

// Playback: free-preview lessons are playable by guests; everything else
// requires an enrolled (or admin) authenticated user — enforced in-controller.
Route::get('/lessons/{lesson}/playback', [PlaybackController::class, 'show']);

// Payment provider webhooks (server-to-server, signature-verified in-controller)
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe']);

// Reference data: the 52 provinces, for partner filters and the admin editor.
Route::get('/provinces', [ProvinceController::class, 'index'])->middleware('throttle:60,1');

// Public identity and contact details (footer, FAQ, payment pages). Only what
// the admin has filled, and never the fiscal-only fields.
Route::get('/business', [BusinessController::class, 'show'])->middleware('throttle:60,1');

// Live pricing quote (guest-friendly; owned modules excluded when authenticated)
Route::post('/checkout/quote', [CheckoutController::class, 'quote']);
Route::get('/checkout/methods', [CheckoutController::class, 'methods'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateMe']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);

    // Course player (enrolled students / admins)
    Route::get('/courses/{course:slug}/learn', [LearnController::class, 'show']);

    // Per-module action checklists + the student's own tasks
    Route::get('/courses/{course:slug}/checklists', [ChecklistController::class, 'index']);
    Route::put('/checklist-items/{item}', [ChecklistController::class, 'toggle'])->whereNumber('item');
    Route::post('/modules/{module}/tasks', [ChecklistController::class, 'storeTask'])->whereNumber('module');
    Route::patch('/tasks/{task}', [ChecklistController::class, 'updateTask'])->whereNumber('task');
    Route::delete('/tasks/{task}', [ChecklistController::class, 'destroyTask'])->whereNumber('task');
    Route::post('/lessons/{lesson}/progress', [ProgressController::class, 'heartbeat']);
    Route::post('/lessons/{lesson}/complete', [ProgressController::class, 'complete']);
    Route::get('/lessons/{lesson}/attachments/{index}', [AttachmentController::class, 'download'])
        ->whereNumber('index');
    // Printable safety sheet (module 13.2) — same access gate as the lesson.
    Route::get('/lessons/{lesson}/reflex-sheet', [ReflexSheetController::class, 'download']);

    // Checkout + orders
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->whereNumber('order');
    Route::post('/orders/{order}/receipt', [OrderController::class, 'uploadReceipt']);

    // Leave / update your own review (enrolled students only)
    Route::post('/courses/{course:slug}/reviews', [ReviewController::class, 'store']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('courses', AdminCourseController::class);

    Route::post('courses/{course}/modules', [AdminModuleController::class, 'store']);
    Route::put('courses/{course}/modules/reorder', [AdminModuleController::class, 'reorder']);
    Route::put('modules/{module}', [AdminModuleController::class, 'update']);
    Route::delete('modules/{module}', [AdminModuleController::class, 'destroy']);

    Route::post('modules/{module}/lessons', [AdminLessonController::class, 'store']);
    Route::put('modules/{module}/lessons/reorder', [AdminLessonController::class, 'reorder']);
    Route::put('lessons/{lesson}', [AdminLessonController::class, 'update']);
    Route::delete('lessons/{lesson}', [AdminLessonController::class, 'destroy']);

    Route::apiResource('coupons', AdminCouponController::class)->except(['show']);
    Route::apiResource('campaigns', AdminCampaignController::class)->except(['show']);
    Route::apiResource('partners', AdminPartnerController::class)->except(['show']);
    Route::put('modules/{module}/partners', [AdminPartnerController::class, 'syncModule']);

    // Order review
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/export', [AdminOrderController::class, 'export']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
    Route::get('orders/{order}/receipt', [AdminOrderController::class, 'receipt'])->whereNumber('order');
    Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->whereNumber('order');
    Route::post('orders/{order}/reject', [AdminOrderController::class, 'reject'])->whereNumber('order');
    Route::post('orders/{order}/grant', [AdminOrderController::class, 'grant'])->whereNumber('order');

    // Invoicing: seller settings + issued invoices (factura on demand)
    Route::get('invoicing/settings', [AdminInvoicingSettingsController::class, 'show']);
    Route::put('invoicing/settings', [AdminInvoicingSettingsController::class, 'update']);
    Route::get('invoices', [AdminInvoiceController::class, 'index']);
    Route::post('orders/{order}/invoice', [AdminInvoiceController::class, 'store'])->whereNumber('order');
    Route::put('invoices/{invoice}', [AdminInvoiceController::class, 'update'])->whereNumber('invoice');
    Route::get('invoices/{invoice}/pdf', [AdminInvoiceController::class, 'pdf'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/send', [AdminInvoiceController::class, 'send'])
        ->whereNumber('invoice')
        ->middleware('throttle:10,1');

    // Review moderation (admins approve/reject what students wrote — never author it)
    Route::get('reviews', [AdminReviewController::class, 'index']);
    Route::post('reviews/{review}/moderate', [AdminReviewController::class, 'moderate'])->whereNumber('review');
    Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->whereNumber('review');

    // Dashboard + students
    Route::get('stats', [AdminStatsController::class, 'index']);
    Route::get('students', [AdminStudentController::class, 'index']);
    Route::get('students/{user}', [AdminStudentController::class, 'show'])->whereNumber('user');
    Route::post('students/{user}/cash-orders', [AdminCashOrderController::class, 'store'])->whereNumber('user');
});
