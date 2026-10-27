<?php

namespace App\Providers;

use App\Listeners\AuditarCambiosDeRol;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Registra el observer y los listeners de auditoría (RF-ADM-008 / CC-87)
     * que dejan constancia de creación, modificación y cambios de rol de
     * usuarios sin que cada módulo tenga que invocarlos a mano.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        Event::listen(RoleAttachedEvent::class, [AuditarCambiosDeRol::class, 'alAsignar']);
        Event::listen(RoleDetachedEvent::class, [AuditarCambiosDeRol::class, 'alRemover']);
    }
}
