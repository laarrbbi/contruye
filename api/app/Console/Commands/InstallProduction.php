<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\ProvinceGuideSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * First install on an empty production database: the 52 provinces, the
 * instructor's curriculum and checklists, and ONE admin account with a real
 * address and a password typed by the owner.
 *
 *   php artisan migrate --force
 *   php artisan app:install
 *
 * What it never does is what `db:seed` does: an admin@example.es / "password"
 * login anyone reading the repository knows, students who do not exist with
 * paid orders that would show up in revenue and in the public student count,
 * and a 50 %-off coupon whose code is in the source. DatabaseSeeder refuses
 * to run in production for that reason.
 *
 * Safe to run again: each step skips itself once done, and the curriculum is
 * never re-seeded over a course that already exists — the admin may have
 * edited it, and paid orders reference its modules.
 */
class InstallProduction extends Command
{
    protected $signature = 'app:install
        {--admin-email= : The owner\'s real address (prompted when omitted)}
        {--admin-name= : Shown in the admin (prompted when omitted)}';

    protected $description = 'Install provinces, the curriculum and the first admin — no demo data';

    private const SLUG = 'promocion-inmobiliaria-espana';

    public function handle(): int
    {
        $this->components->task('Provinces (52) and their guides (Anexo A)', fn () => $this->callSilent('db:seed', [
            '--class' => ProvinceSeeder::class, '--force' => true,
        ]) === 0 && $this->callSilent('db:seed', [
            '--class' => ProvinceGuideSeeder::class, '--force' => true,
        ]) === 0);

        if (Course::query()->where('slug', self::SLUG)->exists()) {
            $this->components->twoColumnDetail('Curriculum', '<fg=yellow>already installed — left as it is</>');
        } else {
            $this->components->task('Curriculum (12 modules and Financials), quizzes and checklists', function () {
                return $this->callSilent('db:seed', ['--class' => CurriculumV2Seeder::class, '--force' => true]) === 0
                    && $this->callSilent('db:seed', ['--class' => ChecklistSeeder::class, '--force' => true]) === 0;
            });
        }

        if (User::query()->where('role', 'admin')->exists()) {
            $this->components->twoColumnDetail('Admin account', '<fg=yellow>an admin already exists — none created</>');

            return self::SUCCESS;
        }

        return $this->createAdmin();
    }

    private function createAdmin(): int
    {
        $email = $this->option('admin-email') ?: text(
            label: 'Admin e-mail (the owner\'s real address)',
            required: true,
        );
        $name = $this->option('admin-name') ?: text(label: 'Admin name', default: 'Administrador');

        // Typed, never passed as an option: options end up in shell history.
        $password = password(label: 'Admin password (12 characters or more)', required: true);
        $confirmation = password(label: 'Same password again', required: true);

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'unique:users,email', 'not_regex:/@example\.(es|com|org)$/i'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12), 'not_in:password,password1234,admin1234567'],
            ],
            ['email.not_regex' => 'example.es is the demo domain: use the owner\'s real address.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        (new User(['name' => $name, 'email' => $email, 'password' => $password, 'locale' => 'es']))
            ->forceFill(['role' => 'admin', 'email_verified_at' => now()])
            ->save();

        $this->components->info("Admin {$email} created. Sign in at ".rtrim((string) config('app.frontend_url'), '/').'/login');

        return self::SUCCESS;
    }
}
