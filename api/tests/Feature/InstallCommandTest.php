<?php

use App\Models\ChecklistItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\BenchmarkSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * `app:install` is how an empty production database gets its content. The
 * default seeder is not an option there: it creates admin@example.dz with the
 * password "password", invented students with paid orders, and coupons whose
 * codes are in the repository.
 */
function runInstall(string $email = 'owner@ebni-academy.dz', string $password = 'un-vrai-mot-de-passe', ?string $confirmation = null)
{
    return test()->artisan('app:install', ['--admin-email' => $email, '--admin-name' => 'Propriétaire'])
        ->expectsQuestion('Admin password (12 characters or more)', $password)
        ->expectsQuestion('Same password again', $confirmation ?? $password);
}

it('installs the real content and one real admin, and nothing invented', function () {
    runInstall()->assertExitCode(0);

    expect(Wilaya::query()->count())->toBe(58)
        ->and(Course::query()->where('slug', 'promotion-immobiliere-algerie')->value('status'))->toBe('published')
        ->and(Module::query()->count())->toBe(13)
        ->and(ChecklistItem::query()->count())->toBeGreaterThan(0);

    $admin = User::query()->sole();
    expect($admin->email)->toBe('owner@ebni-academy.dz')
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('un-vrai-mot-de-passe', $admin->password))->toBeTrue();

    // No student, no order, no coupon: nothing that would show up in revenue,
    // in the public student count, or as a discount anyone can type.
    expect(User::query()->where('role', 'student')->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(Coupon::query()->count())->toBe(0);
});

it('refuses the demo domain, a short password and a mistyped confirmation', function (string $email, string $password, string $confirmation) {
    runInstall($email, $password, $confirmation)->assertExitCode(1);

    expect(User::query()->count())->toBe(0);
})->with([
    'demo address' => ['admin@example.dz', 'un-vrai-mot-de-passe', 'un-vrai-mot-de-passe'],
    'short password' => ['owner@ebni-academy.dz', 'court', 'court'],
    'the seeded password' => ['owner@ebni-academy.dz', 'password', 'password'],
    'mistyped confirmation' => ['owner@ebni-academy.dz', 'un-vrai-mot-de-passe', 'un-vrai-mot-de-passx'],
]);

it('leaves an installed course and an existing admin alone when run again', function () {
    runInstall()->assertExitCode(0);

    // The admin edited the course after the first install.
    Course::query()->where('slug', 'promotion-immobiliere-algerie')->update(['title_fr' => 'Titre corrigé par l\'admin']);

    $this->artisan('app:install', ['--admin-email' => 'someone-else@ebni-academy.dz'])
        ->expectsOutputToContain('already installed')
        ->expectsOutputToContain('an admin already exists')
        ->assertExitCode(0);

    expect(Course::query()->value('title_fr'))->toBe('Titre corrigé par l\'admin')
        ->and(User::query()->count())->toBe(1)
        ->and(Module::query()->count())->toBe(13);
});

it('refuses to seed demo data in production', function (string $seeder) {
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => $this->app->make($seeder)->run())->toThrow(RuntimeException::class);

    expect(User::query()->count())->toBe(0);
})->with([
    'default seeder' => [DatabaseSeeder::class],
    'showcase seeder' => [DemoShowcaseSeeder::class],
    'benchmark seeder' => [BenchmarkSeeder::class],
]);
