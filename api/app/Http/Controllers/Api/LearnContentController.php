<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\LearnCourseResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Support\Protection\ContentFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The course's texts, one page at a time: a lesson (its text, bullets and
 * blocks) or a module (its introduction and its sections). The outline
 * (/learn) carries none of them, so reading the course means one request per
 * page, each behind the access gate, the content meter and the reader's mark.
 */
class LearnContentController extends Controller
{
    use AuthorizesCourseAccess;

    public function lesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        $lesson->load(['module.partners.provinces', 'contentBlocks']);
        $module = $lesson->module;
        abort_if($module === null || $module->course_id !== $course->id, 404);

        $content = $this->resource($user, $course)->lessonContent($lesson, $module);
        abort_if($content === null, 403);

        $content['content_html'] = ContentFingerprint::stamp($content['content_html'], $user);
        $content['blocks'] = $this->stampBlocks($content['blocks'], $user);

        return response()->json(['data' => $content]);
    }

    public function module(Request $request, Course $course, Module $module): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);
        abort_if($module->course_id !== $course->id, 404);

        $module->load(['contentBlocks', 'partners.provinces']);

        $content = $this->resource($user, $course)->moduleContent($module);
        $content['description_html'] = ContentFingerprint::stamp($content['description_html'], $user);
        $content['blocks'] = $this->stampBlocks($content['blocks'], $user);

        return response()->json(['data' => $content]);
    }

    private function resource(User $user, Course $course): LearnCourseResource
    {
        $accessible = $user->isAdmin()
            ? $course->modules()->pluck('id')
            : $user->accessibleModuleIds($course);

        return new LearnCourseResource($course, new Collection, $accessible);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function stampBlocks(array $blocks, User $user): array
    {
        return array_map(function (array $block) use ($user) {
            if (is_string($block['body_html'] ?? null)) {
                $block['body_html'] = ContentFingerprint::stamp($block['body_html'], $user);
            }

            return $block;
        }, $blocks);
    }
}
