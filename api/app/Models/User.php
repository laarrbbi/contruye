<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'locale',
        'wilaya_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Send a queued, localized password reset notification that points
     * at the SPA reset page instead of a backend route.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            (new ResetPasswordNotification($token))->locale($this->locale ?? 'fr')
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Where the learner is building — used to default every partner list to
     * the providers that actually operate there.
     */
    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Any active access to the course — full or at least one module.
     */
    public function isEnrolledIn(Course $course): bool
    {
        return $this->activeEnrollmentsFor($course)->exists();
    }

    /**
     * The student can watch every module of the course: they bought it
     * whole (an enrollment with module_id NULL) or module by module until
     * they own them all. The one definition the course page, the dashboard,
     * the player and checkout share — an à-la-carte completer used to be
     * offered the full course again at full price.
     */
    public function hasFullAccessTo(Course $course): bool
    {
        if ($this->activeEnrollmentsFor($course)->whereNull('module_id')->exists()) {
            return true;
        }

        $moduleCount = $course->modules()->count();

        return $moduleCount > 0 && $this->ownedModuleCount($course) >= $moduleCount;
    }

    /** Distinct modules of the course held through an active per-module enrollment. */
    private function ownedModuleCount(Course $course): int
    {
        return $this->activeEnrollmentsFor($course)
            ->whereNotNull('module_id')
            ->whereIn('module_id', $course->modules()->select('id'))
            ->distinct()
            ->count('module_id');
    }

    /** Curated checklist steps this user has ticked off. */
    public function checkedChecklistItems(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistItem::class, 'checklist_item_user')->withTimestamps();
    }

    public function hasAccessToModule(Module $module): bool
    {
        return $this->activeEnrollmentsFor($module->course)
            ->where(fn ($q) => $q->whereNull('module_id')->orWhere('module_id', $module->id))
            ->exists();
    }

    /**
     * Ids of the course's modules this user can watch.
     *
     * @return Collection<int, int>
     */
    public function accessibleModuleIds(Course $course): Collection
    {
        if ($this->activeEnrollmentsFor($course)->whereNull('module_id')->exists()) {
            return $course->modules()->pluck('id');
        }

        return $this->activeEnrollmentsFor($course)
            ->whereNotNull('module_id')
            ->pluck('module_id');
    }

    private function activeEnrollmentsFor(Course $course)
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->active();
    }
}
