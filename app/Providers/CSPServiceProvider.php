<?php

namespace App\Providers;

use App\Helpers\CSPHelper;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class CSPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Register Blade directive for CSP nonce
        Blade::directive('cspNonce', function () {
            return "<?php echo \App\Helpers\CSPHelper::nonceAttr(); ?>";
        });
        
        // Register Blade directive for script with nonce
        Blade::directive('scriptNonce', function ($expression) {
            return "<?php echo \App\Helpers\CSPHelper::scriptWithNonce($expression); ?>";
        });
        
        // Register Blade directive for style with nonce
        Blade::directive('styleNonce', function ($expression) {
            return "<?php echo \App\Helpers\CSPHelper::styleWithNonce($expression); ?>";
        });
    }
}
