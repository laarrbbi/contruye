<?php

namespace App\Services\Assistant;

use App\Models\Course;
use App\Models\User;

/**
 * What a student may read, as the assistant sees it: the same gate as the
 * player (AuthorizesCourseAccess). Admins read everything. The assistant
 * never answers from a piece outside this scope, and the frequent-question
 * cache is keyed by it, so an answer built from a module never reaches
 * someone who has not bought it. The student's province picks the offices of
 * the province directory (Anexo A) they read: only their own.
 */
final class Scope
{
    /** @param list<int> $modules */
    private function __construct(
        public readonly bool $admin,
        public readonly bool $enrolled,
        public readonly bool $full,
        public readonly array $modules,
        public readonly ?int $province = null,
    ) {}

    public static function for(User $user, Course $course): self
    {
        $province = $user->province_id === null ? null : (int) $user->province_id;
        if ($user->isAdmin()) {
            return new self(true, true, true, $course->modules()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(), $province);
        }

        $modules = $user->accessibleModuleIds($course)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return new self(false, $modules !== [] || $user->hasFullAccessTo($course), $user->hasFullAccessTo($course), $modules, $province);
    }

    /**
     * A scope without a student: the evaluation set asks as «a student of
     * Module 1», «a student of the whole course», and so on.
     *
     * @param  list<int>  $modules
     */
    public static function make(bool $admin, bool $enrolled, bool $full, array $modules, ?int $province = null): self
    {
        $modules = array_values(array_unique(array_map('intval', $modules)));
        sort($modules);

        return new self($admin, $enrolled, $full, $modules, $province);
    }

    /** Whether this student may use the assistant at all: a student of the course, or an admin. */
    public function eligible(): bool
    {
        return $this->admin || $this->enrolled;
    }

    /** The credits tier: the whole course, some modules, or an admin. */
    public function tier(): string
    {
        return $this->admin ? 'admin' : ($this->full ? 'full' : 'modules');
    }

    /**
     * A piece of one province's offices goes only to a student of that province
     * (an admin without a province reads them all); every piece, also by its access.
     *
     * @param  array{access: string, module_id: ?int, province_id?: ?int}  $chunk
     */
    public function allows(array $chunk): bool
    {
        $province = $chunk['province_id'] ?? null;
        if ($province !== null && ($this->province === null ? ! $this->admin : (int) $province !== $this->province)) {
            return false;
        }
        if ($this->admin) {
            return true;
        }

        return match ($chunk['access']) {
            'free' => true,
            'enrolled' => $this->enrolled,
            'full' => $this->full,
            'module', 'financials' => $chunk['module_id'] !== null && in_array((int) $chunk['module_id'], $this->modules, true),
            default => false,
        };
    }

    public function signature(): string
    {
        return ($this->admin ? 'a' : '').($this->full ? 'f' : '').($this->enrolled ? 'e' : '').':'.implode(',', $this->modules)
            .($this->province === null ? '' : ':p'.$this->province);
    }
}
