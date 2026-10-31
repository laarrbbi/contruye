<?php

namespace App\Support\Course;

/**
 * The files of the course package a student can download, by key. Each is
 * served from the package as delivered, never copied or altered, behind the
 * gate its `access` names:
 * - `financials`: the owner of the Financials module (the template files);
 * - `full`: a student with the whole course.
 *
 * The course PDF is the edition without the quizzes and the exam
 * (curso_sin_evaluacion): the complete one carries the answer keys.
 */
final class CourseFiles
{
    /** @var array<string, array{path: string, name: string, access: 'financials'|'full'}> */
    public const FILES = [
        'plantilla-libre' => [
            'path' => '2_plantilla_financiera/originales/PLANTILLA_LIBRE.xls',
            'name' => 'PLANTILLA_LIBRE.xls',
            'access' => 'financials',
        ],
        'plantilla-simplificada' => [
            'path' => '2_plantilla_financiera/originales/Plantilla_simplificada_Analisis_de_Viabilidad.xlsx',
            'name' => 'Plantilla_simplificada_Analisis_de_Viabilidad.xlsx',
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
