<?php

use App\Mail\OfferMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserOffer;
use App\Services\OfferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('shows nothing while an offer is not written and switched on', function () {
    $student = paidStudent(now()->subHours(30));

    // The three slots exist, empty and off.
    expect(Offer::query()->pluck('stage')->all())->toEqualCanonicalizing(Offer::STAGES)
        ->and(offerStages($student))->toBe([]);

    // Written but off: still nothing.
    Offer::query()->where('stage', 'after_purchase')->update(['title_es' => 'Oferta', 'url' => 'https://example.es']);
    expect(offerStages($student))->toBe([]);

    // On but saying nothing: still nothing.
    Offer::query()->where('stage', 'after_purchase')->update(['is_active' => true, 'title_es' => null]);
    expect(offerStages($student))->toBe([]);
});

it('offers the after-purchase offer from 24 to 48 hours after the first purchase', function () {
    liveOffer('after_purchase');
    $paidAt = now()->subHours(23)->startOfSecond();
    $student = paidStudent($paidAt);

    expect(offerStages($student))->toBe([]);

    $this->travel(2)->hours();   // 25 h after the purchase
    $response = $this->actingAs($student)->getJson('/api/me/offers')->assertOk();
    expect($response->json('data.0.stage'))->toBe('after_purchase')
        ->and($response->json('data.0.title'))->toBe('[DEMO] Oferta after_purchase')
        ->and($response->json('data.0.body_html'))->toContain('<strong>de la oferta</strong>')
        ->and($response->json('data.0.url'))->toBe('https://example.es/oferta')
        // The deadline is the purchase + 48 h, whenever it is read.
        ->and($response->json('data.0.closes_at'))->toBe($paidAt->copy()->addHours(48)->toIso8601String());

    $this->travel(24)->hours();  // 49 h after the purchase
    expect(offerStages($student))->toBe([]);
});

it('offers the milestone offer once the first module is finished, not before', function () {
    liveOffer('milestone');
    $course = Course::factory()->create(['status' => 'published']);
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $lessons = Lesson::factory()->count(2)->sequence(['order' => 1], ['order' => 2])->create(['module_id' => $module->id]);
    $student = paidStudent();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    LessonProgress::factory()->create(['user_id' => $student->id, 'lesson_id' => $lessons[0]->id, 'completed_at' => now()]);
    expect(offerStages($student))->toBe([]);

    // Started but not finished: a progress row without completed_at does not count.
    LessonProgress::factory()->create(['user_id' => $student->id, 'lesson_id' => $lessons[1]->id, 'completed_at' => null]);
    expect(offerStages($student))->toBe([]);

    LessonProgress::query()->where('lesson_id', $lessons[1]->id)->update(['completed_at' => now()]);
    expect(offerStages($student))->toBe(['milestone']);
});

it('offers the end-of-course offer when the final exam is passed, ahead of the others', function () {
    liveOffer('after_purchase', ['delay_hours' => 0, 'available_hours' => null]);
    liveOffer('course_end');
    $student = paidStudent();
    $exam = Quiz::factory()->create(['kind' => 'final']);

    QuizAttempt::query()->create(['quiz_id' => $exam->id, 'user_id' => $student->id, 'answers' => [], 'score' => 10, 'total' => 40, 'passed' => false, 'started_at' => now(), 'submitted_at' => now()]);
    expect(offerStages($student))->toBe(['after_purchase']);

    QuizAttempt::query()->create(['quiz_id' => $exam->id, 'user_id' => $student->id, 'answers' => [], 'score' => 36, 'total' => 40, 'passed' => true, 'started_at' => now(), 'submitted_at' => now()]);
    expect(offerStages($student))->toBe(['course_end', 'after_purchase']);
});

it('records seen and click once, and never shows a closed offer again', function () {
    liveOffer('after_purchase', ['delay_hours' => 0]);
    $student = paidStudent();
    $this->postJson('/api/me/offers/after_purchase/seen')->assertUnauthorized();
    expect(offerStages($student))->toBe(['after_purchase']);

    $this->actingAs($student)->postJson('/api/me/offers/after_purchase/seen')->assertNoContent();
    $firstSeen = UserOffer::query()->where('user_id', $student->id)->value('seen_at');
    $this->travel(1)->hours();
    $this->actingAs($student)->postJson('/api/me/offers/after_purchase/seen')->assertNoContent();
    expect(UserOffer::query()->where('user_id', $student->id)->value('seen_at'))->toEqual($firstSeen);

    $this->actingAs($student)->postJson('/api/me/offers/after_purchase/dismiss')->assertNoContent();
    expect(offerStages($student))->toBe([]);

    $this->actingAs($student)->postJson('/api/me/offers/after_purchase/delete')->assertNotFound();
});

it('does not sell a course to a student who already has it in full', function () {
    $big = Course::factory()->create(['status' => 'published', 'slug' => 'formacion-excel']);
    Module::factory()->create(['course_id' => $big->id]);
    liveOffer('after_purchase', ['delay_hours' => 0, 'url' => null, 'course_id' => $big->id]);
    $student = paidStudent();

    $response = $this->actingAs($student)->getJson('/api/me/offers')->assertOk();
    expect($response->json('data.0.course.slug'))->toBe('formacion-excel')
        ->and($response->json('data.0.url'))->toBeNull();

    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $big->id, 'module_id' => null]);
    expect(offerStages($student))->toBe([]);
});

it('e-mails an offer once when its window opens, and respects the unsubscribe link', function () {
    Mail::fake();
    liveOffer('after_purchase');
    $student = paidStudent(now()->subHours(25));
    $optedOut = paidStudent(now()->subHours(25));
    $optedOut->forceFill(['offer_emails_opted_out_at' => now()])->save();
    $tooEarly = paidStudent(now()->subHours(2));
    $admin = User::factory()->admin()->create();
    Order::factory()->create(['user_id' => $admin->id, 'status' => 'paid', 'paid_at' => now()->subHours(25)]);

    $this->artisan('offers:send')->assertSuccessful();
    $this->artisan('offers:send')->assertSuccessful();

    Mail::assertQueued(OfferMail::class, 1);
    Mail::assertQueued(OfferMail::class, fn (OfferMail $mail) => $mail->hasTo($student->email));

    // What it says: the offer, its deadline, the button, and the way out.
    $mail = new OfferMail(Offer::query()->where('stage', 'after_purchase')->first(), UserOffer::query()->where('user_id', $student->id)->first());
    $mail->assertSeeInHtml('[DEMO] Oferta after_purchase')
        ->assertSeeInHtml('<strong>de la oferta</strong>', false)
        ->assertSeeInHtml('Disponible hasta el')
        ->assertSeeInHtml('/api/offers/open/')
        ->assertSeeInHtml('/api/offers/unsubscribe/'.$student->id);
    expect(UserOffer::query()->where('user_id', $tooEarly->id)->value('emailed_at'))->toBeNull();
});

it('never e-mails a moment from long before, nor an offer that is off or not for e-mail', function () {
    Mail::fake();
    liveOffer('milestone', ['is_active' => false]);
    liveOffer('course_end', ['send_email' => false]);
    liveOffer('after_purchase', ['delay_hours' => 0, 'available_hours' => null]);
    $old = paidStudent(now()->subDays(5));
    // Its moment is recorded (the app shows the offer), but it is days old: no e-mail.
    UserOffer::query()->create(['user_id' => $old->id, 'stage' => 'after_purchase', 'triggered_at' => now()->subDays(5)]);
    UserOffer::query()->create(['user_id' => $old->id, 'stage' => 'milestone', 'triggered_at' => now()]);
    UserOffer::query()->create(['user_id' => $old->id, 'stage' => 'course_end', 'triggered_at' => now()]);
    // Closed by the student before the e-mail went: no e-mail either.
    $closed = paidStudent(now()->subHour());
    UserOffer::query()->create(['user_id' => $closed->id, 'stage' => 'after_purchase', 'triggered_at' => now()->subHour(), 'dismissed_at' => now()]);

    $this->artisan('offers:send')->assertSuccessful();

    Mail::assertNothingQueued();
    expect(offerStages($old))->toBe(['course_end', 'after_purchase']);
});

it('sends nothing when another run has just claimed the e-mail', function () {
    Mail::fake();
    liveOffer('after_purchase');
    $student = paidStudent(now()->subHours(25));
    app(OfferService::class)->sync($student);

    // Another run e-mails it between this run's read and its claim.
    $raced = false;
    DB::listen(function ($query) use (&$raced) {
        if (! $raced && str_starts_with($query->sql, 'select * from `user_offers`')) {
            $raced = true;
            DB::table('user_offers')->update(['emailed_at' => now()->subMinute()]);
        }
    });

    $this->artisan('offers:send')->assertSuccessful();

    expect($raced)->toBeTrue();
    Mail::assertNothingQueued();
});

it('serves the e-mail links only when signed', function () {
    liveOffer('after_purchase', ['delay_hours' => 0]);
    $student = paidStudent();
    $this->actingAs($student)->getJson('/api/me/offers')->assertOk();
    $moment = UserOffer::query()->where('user_id', $student->id)->firstOrFail();
    auth()->forgetGuards();

    $this->get("/api/offers/open/{$moment->id}")->assertForbidden();
    $this->get("/api/offers/unsubscribe/{$student->id}")->assertForbidden();

    $this->get(URL::signedRoute('offers.open', ['moment' => $moment->id]))->assertRedirect('https://example.es/oferta');
    expect($moment->fresh()->clicked_at)->not->toBeNull();

    $this->get(URL::signedRoute('offers.unsubscribe', ['user' => $student->id]))->assertOk()->assertSee('Ya no recibirás ofertas por correo.');
    expect($student->fresh()->offer_emails_opted_out_at)->not->toBeNull();
});

it('lets the admin write the three offers, and refuses one switched on with nothing to sell', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::query()->where('stage', 'course_end')->firstOrFail();
    $base = ['delay_hours' => 0, 'available_hours' => null, 'modules_required' => 1, 'send_email' => true];

    $list = $this->actingAs($admin)->getJson('/api/admin/offers')->assertOk();
    expect(collect($list->json('data'))->pluck('stage')->all())->toBe(Offer::STAGES)
        ->and($list->json('data.0.stats.reached'))->toBe(0);

    $this->actingAs($admin)->putJson("/api/admin/offers/{$offer->id}", $base + ['is_active' => true])
        ->assertUnprocessable()->assertJsonValidationErrors(['title_es', 'url']);
    $this->actingAs($admin)->putJson("/api/admin/offers/{$offer->id}", $base + ['is_active' => false, 'url' => 'https://example.es', 'course_id' => Course::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['url']);

    $this->actingAs($admin)->putJson("/api/admin/offers/{$offer->id}", $base + [
        'is_active' => true, 'title_es' => 'Formación Excel', 'body_es' => 'La plantilla, a fondo.', 'url' => 'https://example.es/excel', 'price_eur' => 990,
    ])->assertOk()->assertJsonPath('data.is_live', true);
    expect($offer->fresh()->activated_at)->not->toBeNull();

    $this->actingAs(User::factory()->create())->getJson('/api/admin/offers')->assertForbidden();
});
