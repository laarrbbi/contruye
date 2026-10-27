<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        // The month boundary is the business calendar's, not UTC's.
        $monthStart = now()->setTimezone(config('app.business_timezone'))->startOfMonth();

        $revenueThisMonth = Order::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $ordersByMethod = Order::query()
            ->selectRaw("method,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid")
            ->groupBy('method')
            ->get()
            ->keyBy('method')
            ->map(fn ($row) => ['total' => (int) $row->total, 'paid' => (int) $row->paid]);

        return response()->json(['data' => [
            'revenue_this_month' => [
                'EUR' => (int) ($revenueThisMonth['EUR'] ?? 0),
            ],
            // Always a JSON object keyed by method — an empty collection
            // would serialize as [] and change type on a fresh install.
            'orders_by_method' => $ordersByMethod->isEmpty() ? new stdClass : $ordersByMethod,
            'orders_awaiting_review' => Order::where('status', 'awaiting_review')->count(),
            // Receipts the buyer sent more than 48 h ago and nobody has looked at.
            'orders_awaiting_review_overdue' => Order::where('status', 'awaiting_review')
                ->where('receipt_uploaded_at', '<=', now()->subHours(Order::RECEIPT_REVIEW_HOURS))
                ->count(),
            'enrollments_total' => Enrollment::count(),
            'enrollments_this_month' => Enrollment::where('created_at', '>=', $monthStart)->count(),
            'students_total' => User::where('role', 'student')->count(),
            'completion_rate' => $this->completionRate(),
            'recent_orders' => AdminOrderResource::collection(
                Order::with(['user', 'course'])->latest()->limit(10)->get(),
            ),
        ]]);
    }

    /**
     * Average completion (%) across active enrollments: for each enrollment,
     * completed lessons in that course / total lessons in that course.
     *
     * Aggregated entirely in SQL. The previous version pulled every
     * enrollment and every user/course completion group into PHP and looped
     * — at 26k enrollments over 275k progress rows that alone was ~340 ms of
     * the dashboard's response.
     */
    private function completionRate(): int
    {
        $lessonsPerCourse = DB::table('lessons')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->selectRaw('modules.course_id, COUNT(*) AS lessons_total')
            ->groupBy('modules.course_id');

        $completedPerUserCourse = DB::table('lesson_progress')
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->whereNotNull('lesson_progress.completed_at')
            ->selectRaw('lesson_progress.user_id, modules.course_id, COUNT(*) AS done')
            ->groupBy('lesson_progress.user_id', 'modules.course_id');

        // One enrollment row = one sample, exactly as before: a student who
        // bought three modules separately weighs three times.
        $row = DB::table('enrollments')
            ->joinSub($lessonsPerCourse, 'course_lessons', fn ($join) => $join
                ->on('course_lessons.course_id', '=', 'enrollments.course_id'))
            ->leftJoinSub($completedPerUserCourse, 'progress', fn ($join) => $join
                ->on('progress.user_id', '=', 'enrollments.user_id')
                ->on('progress.course_id', '=', 'enrollments.course_id'))
            ->where(fn ($q) => $q->whereNull('enrollments.expires_at')->orWhere('enrollments.expires_at', '>', now()))
            ->where('course_lessons.lessons_total', '>', 0)
            ->selectRaw('COUNT(*) AS samples, SUM(LEAST(1, COALESCE(progress.done, 0) / course_lessons.lessons_total)) AS ratio_sum')
            ->first();

        $samples = (int) ($row->samples ?? 0);

        return $samples === 0 ? 0 : (int) round(((float) $row->ratio_sum) / $samples * 100);
    }
}
