<?php

namespace App\Support\Course;

/**
 * The files of the course a student can download, by key. Each is served as
 * delivered, never altered, from the package (curso/) or, for the
 * instructor's templates sent outside it, from materiales/ (`source`; none at
 * the moment: package v5 brought them all), behind the gate its `access` names:
 * - `financials`: a student who bought the extra (the Financials module);
 * - `full`: a student with the whole course;
 * - `module`: the owners of the phase module numbered `module`.
 *
 * A corrected template (`legal`) is the one exception: like every template
 * a student downloads, it carries the legal parameters in force, written into
 * a copy (CourseFileController); nothing else of it changes.
 *
 * The corrected professional template itself is not here: a student downloads
 * it filled with their inputs and the legal parameters in force
 * (FinancialsController::excel), and each small template likewise from its
 * module (SmallTemplateController::excel).
 *
 * The course PDF is the edition without the quizzes and the exam
 * (curso_sin_evaluacion): the complete one carries the answer keys.
 */
final class CourseFiles
{
    /** @var array<string, array{path: string, name: string, access: 'financials'|'full'|'module', module?: int, source?: 'curso'|'materiales', legal?: bool}> */
    public const FILES = [
        // Module 1: the professional's land management database (package v5, built from
        // the professional's schema; indice.json, plantillas_del_profesional). A download
        // only: the other one there, the «números gordos», is a calculator (SmallTemplateController).
        'base-datos-suelo' => [
            'path' => '2_plantilla_financiera/plantillas_pequenas/base_datos_suelo.xlsx',
            'name' => 'base_datos_suelo.xlsx',
            'access' => 'module',
            'module' => 1,
        ],
        // The professional's feasibility guide (22 homes, with notes in the cells) over the
        // corrected template: with the extra. A corrected template like PLANTILLA_LIBRE, so
        // it goes out with the legal parameters in force (and the tariffs the admin
        // updated) written into a copy, as the platform calculates it (`legal`).
        'guia-analisis-viabilidad' => [
            'path' => '2_plantilla_financiera/excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx',
            'name' => 'Guia_Analisis_de_Viabilidad_corregida.xlsx',
            'access' => 'financials',
            'legal' => true,
        ],
        // The professional's real example (1 home of 200 m²), with «Varios» corrected: values, no formulas.
        'plantilla-simplificada' => [
            'path' => '2_plantilla_financiera/excel_corregido/Plantilla_simplificada_corregida.xlsx',
            'name' => 'Plantilla_simplificada_corregida.xlsx',
            'access' => 'financials',
        ],
        'directorio-partners' => [
            'path' => '1_contenido/datos/partners_por_provincia.xlsx',
            'name' => 'partners_por_provincia.xlsx',
            'access' => 'full',
        ],
        'curso-pdf' => [
            'path' => '1_contenido/pdf/curso_sin_evaluacion.pdf',
            'name' => 'CONSTRUYE_curso.pdf',
            'access' => 'full',
        ],
    ];

    /**
     * @return array{path: string, name: string, access: string, module?: int, source?: string, legal?: bool}|null
     */
    public static function find(string $key): ?array
    {
        return self::FILES[$key] ?? null;
    }

    /**
     * Where a file is on disk: in the package, or in materiales/.
     *
     * @param  array{path: string, source?: string}  $file
     */
    public static function path(array $file): string
    {
        return ($file['source'] ?? 'curso') === 'materiales'
            ? rtrim((string) config('curso.materials'), '/').'/'.$file['path']
            : CourseSource::fromConfig()->path($file['path']);
    }
}
