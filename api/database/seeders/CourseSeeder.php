<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Services\PricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->firstOrCreate(
            ['slug' => 'promotion-immobiliere-algerie'],
            [
                'title_fr' => 'La Promotion Immobilière en Algérie',
                'title_ar' => 'الترقية العقارية في الجزائر',
                'description_fr' => 'De la création de la société à la remise des clés : maîtrisez le circuit réel de la promotion immobilière en Algérie, étape par étape. Agrément, foncier, permis de construire, vente sur plan, chantier et livraison : 10 modules concrets avec les guichets, les délais et les coûts réels du terrain. Une formation de praticien, adossée aux textes de loi algériens en vigueur.',
                'description_ar' => 'من تأسيس الشركة إلى تسليم المفاتيح: أتقِن المسار الحقيقي للترقية العقارية في الجزائر خطوة بخطوة. الاعتماد، الوعاء العقاري، رخصة البناء، البيع على التصاميم، الورشة والتسليم: عشرة محاور عملية مع الشبابيك والآجال والتكاليف الحقيقية للميدان. تكوين من ممارس ميداني، مدعوم بالنصوص القانونية الجزائرية السارية المفعول.',
                // Full access = best deal: 10 modules à la carte would cost
                // 120 000 DZD / 400 EUR (12 000 / 40 each) → -25 %.
                'price_dzd' => 90000,
                'price_eur' => 300,
                'module_discount_tiers' => PricingService::DEFAULT_TIERS,
                'status' => 'published',
                'cover_image' => null,
            ],
        );

        // Idempotent-safe: never duplicate the curriculum on a re-run.
        if ($course->modules()->exists()) {
            return;
        }

        foreach ($this->program() as $moduleIndex => $moduleData) {
            $module = $course->modules()->create([
                'order' => $moduleIndex + 1,
                'title_fr' => $moduleData['title_fr'],
                'title_ar' => $moduleData['title_ar'],
                'price_dzd' => 12000,
                'price_eur' => 40,
            ]);

            foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                $module->lessons()->create([
                    'order' => $lessonIndex + 1,
                    'title_fr' => $lessonData['title_fr'],
                    'title_ar' => $lessonData['title_ar'],
                    'bunny_video_id' => (string) Str::uuid(),
                    'duration_seconds' => random_int(30, 80) * 30, // 900..2400s (15-40 min)
                    'is_free_preview' => $moduleIndex === 0 && $lessonIndex < 2,
                    'attachments' => $lessonData['attachments'] ?? null,
                ]);
            }
        }
    }

    /**
     * The real course program: "La Promotion Immobilière en Algérie"
     * (10 modules, ~40 lessons derived from the detailed syllabus).
     *
     * @return array<int, array{title_fr: string, title_ar: string, lessons: array<int, array{title_fr: string, title_ar: string, attachments?: array<int, array{name: string, path: string}>}>}>
     */
    private function program(): array
    {
        return [
            [
                'title_fr' => 'Le métier, le marché et le business model',
                'title_ar' => 'المهنة والسوق ونموذج الأعمال',
                'lessons' => [
                    [
                        'title_fr' => "La promotion immobilière au sens de la loi 11-04 : ce qu'elle est, ce qu'elle n'est pas",
                        'title_ar' => 'الترقية العقارية بمفهوم القانون 11-04: ما هي وما ليست عليه',
                    ],
                    [
                        'title_fr' => 'Le business model du promoteur : terrain 25 %, gestion 7 %, construction 68 %',
                        'title_ar' => 'نموذج أعمال المرقي العقاري: الوعاء العقاري 25٪، التسيير 7٪، البناء 68٪',
                    ],
                    [
                        'title_fr' => "Trésorerie de départ et marge cible : sécuriser environ 35 % du chiffre d'affaires",
                        'title_ar' => 'خزينة الانطلاق والهامش المستهدف: تأمين حوالي 35٪ من رقم الأعمال',
                    ],
                    [
                        'title_fr' => "Les acteurs de l'écosystème : wilaya, APC, notaire, CNRC, FGCMPI, CTC",
                        'title_ar' => 'الفاعلون في المنظومة: الولاية، البلدية، الموثق، المركز الوطني للسجل التجاري، صندوق الضمان، هيئة الرقابة التقنية',
                        'attachments' => [
                            ['name' => 'Checklist Module 1 (PDF)', 'path' => 'attachments/demo/checklist-m1.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => 'Créer la société (le véhicule juridique)',
                'title_ar' => 'تأسيس الشركة (الوعاء القانوني)',
                'lessons' => [
                    [
                        'title_fr' => 'EURL, SARL ou SPA : choisir la forme juridique',
                        'title_ar' => 'EURL أو SARL أو SPA: اختيار الشكل القانوني',
                    ],
                    [
                        'title_fr' => 'Le circuit de création pas à pas : CNRC, notaire, BOAL',
                        'title_ar' => 'مسار التأسيس خطوة بخطوة: السجل التجاري، الموثق، النشرة الرسمية للإعلانات القانونية',
                    ],
                    [
                        'title_fr' => "NIF, C20, NIS, CNAS : l'immatriculation fiscale et sociale",
                        'title_ar' => 'NIF وC20 وNIS وCNAS: التسجيل الجبائي والاجتماعي',
                    ],
                    [
                        'title_fr' => 'G50 et obligations récurrentes dès le jour 1',
                        'title_ar' => 'التصريح G50 والالتزامات الدورية منذ اليوم الأول',
                        'attachments' => [
                            ['name' => 'Checklist Module 2 (PDF)', 'path' => 'attachments/demo/checklist-m2.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => "L'agrément de promoteur immobilier",
                'title_ar' => 'اعتماد المرقي العقاري',
                'lessons' => [
                    [
                        'title_fr' => "Pourquoi l'agrément est LE verrou : loi 11-04 et Tableau National des Promoteurs",
                        'title_ar' => 'لماذا الاعتماد هو المفتاح: القانون 11-04 والجدول الوطني للمرقين العقاريين',
                    ],
                    [
                        'title_fr' => "Les conditions d'obtention : diplôme, moralité, ressources financières",
                        'title_ar' => 'شروط الحصول على الاعتماد: الشهادة، حسن السيرة، الموارد المالية',
                    ],
                    [
                        'title_fr' => 'Monter un dossier qui passe en wilaya (décrets 12-84 et 19-243)',
                        'title_ar' => 'إعداد ملف يُقبل على مستوى الولاية (المرسومان التنفيذيان 12-84 و19-243)',
                    ],
                    [
                        'title_fr' => "Après l'agrément : inscription au TNPI et affiliation au FGCMPI",
                        'title_ar' => 'بعد الاعتماد: التسجيل في الجدول الوطني والانخراط في صندوق ضمان وكفالة الترقية العقارية',
                        'attachments' => [
                            ['name' => 'Checklist Module 3 (PDF)', 'path' => 'attachments/demo/checklist-m3.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => 'Le foncier : trouver, vérifier et acheter le terrain',
                'title_ar' => 'الوعاء العقاري: البحث عن الأرض والتحقق منها وشراؤها',
                'lessons' => [
                    [
                        'title_fr' => 'Vérifier le titre de propriété chez le notaire : chaîne de propriété et servitudes',
                        'title_ar' => 'التحقق من سند الملكية لدى الموثق: سلسلة الملكية والارتفاقات',
                    ],
                    [
                        'title_fr' => "Le certificat d'urbanisme : POS, PDAU, COS/CES et droits à construire",
                        'title_ar' => 'شهادة التعمير: مخطط شغل الأراضي، المخطط التوجيهي، معاملات البناء وحقوق البناء',
                    ],
                    [
                        'title_fr' => "L'erreur n°1 du débutant : acheter le terrain avant le certificat d'urbanisme",
                        'title_ar' => 'الخطأ الأول للمبتدئ: شراء الأرض قبل الحصول على شهادة التعمير',
                    ],
                    [
                        'title_fr' => 'Le foncier public : concession convertible en cession (décret 15-281)',
                        'title_ar' => 'العقار العمومي: الامتياز القابل للتحويل إلى تنازل (المرسوم 15-281)',
                        'attachments' => [
                            ['name' => 'Checklist Module 4 (PDF)', 'path' => 'attachments/demo/checklist-m4.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => 'Études techniques et permis de construire',
                'title_ar' => 'الدراسات التقنية ورخصة البناء',
                'lessons' => [
                    [
                        'title_fr' => "L'étude géotechnique : sondages, portance et niveau du bon sol",
                        'title_ar' => 'الدراسة الجيوتقنية: السبر، قدرة التحمل ومستوى الأرضية الصالحة',
                    ],
                    [
                        'title_fr' => "Choisir son bureau d'études : le partenaire central du projet",
                        'title_ar' => 'اختيار مكتب الدراسات: الشريك المحوري للمشروع',
                    ],
                    [
                        'title_fr' => 'Le dossier de permis de construire : pièces, guichet unique, 5 exemplaires',
                        'title_ar' => 'ملف رخصة البناء: الوثائق، الشباك الوحيد، خمس نسخ',
                    ],
                    [
                        'title_fr' => "L'instruction du permis : avis des services, commission et délais réalistes",
                        'title_ar' => 'دراسة ملف الرخصة: آراء المصالح، اللجنة والآجال الواقعية',
                    ],
                    [
                        'title_fr' => 'La convention CTC : le contrôle technique qui conditionne la décennale',
                        'title_ar' => 'اتفاقية هيئة الرقابة التقنية للبناء: الرقابة التقنية شرط الضمان العشري',
                        'attachments' => [
                            ['name' => 'Checklist Module 5 (PDF)', 'path' => 'attachments/demo/checklist-m5.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => "Diviser le projet : l'EDD et le livret foncier par unité",
                'title_ar' => 'تقسيم المشروع: بيان وصف التقسيم والدفتر العقاري لكل وحدة',
                'lessons' => [
                    [
                        'title_fr' => "Le géomètre-expert et l'État Descriptif de Division (EDD)",
                        'title_ar' => 'الخبير المساح المعتمد وبيان وصف التقسيم (EDD)',
                    ],
                    [
                        'title_fr' => 'Du terrain unique aux N livrets fonciers : cadastre et conservation foncière',
                        'title_ar' => 'من قطعة أرض واحدة إلى دفاتر عقارية متعددة: مسح الأراضي والمحافظة العقارية',
                    ],
                    [
                        'title_fr' => 'Le règlement de copropriété type (décret 14-99)',
                        'title_ar' => 'نظام الملكية المشتركة النموذجي (المرسوم 14-99)',
                    ],
                ],
            ],
            [
                'title_fr' => 'Vendre sur plan (VSP) : le cœur commercial et juridique',
                'title_ar' => 'البيع على التصاميم: القلب التجاري والقانوني',
                'lessons' => [
                    [
                        'title_fr' => 'Les préalables non négociables avant de vendre le moindre mètre carré',
                        'title_ar' => 'الشروط المسبقة غير القابلة للتفاوض قبل بيع أي متر مربع',
                        'attachments' => [
                            ['name' => 'Checklist Module 7 (PDF)', 'path' => 'attachments/demo/checklist-m7.pdf'],
                        ],
                    ],
                    [
                        'title_fr' => 'Réservation vs vente sur plan : deux contrats, deux régimes',
                        'title_ar' => 'عقد الحجز مقابل عقد البيع على التصاميم: عقدان ونظامان قانونيان',
                    ],
                    [
                        'title_fr' => 'Le contrat VSP notarié : tranches de paiement, pénalités et article 38',
                        'title_ar' => 'عقد البيع على التصاميم الموثق: أقساط الدفع، الغرامات والمادة 38',
                        'attachments' => [
                            ['name' => 'Modèle de contrat VSP (PDF)', 'path' => 'attachments/demo/modele-contrat-vsp.pdf'],
                        ],
                    ],
                    [
                        'title_fr' => 'Monter la machine commerciale : équipe, bureau de vente et marketing',
                        'title_ar' => 'بناء الآلة التجارية: الفريق، مكتب البيع والتسويق',
                    ],
                    [
                        'title_fr' => 'Conventions bancaires : financer vos acquéreurs pour vendre plus vite',
                        'title_ar' => 'الاتفاقيات البنكية: تمويل المشترين للبيع بشكل أسرع',
                    ],
                ],
            ],
            [
                'title_fr' => 'Construire : entreprises, chantier, assurances, aléas',
                'title_ar' => 'البناء: المقاولات، الورشة، التأمينات والمخاطر',
                'lessons' => [
                    [
                        'title_fr' => 'Le devis quantitatif et la consultation des entreprises : 3 devis par lot',
                        'title_ar' => 'الكشف الكمي واستشارة المقاولات: ثلاثة عروض لكل حصة',
                    ],
                    [
                        'title_fr' => 'Les lots du chantier : du terrassement aux ascenseurs et VRD',
                        'title_ar' => 'حصص الورشة: من أشغال الحفر إلى المصاعد وشبكات التهيئة',
                    ],
                    [
                        'title_fr' => 'Le suivi de chantier : chef de projet, ingénieurs et visas CTC',
                        'title_ar' => 'متابعة الورشة: رئيس المشروع، المهندسون وتأشيرات هيئة الرقابة التقنية',
                    ],
                    [
                        'title_fr' => 'RC professionnelle, décennale, CAT-NAT : les assurances obligatoires du chantier',
                        'title_ar' => 'المسؤولية المدنية المهنية، الضمان العشري وتأمين الكوارث الطبيعية: التأمينات الإلزامية للورشة',
                    ],
                    [
                        'title_fr' => "Les aléas du réel : venues d'eau, béton non conforme et délai de grâce",
                        'title_ar' => 'مفاجآت الميدان: تسربات المياه، الخرسانة غير المطابقة ومهلة السماح',
                        'attachments' => [
                            ['name' => 'Checklist Module 8 (PDF)', 'path' => 'attachments/demo/checklist-m8.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => 'Livrer : conformité, réceptions, actes définitifs',
                'title_ar' => 'التسليم: المطابقة، الاستلامات والعقود النهائية',
                'lessons' => [
                    [
                        'title_fr' => 'Le certificat de conformité : la commission vérifie le permis, ni plus ni moins',
                        'title_ar' => 'شهادة المطابقة: اللجنة تتحقق من الرخصة، لا أكثر ولا أقل',
                    ],
                    [
                        'title_fr' => 'Réception provisoire et gestion des réserves des clients',
                        'title_ar' => 'الاستلام المؤقت وتسيير تحفظات الزبائن',
                    ],
                    [
                        'title_fr' => 'Réception définitive : le PV qui déclenche la garantie décennale',
                        'title_ar' => 'الاستلام النهائي: المحضر الذي يُطلق سريان الضمان العشري',
                    ],
                    [
                        'title_fr' => 'Les actes définitifs chez le notaire : le dossier complet de la remise des clés',
                        'title_ar' => 'العقود النهائية لدى الموثق: الملف الكامل لتسليم المفاتيح',
                        'attachments' => [
                            ['name' => 'Checklist Module 9 (PDF)', 'path' => 'attachments/demo/checklist-m9.pdf'],
                        ],
                    ],
                ],
            ],
            [
                'title_fr' => 'Piloter les chiffres',
                'title_ar' => 'قيادة الأرقام',
                'lessons' => [
                    [
                        'title_fr' => 'Le tableau de bord projet : coût de revient au m² et point mort',
                        'title_ar' => 'لوحة قيادة المشروع: سعر التكلفة للمتر المربع ونقطة التعادل',
                    ],
                    [
                        'title_fr' => "L'échéancier de trésorerie : encaissements VSP vs décaissements chantier",
                        'title_ar' => 'جدول الخزينة: مقبوضات البيع على التصاميم مقابل مدفوعات الورشة',
                    ],
                    [
                        'title_fr' => 'La fiscalité du promoteur : G50, IBS, TVA et droits d’enregistrement',
                        'title_ar' => 'جباية المرقي العقاري: التصريح G50، الضريبة على أرباح الشركات، الرسم على القيمة المضافة وحقوق التسجيل',
                    ],
                ],
            ],
        ];
    }
}
