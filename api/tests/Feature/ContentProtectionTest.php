<?php

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Support\Protection\ContentFingerprint;
use Illuminate\Support\Facades\Artisan;

/** A course with one owned module and one locked one, each with a lesson. */
function protectedCourse(): array
{
    $course = Course::factory()->create(['slug' => 'curso-protegido']);
    $owned = Module::factory()->for($course)->create(['order' => 1, 'description_es' => '**Objetivo:** comprar bien el solar.']);
    $locked = Module::factory()->for($course)->create(['order' => 2, 'description_es' => '**Objetivo:** financiar la obra.']);
    $lesson = Lesson::factory()->for($owned)->create(['content_es' => "El solar se compra con números.\n\nLa repercusión manda en el precio."]);
    $lockedLesson = Lesson::factory()->for($locked)->create(['content_es' => 'Texto de pago.', 'is_free_preview' => false]);
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $owned->id]);

    return compact('course', 'owned', 'locked', 'lesson', 'lockedLesson', 'student');
}

it('sends the outline without a single line of the course text', function () {
    ['course' => $course, 'student' => $student] = protectedCourse();

    $response = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    expect($response->getContent())
        ->not->toContain('se compra con números')
        ->not->toContain('comprar bien el solar');
    $response->assertJsonPath('data.modules.0.lessons.0.content_html', null)
        ->assertJsonPath('data.modules.0.description_html', null);
});

it('serves a lesson\'s text on its own, behind the module gate', function () {
    ['course' => $course, 'lesson' => $lesson, 'lockedLesson' => $lockedLesson, 'student' => $student] = protectedCourse();

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")
        ->assertOk()
        ->assertJsonPath('data.content_html', fn (string $html) => str_contains($html, 'se compra con números'))
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/lessons/{$lockedLesson->id}/content")
        ->assertForbidden();

    // A lesson of another course cannot be read through this one.
    $other = Lesson::factory()->create();
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/lessons/{$other->id}/content")->assertNotFound();
});

it('serves a module\'s introduction to all, its sections to owners only', function () {
    ['course' => $course, 'owned' => $owned, 'locked' => $locked, 'student' => $student] = protectedCourse();

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$owned->id}/content")
        ->assertOk()
        ->assertJsonPath('data.description_html', fn (string $html) => str_contains($html, 'comprar bien el solar'));

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$locked->id}/content")
        ->assertOk()
        ->assertJsonPath('data.blocks', []);
});

it('marks the text with its reader, invisibly, and reads the mark back', function () {
    config(['protection.fingerprint' => true]);
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = protectedCourse();

    $html = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")
        ->assertOk()->json('data.content_html');

    // What a reader sees is unchanged once the invisible characters are set aside.
    expect(preg_replace('/[\x{2061}-\x{2063}]/u', '', $html))->toContain('El solar se compra con números.');
    expect(ContentFingerprint::trace(strip_tags($html)))->toBe([$student->id => 2]);

    // Another account's mark is never mistaken for this one, and a forged one is ignored.
    $forged = "\u{2061}".str_repeat("\u{2063}", 32);
    expect(ContentFingerprint::trace($forged))->toBe([]);

    $file = tempnam(sys_get_temp_dir(), 'copy');
    file_put_contents($file, strip_tags($html));
    expect(Artisan::call('content:trace', ['file' => $file]))->toBe(0);
    expect(Artisan::output())->toContain($student->email);
    unlink($file);
});

it('stops a script walking the course, and tells the owner once', function () {
    config(['protection.per_minute' => 3]);
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = protectedCourse();
    $url = "/api/courses/{$course->slug}/lessons/{$lesson->id}/content";

    foreach (range(1, 3) as $i) {
        $this->actingAs($student)->getJson($url)->assertOk();
    }

    $this->actingAs($student)->getJson($url)->assertStatus(429)
        ->assertJsonPath('message', fn (string $message) => $message !== '')
        ->assertHeader('Retry-After');
    $this->actingAs($student)->getJson($url)->assertStatus(429);

    expect(AuditLog::query()->where('action', 'content.rate_limited')->where('user_id', $student->id)->count())->toBe(1);
});

it('flags an account that opens an unusual number of pages in a day', function () {
    config(['protection.daily_alert' => 2]);
    ['course' => $course, 'owned' => $owned, 'lesson' => $lesson, 'student' => $student] = protectedCourse();

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")->assertOk();
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$owned->id}/content")->assertOk();
    expect(AuditLog::query()->where('action', 'content.unusual_reading')->exists())->toBeFalse();

    // A third distinct page crosses the line; reading goes on.
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/checklists")->assertOk();
    expect(AuditLog::query()->where('action', 'content.unusual_reading')->where('user_id', $student->id)->count())->toBe(1);
});

it('does not meter the people who write the course', function () {
    config(['protection.per_minute' => 1]);
    ['course' => $course, 'lesson' => $lesson] = protectedCourse();
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (range(1, 3) as $i) {
        $this->actingAs($admin)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")->assertOk();
    }
});
