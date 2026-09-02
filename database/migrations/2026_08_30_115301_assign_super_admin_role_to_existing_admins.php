<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Zero-disruption upgrade to role-based module access: every admin who
     * could log in and touch everything yesterday keeps doing so today,
     * via the same `super_admin` role Shield's Gate::before bypass checks
     * for. Only users created afterward, through the new Users screen, get
     * a narrower role (e.g. Marketing, SEO) instead.
     */
    public function up(): void
    {
        $superAdmin = Role::findOrCreate('super_admin');

        User::query()->where('is_admin', true)->get()->each(
            fn (User $user) => $user->assignRole($superAdmin),
        );
    }

    public function down(): void
    {
        $superAdmin = Role::findByName('super_admin');

        User::query()->where('is_admin', true)->get()->each(
            fn (User $user) => $user->removeRole($superAdmin),
        );
    }
};
