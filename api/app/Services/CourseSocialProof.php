<?php

namespace App\Services;

use App\Models\Course;

/**
 * The real, verifiable numbers a public course page shows: distinct students
 * with an active enrolment, the average of APPROVED reviews only (null when
 * there are none — never a placeholder score), and the running promotion.
 * Shared by the course API and the server-rendered page, so both say the same.
 */
final class CourseSocialProof
{
    public static function attach(Course $course): void
    {
        $students = $course->enrollments()
            ->active()
            ->distinct()
            ->count('user_id');

        $approved = $course->reviews()->approved();

        $course->setAttribute('students_count', $students);
        $course->setAttribute('reviews_count', (clone $approved)->count());
        $course->setAttribute('rating', (clone $approved)->avg('rating'));
        $course->setAttribute(
            'campaign',
            app(PricingService::class)->campaignPayload($course->activeCampaign()),
        );
    }
}
