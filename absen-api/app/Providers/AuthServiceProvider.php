<?php

namespace App\Providers;

use App\Models\Karyawan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Gate untuk IT/Developer (bisa melakukan segalanya)
        Gate::before(function (Karyawan $karyawan, $ability) {
            if ($karyawan->role === 'it_dev') {
                return true;
            }
        });

        // Manajemen Karyawan
        Gate::define('view-any-karyawan', fn (Karyawan $user) => in_array($user->role, ['hr', 'direktur']));
        Gate::define('view-karyawan', fn (Karyawan $user, Karyawan $model) => $user->karyawan_id === $model->karyawan_id || in_array($user->role, ['hr', 'direktur']));
        Gate::define('create-karyawan', fn (Karyawan $user) => $user->role === 'hr');
        Gate::define('update-karyawan', fn (Karyawan $user, Karyawan $model) => $user->karyawan_id === $model->karyawan_id || $user->role === 'hr');
        Gate::define('delete-karyawan', fn (Karyawan $user) => $user->role === 'hr');

        // Manajemen Master Data (Departemen, Jabatan, Shift)
        Gate::define('manage-master-data', fn (Karyawan $user) => $user->role === 'hr');
        Gate::define('view-master-data', fn (Karyawan $user) => in_array($user->role, ['hr', 'direktur']));

        // Penggajian
        Gate::define('process-payroll', fn (Karyawan $user) => $user->role === 'hr');
        Gate::define('approve-payroll', fn (Karyawan $user) => $user->role === 'direktur');
        Gate::define('view-any-slip', fn (Karyawan $user) => in_array($user->role, ['hr', 'direktur']));
        Gate::define('view-own-slip', fn (Karyawan $user) => $user->role === 'karyawan');
    }
}

