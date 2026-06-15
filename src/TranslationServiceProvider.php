<?php

namespace Dwoydig\L18nTranslator;

use Dwoydig\L18nTranslator\Http\Controllers\DeeplController;
use Dwoydig\L18nTranslator\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/l18n-translator.php', 'l18n-translator');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'l18n-translator');

        $this->registerRoutes();

        $this->publishes([
            __DIR__ . '/../config/l18n-translator.php' => config_path('l18n-translator.php'),
        ], 'l18n-translator-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/l18n-translator'),
        ], 'l18n-translator-views');
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix'     => config('l18n-translator.route_prefix', 'admin/translations'),
            'middleware' => config('l18n-translator.middleware', ['web', 'auth']),
            'as'         => 'l18n.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }
}
