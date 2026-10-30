<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;

/**
 * C31: every 403 and 404 says something, in the reader's language. A bare
 * abort() sent {"message": ""} in production, and a missing route model
 * leaked the model class name and id.
 */
beforeEach(fn () => config(['app.debug' => false]));

it('explains a refused lesson instead of sending an empty message', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_free_preview' => false]);

    $this->actingAs(User::factory()->create())
        ->withHeader('Accept-Language', 'es')
        ->postJson("/api/lessons/{$lesson->id}/complete")
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes acceso a este contenido.']);
});

it('does not name the model behind a missing record', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withHeader('Accept-Language', 'es')
        ->getJson('/api/orders/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'No encontrado.']);

    expect($response->getContent())->not->toContain('App\\\\Models');
});

it('keeps an explicit message when the code gave one', function () {
    $student = User::factory()->create();
    $order = Order::factory()->for($student)->create();

    // OrderController::invoice aborts with its own explanation.
    $this->actingAs($student)
        ->withHeader('Accept-Language', 'es')
        ->getJson("/api/orders/{$order->id}/invoice")
        ->assertNotFound()
        ->assertJsonPath('message', __('No invoice has been issued for this order.', [], 'es'));
});
