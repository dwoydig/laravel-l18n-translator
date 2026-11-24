<?php

namespace Dwoydig\L18nTranslator;

use Dwoydig\L18nTranslator\Contracts\TranslatorContract;
use Dwoydig\L18nTranslator\Services\Adapters\AwsTranslateAdapter;
use Dwoydig\L18nTranslator\Services\Adapters\DeeplAdapter;
use Dwoydig\L18nTranslator\Services\Adapters\GoogleTranslateAdapter;
use Dwoydig\L18nTranslator\Services\DeeplService;
use Dwoydig\L18nTranslator\Translation\PhpArrayExporter;
use Dwoydig\L18nTranslator\Translation\TranslationRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/l18n-translator.php', 'l18n-translator');

        // Bind the active translator adapter to the contract based on the configured driver.
        $this->app->singleton(TranslatorContract::class, function ($app) {
            $driver = config('l18n-translator.translator.driver', 'deepl');

            return match ($driver) {
                'deepl'  => $app->make(DeeplAdapter::class),
                'google' => $app->make(GoogleTranslateAdapter::class),
                'aws'    => $app->make(AwsTranslateAdapter::class),
                default  => throw new \InvalidArgumentException(
                    "Unsupported translator driver [{$driver}]. Supported: deepl, google, aws."
                ),
            };
        });

        // Keep DeeplService resolvable for backward compatibility (DeepL facade).
        $this->app->singleton(DeeplService::class);

        $this->app->singleton(TranslationRepository::class, fn(Application $app): TranslationRepository => TranslationRepository::discover(
            config('l18n-translator.lang_path') ?: resource_path('lang'),
            $app->make(PhpArrayExporter::class),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'l18n-translator');

        $this->registerRoutes();
        $this->computeTranslatorState();

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

    /**
     * Compute whether the active translator driver is configured and write it back into the
     * config repository as translator.enabled. This lets layouts and partials read the result
     * via config() without needing a view composer (which doesn't fire reliably for layouts).
     */
    protected function computeTranslatorState(): void
    {
        $driver = config('l18n-translator.translator.driver', 'deepl');

        $enabled = match ($driver) {
            'deepl'  => (bool) config('l18n-translator.deepl.enabled'),
            'google' => (bool) config('l18n-translator.google.api_key'),
            'aws'    => !empty(config('l18n-translator.aws.key'))
                        && !empty(config('l18n-translator.aws.secret'))
                        && !empty(config('l18n-translator.aws.region')),
            default  => false,
        };

        config(['l18n-translator.translator.enabled' => $enabled]);
    }
}
