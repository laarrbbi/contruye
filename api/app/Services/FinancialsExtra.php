<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Module;
use App\Models\Setting;
use App\Models\User;

/**
 * The extra offered at the end of the course: the professional financial
 * template (the Financials module: its 9 lessons, the calculator and the
 * corrected Excel files). It is not part of any plan of the course; a student
 * of any plan buys it on its own, as the Financials module.
 *
 * It is on sale only once the admin switches it on with a price: off until the
 * professional authorises selling his corrected template.
 */
class FinancialsExtra
{
    private const SETTING = 'financials_extra';

    public function module(Course $course): ?Module
    {
        return $course->modules()->where('kind', Module::KIND_FINANCIALS)->first();
    }

    public function enabled(): bool
    {
        return (bool) (Setting::getValue(self::SETTING)['enabled'] ?? false);
    }

    /** Whole euros, IVA included; null until the owner sets it. */
    public function price(Course $course): ?int
    {
        $price = $this->module($course)?->price_eur;

        return $price !== null && $price > 0 ? (int) $price : null;
    }

    public function onSale(Course $course): bool
    {
        return $this->enabled() && $this->price($course) !== null;
    }

    public function ownedBy(User $user, Course $course): bool
    {
        $module = $this->module($course);

        return $module !== null && $user->hasAccessToModule($module);
    }

    /**
     * What a student's pages need to show the offer: whether it can be bought
     * now, at what price, and whether they already have it.
     *
     * @return array{on_sale: bool, price_eur: ?int, owned: bool, module_id: ?int}
     */
    public function view(Course $course, ?User $user): array
    {
        $module = $this->module($course);
        $owned = $user !== null && $module !== null && ($user->isAdmin() || $user->hasAccessToModule($module));

        return [
            'on_sale' => $this->onSale($course),
            'price_eur' => $this->onSale($course) ? $this->price($course) : null,
            'owned' => $owned,
            'module_id' => $module?->id,
        ];
    }

    public function setEnabled(bool $enabled): void
    {
        Setting::setValue(self::SETTING, ['enabled' => $enabled]);
    }
}
