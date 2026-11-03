<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Province;
use App\Support\Spreadsheet\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads company lists into the directory.
 *
 *   php artisan directory:import file1.xlsx file2.xlsx … [--source=orbis] [--hidden]
 *
 * Reads an Orbis "List export" (the sheet with the companies; columns found
 * by their header, in French, English or Spanish) or any sheet with the same
 * headers. A company is keyed by its source reference (the BvD number, else
 * its tax id): importing a newer export updates it and keeps the admin's
 * choice of whether students see it. New companies are visible unless
 * --hidden is given.
 *
 * Use only lists you are licensed to publish to your students: an Orbis
 * subscription for academic research does not cover a commercial course.
 */
class ImportDirectory extends Command
{
    protected $signature = 'directory:import {files* : .xlsx files} {--source=orbis : Where the list comes from} {--hidden : New companies start hidden from students}';

    protected $description = 'Import company lists into the partner directory';

    /** Header fragments that identify each field, lower-case (fr / en / es). */
    private const HEADERS = [
        'name' => ["nom de l'entreprise", 'company name', 'nombre'],
        'activity_code' => ['nace rev. 2 code principal', 'nace rev. 2, core code', 'nace rev. 2 main', 'cnae', 'código nace'],
        'employees' => ['effectifs', 'number of employees', 'empleados'],
        'address1' => ['rue, n°, bât. etc., ligne 1', 'street, no., building etc, line 1', 'dirección'],
        'address2' => ['rue, n°, bât. etc., ligne 2', 'street, no., building etc, line 2'],
        'postal_code' => ['code postal', 'postcode', 'código postal'],
        'city' => ['ville', 'city', 'localidad', 'municipio'],
        'phone' => ['téléphone', 'telephone number', 'teléfono'],
        'website' => ['adresse du site internet', 'website address', 'sitio web', 'web'],
        'email' => ['e-mail', 'email'],
        'source_ref' => ['numéro bvd', 'bvd id number', 'número bvd'],
        'tax_id' => ['n° de taxe / tva', 'vat/tax number', 'nif', 'cif'],
        'legal_form' => ['forme juridique nationale', 'national legal form', 'forma jurídica'],
    ];

    public function handle(): int
    {
        $provinces = Province::query()->pluck('id', 'code');
        $source = (string) $this->option('source');
        $visible = ! $this->option('hidden');
        $total = 0;

        foreach ($this->argument('files') as $file) {
            $reader = new XlsxReader($file);
            [$sheet, $columns] = $this->findDataSheet($reader);
            if ($sheet === null) {
                $this->error("{$file}: no sheet with a company-name column.");

                return self::FAILURE;
            }
            $batch = [];
            $count = 0;
            foreach ($reader->rows($sheet) as $i => $row) {
                if ($i === 0) {
                    continue;
                }
                $company = $this->company($row, $columns, $source, $provinces);
                if ($company === null) {
                    continue;
                }
                $batch[] = $company;
                if (count($batch) === 1000) {
                    $count += $this->store($batch, $visible);
                    $batch = [];
                }
            }
            $count += $this->store($batch, $visible);
            $total += $count;
            $this->info(basename($file).": {$count} companies");
        }

        Company::forgetCounts();
        $this->info("Directory: {$total} companies imported or updated.");

        return self::SUCCESS;
    }

    /** @return array{0: ?int, 1: array<string, int>} the sheet whose first row names the companies, and its columns */
    private function findDataSheet(XlsxReader $reader): array
    {
        for ($sheet = 1; $sheet <= $reader->sheetCount(); $sheet++) {
            foreach ($reader->rows($sheet) as $header) {
                $columns = [];
                foreach ($header as $i => $label) {
                    $label = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $label)));
                    foreach (self::HEADERS as $field => $fragments) {
                        if (isset($columns[$field])) {
                            continue;
                        }
                        foreach ($fragments as $fragment) {
                            if ($label !== '' && str_starts_with($label, $fragment)) {
                                $columns[$field] = $i;
                                break;
                            }
                        }
                    }
                }
                if (isset($columns['name'])) {
                    return [$sheet, $columns];
                }
                break;
            }
        }

        return [null, []];
    }

    private function company(array $row, array $columns, string $source, $provinces): ?array
    {
        $get = function (string $field) use ($row, $columns): ?string {
            $value = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
            $value = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value));

            return $value === '' ? null : $value;
        };
        $name = $get('name');
        $ref = $get('source_ref') ?? $get('tax_id');
        if ($name === null || $ref === null) {
            return null;
        }
        $postal = $get('postal_code');
        $code = $postal !== null && preg_match('/^\d{5}$/', $postal) ? (int) substr($postal, 0, 2) : null;
        $website = $get('website');
        // Orbis lists several sites, one per line: keep the first.
        if ($website !== null) {
            $first = preg_split('/\s+/', $website)[0];
            $website = preg_match('#^https?://#i', $first) ? $first : 'https://'.$first;
        }
        $activity = $get('activity_code');
        $employees = $get('employees');

        return [
            'source' => $source,
            'source_ref' => mb_substr($ref, 0, 64),
            'name' => mb_substr($name, 0, 255),
            'category' => Company::categoryFor($activity),
            'activity_code' => $activity === null ? null : mb_substr(preg_replace('/\D/', '', $activity), 0, 8),
            'legal_form' => $get('legal_form') === null ? null : mb_substr($get('legal_form'), 0, 120),
            'address' => ($a = trim(implode(' ', array_filter([$get('address1'), $get('address2')])))) === '' ? null : mb_substr($a, 0, 255),
            'postal_code' => $postal === null ? null : mb_substr($postal, 0, 10),
            'city' => $get('city') === null ? null : mb_substr(mb_convert_case($get('city'), MB_CASE_TITLE), 0, 120),
            'province_id' => $code !== null ? ($provinces[$code] ?? null) : null,
            'phone' => $get('phone') === null ? null : mb_substr(preg_split('/\s{2,}|;/', $get('phone'))[0], 0, 60),
            'email' => $get('email') === null ? null : mb_substr(mb_strtolower(preg_split('/\s+/', $get('email'))[0]), 0, 255),
            'website' => $website === null ? null : mb_substr($website, 0, 255),
            'tax_id' => $get('tax_id') === null ? null : mb_substr($get('tax_id'), 0, 32),
            'employees' => $employees !== null && is_numeric($employees) ? (int) $employees : null,
        ];
    }

    /** Insert new companies, update known ones; the admin's visibility choice is never overwritten. */
    private function store(array $batch, bool $visible): int
    {
        if ($batch === []) {
            return 0;
        }
        $now = now();
        $rows = array_map(fn (array $c) => $c + ['is_visible' => $visible, 'created_at' => $now, 'updated_at' => $now], $batch);
        $update = array_values(array_diff(array_keys($rows[0]), ['source', 'source_ref', 'is_visible', 'created_at']));
        DB::table('companies')->upsert($rows, ['source', 'source_ref'], $update);

        return count($rows);
    }
}
