<?php

namespace App\Support\Course;

/**
 * The files of the course package a student can download, by key. Each is
 * served from the package as delivered, never copied or altered, behind the
 * gate its `access` names:
 * - `financials`: a student who bought the extra (the Financials module);
 * - `full`: a student with the whole course.
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
    /** @var array<string, array{path: string, name: string, access: 'financials'|'full'}> */
    public const FILES = [
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
     * @return array{path: string, name: string, access: string}|null
     */
    public static function find(string $key): ?array
    {
        return self::FILES[$key] ?? null;
    }
}
