<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

it('forbids non-admins on every admin route', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create();
    $module = Module::factory()->for($course)->create(['order' => 1]);
    $lesson = Lesson::factory()->for($module)->create(['order' => 1]);
    $coupon = Coupon::factory()->create();

    $routes = [
        ['GET', '/api/admin/courses'],
        ['POST', '/api/admin/courses'],
        ['GET', "/api/admin/courses/{$course->id}"],
        ['PUT', "/api/admin/courses/{$course->id}"],
        ['DELETE', "/api/admin/courses/{$course->id}"],
        ['POST', "/api/admin/courses/{$course->id}/modules"],
        ['PUT', "/api/admin/courses/{$course->id}/modules/reorder"],
        ['PUT', "/api/admin/modules/{$module->id}"],
        ['DELETE', "/api/admin/modules/{$module->id}"],
        ['POST', "/api/admin/modules/{$module->id}/lessons"],
        ['PUT', "/api/admin/modules/{$module->id}/lessons/reorder"],
        ['PUT', "/api/admin/lessons/{$lesson->id}"],
        ['DELETE', "/api/admin/lessons/{$lesson->id}"],
        ['GET', '/api/admin/coupons'],
        ['POST', '/api/admin/coupons'],
        ['PUT', "/api/admin/coupons/{$coupon->id}"],
        ['DELETE', "/api/admin/coupons/{$coupon->id}"],
    ];

    foreach ($routes as [$method, $uri]) {
        $this->actingAs($student)
            ->json($method, $uri)
            ->assertForbidden();
    }
});

it('lists courses with raw fields and counts for admins', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->draft()->create(['title_ar' => 'تكوين تجريبي']);
    $module = Module::factory()->for($course)->create(['order' => 1]);
    Lesson::factory()->for($module)->count(2)->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/courses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title_ar', 'تكوين تجريبي')
        ->assertJsonPath('data.0.status', 'draft')
        ->assertJsonPath('data.0.modules_count', 1)
        ->assertJsonPath('data.0.lessons_count', 2)
        ->assertJsonPath('data.0.enrollments_count', 0);
});

it('creates a course with an auto-generated slug from title_fr', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/courses', [
            'title_fr' => 'Développement Web Complet',
            'title_ar' => 'تطوير الويب الكامل',
            'price_dzd' => 25000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'developpement-web-complet')
        ->assertJsonPath('data.title_ar', 'تطوير الويب الكامل')
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('courses', [
        'slug' => 'developpement-web-complet',
        'price_dzd' => 25000,
    ]);
});

it('creates a course with an explicit slug and status', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/courses', [
            'title_fr' => 'Cours publié',
            'title_ar' => 'دورة منشورة',
            'slug' => 'mon-slug-perso',
            'price_dzd' => 12000,
            'price_eur' => 60,
            'status' => 'published',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'mon-slug-perso')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.price_eur', 60);
});

it('shows a course with nested modules and lessons including raw fields', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $module = Module::factory()->for($course)->create(['order' => 1]);
    Lesson::factory()->for($module)->create([
        'order' => 1,
        'bunny_video_id' => 'video-abc-123',
        'attachments' => [['name' => 'Support', 'path' => 'attachments/support.pdf']],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonPath('data.modules.0.order', 1)
        ->assertJsonPath('data.modules.0.lessons.0.bunny_video_id', 'video-abc-123')
        ->assertJsonPath('data.modules.0.lessons.0.attachments.0.name', 'Support');
});

it('updates a course keeping its own slug', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->draft()->create(['slug' => 'mon-cours']);

    $this->actingAs($admin)
        ->putJson("/api/admin/courses/{$course->id}", [
            'title_fr' => 'Nouveau titre',
            'title_ar' => 'عنوان جديد',
            'slug' => 'mon-cours',
            'price_dzd' => 30000,
            'status' => 'published',
        ])
        ->assertOk()
        ->assertJsonPath('data.title_fr', 'Nouveau titre')
        ->assertJsonPath('data.status', 'published');

    $this->assertDatabaseHas('courses', [
        'id' => $course->id,
        'title_fr' => 'Nouveau titre',
        'status' => 'published',
    ]);
});

it('rejects an update whose slug belongs to another course', function () {
    $admin = User::factory()->admin()->create();
    Course::factory()->create(['slug' => 'taken-slug']);
    $course = Course::factory()->create(['slug' => 'my-slug']);

    $this->actingAs($admin)
        ->putJson("/api/admin/courses/{$course->id}", [
            'title_fr' => 'Titre',
            'title_ar' => 'عنوان',
            'slug' => 'taken-slug',
            'price_dzd' => 1000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('deletes a course', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/courses/{$course->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('courses', ['id' => $course->id]);
});

it('returns 422 when title_ar is missing', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/courses', [
            'title_fr' => 'Sans titre arabe',
            'price_dzd' => 5000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title_ar']);
});
