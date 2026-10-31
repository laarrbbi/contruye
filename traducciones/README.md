# Translations of the course

The course is the instructor's package in `curso/`, in Spanish, and stays the reference. This folder
holds our translations of it, one folder per language. **They are drafts: no human translator has
reviewed them yet.** The platform says so to students (a line above every translated text: the
Spanish is the reference, above all for laws, figures and deadlines) and shows the Spanish, labelled
as such, wherever a piece is not translated.

## What is translated

| | fr | ar | ru | uk |
|---|---|---|---|---|
| Course title and description, document titles (`curso.json`) | ✓ | ✓ | ✓ | ✓ |
| The 13 module titles and objectives | ✓ | ✓ | ✓ | ✓ |
| The 60 lesson titles | ✓ | ✓ | ✓ | ✓ |
| The 13 checklists (119 items) | ✓ | ✓ | ✓ | ✓ |
| Modules 1 to 4 in full: the laws and where-to-go tables, the lessons (1.1 and 1.2 are the free preview), the closing sections | ✓ | ✓ | ✓ | ✓ |
| Modules 5–12 and Financials: the lessons, "Leyes que aplican", "Dónde ir", the closing sections | Spanish | Spanish | Spanish | Spanish |
| Quizzes (13) and final exam | ✓ | ✓ | ✓ | ✓ |
| Introduction, resources, Anexo A preface, Anexo B | Spanish | Spanish | Spanish | Spanish |
| Anexo A, the 52 provinces (institution names, laws) | Spanish | Spanish | Spanish | Spanish |

The interface (buttons, pages, e-mails, errors) is translated in full; that lives in
`web/src/i18n/` and `api/lang/`, not here. English course material is still empty: add an `en/`
folder the same way when it is written.

## How a file is laid out

`<locale>/modulos/<same name as in curso/1_contenido/modulos>.md` mirrors the Spanish file
section for section:

```markdown
# <module title, as the platform shows it>

> **<Objective>:** …
> **<Duration>:** 120 min · **<Result>:** …

## <one "## " section per Spanish section, in the same order>

<the translated text, or <!-- es --> while it is not translated>
```

- A body that is only `<!-- es -->` keeps the Spanish for that piece. Translating a lesson is
  replacing that line with its text.
- Lesson headings keep their number (`1.1`, `F.3`); checklists keep one `- [ ] ` line per item.
- `curso.json` holds the course title and description and the titles of the course documents.
  A translated document goes in `<locale>/modulos/00_introduccion.md` (etc.), whole.
- Quizzes and the exam go in `<locale>/evaluacion/quizzes_por_modulo.json` and
  `examen_final.json`, with the structure of the Spanish files: same `numero`, same `letra`, same
  `correcta`, only the words change.

## What is checked

`App\Support\Course\CourseTranslation` reads every file against its Spanish original and refuses
the import, naming the file and the section, when a translated piece:

- loses any number of the Spanish — rates, deadlines, thresholds, law and article numbers
  (`Ley 5/2019`, `art. 17`, `10 días`): **a legal figure is quoted, never reworded**;
- loses an acronym of three letters or more (`LOE`, `IVA`, `TRLGDCU`, `SAREB`);
- has a different number of sections, table rows, table cells or checklist items;
- renumbers a lesson, or (quizzes) moves a right answer.

Keep Latin digits and the Spanish number format (`1.000 m²`) in every language, Arabic included.
Spanish names of procedures are worth keeping in brackets the first time they appear
(*licencia de obras*, *nota simple*): the reader will meet them in Spain.

## Loading them

`CourseTranslationSeeder` copies the files into the `_<locale>` columns; `php artisan app:install`
runs it every time, and `php artisan db:seed --class=CourseTranslationSeeder` does it alone. For a
language with a folder, the files are the source of truth: a piece set back to `<!-- es -->` is
emptied on the next run. The Spanish columns are never written.

`php artisan app:check-translations` runs the checks without writing anything; `deploy/deploy.sh`
runs it before maintenance mode and loads the files after the migrations.
`tests/Feature/CourseTranslationTest.php` checks every file here on every test run.
