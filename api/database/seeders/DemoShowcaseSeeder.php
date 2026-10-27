<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Partner;
use App\Models\Review;
use App\Models\User;
use App\Models\Wilaya;
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
 * Every fictional entity is prefixed "[DÉMO]" on purpose: these are NOT real
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

        $course = Course::query()->where('slug', 'promotion-immobiliere-algerie')->first();

        if ($course === null) {
            $this->command?->error('Seed the curriculum first (CurriculumV2Seeder).');

            return;
        }

        $modules = $course->modules()->orderBy('order')->get()->keyBy('order');

        // --- Partners, matched to the partner_slot categories of the modules ---
        // Coverage is expressed in wilaya codes so the demo exercises the real
        // cases: a multi-wilaya provider, a single-wilaya one, and a national.
        $partners = [
            ['name' => '[DÉMO] Étude Notariale Belkacem', 'category' => 'notaire', 'wilayas' => [16, 9, 42],
                'phone' => '0550000001', 'email' => 'demo-notaire@example.dz', 'website' => 'https://example.dz',
                'description_fr' => 'Actes de vente sur plan, EDD et livrets fonciers.',
                'module' => 2, 'note' => 'Pour vos statuts et tous les actes du parcours'],
            ['name' => '[DÉMO] Cabinet Compta DZ', 'category' => 'expert_comptable', 'wilayas' => [9],
                'phone' => '0550000002', 'email' => 'demo-compta@example.dz', 'website' => null,
                'description_fr' => 'Comptabilité, G50, IBS et fiscalité du promoteur.',
                'module' => 12, 'note' => 'Co-animation du module fiscalité'],
            ['name' => '[DÉMO] Banque Partenaire', 'category' => 'banque', 'is_national' => true, 'wilayas' => [],
                'phone' => '0550000003', 'email' => 'demo-banque@example.dz', 'website' => null,
                'description_fr' => 'Crédit acquéreur VSP et crédit promoteur, réseau national.',
                'module' => 8, 'note' => 'Conventions de financement acquéreurs'],
            ['name' => '[DÉMO] Consultant Agrément', 'category' => 'consultant_agrement', 'wilayas' => [31, 13],
                'phone' => '0550000004', 'email' => 'demo-agrement@example.dz', 'website' => null,
                'description_fr' => "Montage des dossiers d'agrément en wilaya.",
                'module' => 3, 'note' => 'Montage du dossier wilaya de bout en bout'],
        ];

        $wilayaIds = Wilaya::query()->pluck('id', 'code');

        foreach ($partners as $data) {
            $partner = Partner::query()->updateOrCreate(
                ['name' => $data['name']],
                collect($data)->except(['module', 'note', 'wilayas'])->all()
                    + ['is_active' => true, 'is_national' => false],
            );

            $partner->wilayas()->sync(
                collect($data['wilayas'])->map(fn (int $code) => $wilayaIds[$code] ?? null)->filter()->all(),
            );

            $module = $modules->get($data['module']);
            if ($module !== null && ! $module->partners()->where('partners.id', $partner->id)->exists()) {
                $module->partners()->attach($partner->id, ['order' => 1, 'note_fr' => $data['note']]);
            }
        }

        // --- A running promotion, so the countdown has something to count to ---
        Campaign::query()->firstOrCreate(
            ['course_id' => $course->id, 'label_fr' => '[DÉMO] Offre de lancement'],
            [
                'label_ar' => '[DÉMO] عرض الإطلاق',
                'percent_off' => 20,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(3)->addHours(6),
                'is_active' => true,
            ],
        );

        // --- Mixed access levels, to exercise the paywall in every state ---
        $amel = User::query()->where('email', 'amel@example.dz')->first();
        if ($amel !== null) {
            // Partial owner: modules 1-3 only → locked-module upsell, partner
            // slots filled on 2 & 3 but not on 8/12.
            Enrollment::query()->where('user_id', $amel->id)->delete();
            foreach ([1, 2, 3] as $order) {
                $module = $modules->get($order);
                if ($module !== null) {
                    Enrollment::query()->create([
                        'user_id' => $amel->id,
                        'course_id' => $course->id,
                        'module_id' => $module->id,
                    ]);
                }
            }
        }

        // --- Reviews, only from students who really own the course ---
        $reviews = [
            ['email' => 'karim@example.dz', 'rating' => 5, 'body' => "[DÉMO] Le circuit administratif est enfin clair : agrément, FGCMPI, permis. J'ai gagné des mois."],
            ['email' => 'lina@example.dz', 'rating' => 4, 'body' => '[DÉMO] Très concret sur la VSP et les délais réels. Le module chantier vaut à lui seul la formation.'],
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

        $this->command?->info('Demo showcase data seeded (all entities prefixed "[DÉMO]").');
    }
}
