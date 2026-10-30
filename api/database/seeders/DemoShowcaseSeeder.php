<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Partner;
use App\Models\Province;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * LOCAL DEMO DATA — opt-in, never part of the default seed.
 *
 *     docker compose exec php php artisan db:seed --class=DemoShowcaseSeeder
 *
 * Fills the features that are empty on a fresh install (partners, a running
 * promotion, reviews, mixed access levels) so the app can be clicked through
 * end to end.
 *
 * Every fictional entity is prefixed "[DEMO]" on purpose: these are NOT real
 * providers, real customers or a real promotion, and must never be mistaken
 * for them if this data ever turns up outside a dev machine.
 */
class DemoShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoShowcaseSeeder writes invented data and refuses to run in production.');
        }

        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->first();

        if ($course === null) {
            $this->command?->error('Seed the curriculum first (CurriculumV2Seeder).');

            return;
        }

        $modules = $course->modules()->orderBy('order')->get()->keyBy('order');

        // --- Partners, matched to the partner_slot categories of the modules ---
        // Coverage is expressed in province codes so the demo exercises the real
        // cases: a multi-province provider, a single-province one, and a national.
        $partners = [
            ['name' => '[DEMO] Notaría Colaboradora', 'category' => 'notario', 'provinces' => [28, 45, 19],
                'phone' => '600000001', 'email' => 'demo-notaria@example.es', 'website' => 'https://example.es',
                'description_es' => 'Compraventa de suelo, obra nueva, división horizontal y escrituras de venta.',
                'module' => 1, 'note' => 'Para la compra del solar y todas las escrituras del recorrido'],
            ['name' => '[DEMO] Asesoría Fiscal Promotores', 'category' => 'asesor_fiscal', 'provinces' => [28],
                'phone' => '600000002', 'email' => 'demo-asesoria@example.es', 'website' => null,
                'description_es' => 'IVA, AJD, impuesto sobre sociedades y cierre de la sociedad promotora.',
                'module' => 12, 'note' => 'Coanima la lección de liquidación'],
            ['name' => '[DEMO] Banco Colaborador', 'category' => 'banco', 'is_national' => true, 'provinces' => [],
                'phone' => '600000003', 'email' => 'demo-banco@example.es', 'website' => null,
                'description_es' => 'Préstamo promotor, avales de cantidades a cuenta e hipotecas para compradores, red nacional.',
                'module' => 5, 'note' => 'Préstamo promotor y avales'],
            ['name' => '[DEMO] Correduría de Seguros de Construcción', 'category' => 'aseguradora', 'provinces' => [8, 46],
                'phone' => '600000004', 'email' => 'demo-seguros@example.es', 'website' => null,
                'description_es' => 'Seguro decenal, todo riesgo construcción y caución de cantidades a cuenta.',
                'module' => 7, 'note' => 'Decenal y OCT desde el proyecto'],
        ];

        $provinceIds = Province::query()->pluck('id', 'code');

        foreach ($partners as $data) {
            $partner = Partner::query()->updateOrCreate(
                ['name' => $data['name']],
                collect($data)->except(['module', 'note', 'provinces'])->all()
                    + ['is_active' => true, 'is_national' => false],
            );

            $partner->provinces()->sync(
                collect($data['provinces'])->map(fn (int $code) => $provinceIds[$code] ?? null)->filter()->all(),
            );

            $module = $modules->get($data['module']);
            if ($module !== null && ! $module->partners()->where('partners.id', $partner->id)->exists()) {
                $module->partners()->attach($partner->id, ['order' => 1, 'note_es' => $data['note']]);
            }
        }

        // --- A running promotion, so the countdown has something to count to ---
        Campaign::query()->firstOrCreate(
            ['course_id' => $course->id, 'label_es' => '[DEMO] Oferta de lanzamiento'],
            [
                'label_en' => '[DEMO] Launch offer',
                'percent_off' => 20,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(3)->addHours(6),
                'is_active' => true,
            ],
        );

        // --- Mixed access levels, to exercise the paywall in every state ---
        $lucia = User::query()->where('email', 'lucia@example.es')->first();
        if ($lucia !== null) {
            // Partial owner: phases 1-3 only → locked-module upsell, partner
            // slot filled on 1 but not on 5/7/12.
            Enrollment::query()->where('user_id', $lucia->id)->delete();
            foreach ([1, 2, 3] as $order) {
                $module = $modules->get($order);
                if ($module !== null) {
                    Enrollment::query()->create([
                        'user_id' => $lucia->id,
                        'course_id' => $course->id,
                        'module_id' => $module->id,
                    ]);
                }
            }
        }

        // --- Reviews, only from students who really own the course ---
        $reviews = [
            ['email' => 'javier@example.es', 'rating' => 5, 'body' => '[DEMO] Por fin tengo claro el recorrido completo: solar, licencia, préstamo promotor y decenal. Me ha ahorrado meses.'],
            ['email' => 'marta@example.es', 'rating' => 4, 'body' => '[DEMO] Muy concreto en la venta sobre plano y la garantía de las cantidades a cuenta. La fase de control de obra vale por sí sola el curso.'],
        ];

        foreach ($reviews as $data) {
            $user = User::query()->where('email', $data['email'])->first();

            // The platform only shows reviews from verified buyers — keep the
            // demo data honest to that rule.
            if ($user === null || ! Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists()) {
                continue;
            }

            Review::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'rating' => $data['rating'],
                    'body' => $data['body'],
                    'status' => 'approved',
                    'approved_at' => now(),
                ],
            );
        }

        $this->command?->info('Demo showcase data seeded (all entities prefixed "[DEMO]").');
    }
}
