<?php

namespace App\Support\Course;

/**
 * The files of the course a student can download, by key. Each is served as
 * delivered, never copied or altered, from the package (curso/) or, for the
 * instructor's templates sent outside it, from materiales/ (`source`), behind
 * the gate its `access` names:
 * - `financials`: a student who bought the extra (the Financials module);
 * - `full`: a student with the whole course;
 * - `module`: the owners of the phase module numbered `module`.
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
    /** @var array<string, array{path: string, name: string, access: 'financials'|'full'|'module', module?: int, source?: 'curso'|'materiales'}> */
    public const FILES = [
        // Module 1, from the instructor outside the package (materiales/LEEME.md).
        'esquema-bbdd-suelo' => [
            'path' => 'modulo_01/Esquema_BBDD_Gestion_de_Suelo.xlsx',
            'name' => 'Esquema_BBDD_Gestion_de_Suelo.xlsx',
            'access' => 'module',
            'module' => 1,
            'source' => 'materiales',
        ],
        'calculo-precio-solar' => [
            'path' => 'modulo_01/Plantilla_calculo_precio_solar.xlsx',
            'name' => 'Plantilla_calculo_precio_solar.xlsx',
            'access' => 'module',
            'module' => 1,
            'source' => 'materiales',
        ],
        // The professional template in its original version, filled with an example (a cooperative
        // of 22 homes), from the instructor outside the package (materiales/LEEME.md): with the
        // extra, not in Module 2, where it would hand out the extra's model uncorrected.
        'guia-analisis-viabilidad' => [
            'path' => 'financials/Plantilla_Guia_Analisis_de_Viabilidad.xlsx',
            'name' => 'Plantilla_Guia_Analisis_de_Viabilidad.xlsx',
            'access' => 'financials',
            'source' => 'materiales',
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
     * @return array{path: string, name: string, access: string, module?: int, source?: string}|null
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
