<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

/**
 * Shared access gates for the course player endpoints. Access is granted
 * per MODULE since students can buy modules à la carte; an enrollment row
 * with module_id NULL means full-course access.
 */
trait AuthorizesCourseAccess
{
    /**
     * Abort with 403 unless the user is an admin or owns at least one
     * module of the course (player entry).
     */
    private function authorizeCourseAccess(User $user, Course $course): void
    {
        abort_unless($user->isAdmin() || $user->isEnrolledIn($course), 403);
    }

    /**
     * Abort with 403 unless the user is an admin or owns the lesson's
     * module. 404s on orphan lessons (no module/course) rather than
     * leaking their existence.
     *
     * `$allowFreePreview` mirrors what the player payload already exposes:
     * a free-preview lesson is playable even inside an unowned module, so the
     * endpoints that follow playback (progress, completion) must accept it
     * too — otherwise the UI offers actions that silently 403.
     */
    private function authorizeLessonAccess(User $user, Lesson $lesson, bool $allowFreePreview = false): void
    {
        $module = $lesson->module;

        abort_if($module === null || $module->course === null, 404);

        if ($allowFreePreview && $lesson->is_free_preview) {
            return;
        }

        abort_unless($user->isAdmin() || $user->hasAccessToModule($module), 403);
    }
}
