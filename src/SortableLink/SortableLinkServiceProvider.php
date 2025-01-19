<?php

namespace proferabg\SortableLink;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class SortableLinkServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/sortablelink.php' => config_path('sortablelink.php'),
        ], 'config');

        Blade::directive('sortablelink', function ($expression) {
            $expression = ($expression[0] === '(') ? substr($expression, 1, -1) : $expression;

            return "<?php echo \proferabg\SortableLink\SortableLink::render(array ({$expression}));?>";
        });

        request()->macro('allFilled', function (array $keys) {
            foreach ($keys as $key) {
                if ( ! $this->filled($key)) {
                    return false;
                }
            }

            return true;
        });
    }


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sortablelink.php', 'sortablelink');
    }
}
