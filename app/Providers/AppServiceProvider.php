<?php

namespace App\Providers;

use App\Exports\CoreExportProvider;
use App\Integrations\ExportProviderRegistry;
use App\Integrations\ExportService;
use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\TicketProviderRegistry;
use App\Integrations\TicketService;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Scramble\RenamePathParametersTransformer;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SocialiteProviders\Auth0\Provider as Auth0Provider;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Google\Provider as GoogleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Permission\Models\Permission;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationTypeRegistry::class);
        $this->app->singleton(TicketProviderRegistry::class);
        $this->app->singleton(TicketService::class);
        $this->app->singleton(ExportProviderRegistry::class);
        $this->app->singleton(ExportService::class);
    }

    public function boot(): void
    {
        $this->app->make(ExportProviderRegistry::class)->registerGlobal(CoreExportProvider::class);

        $this->app->booted(function () {
            if (! Schema::hasTable('permissions')) {
                return;
            }

            $shieldKeys = collect(FilamentShield::getAllResourcePermissionsWithLabels())->keys();

            config([
                'filament-shield.custom_permissions' => Permission::whereNotIn('name', $shieldKeys)->pluck('name')->toArray(),
            ]);
        });

        FilamentShield::buildPermissionKeyUsing(function (string $subject, string $affix): string {
            $action = match ($affix) {
                'viewAny', 'view' => 'read',
                'deleteAny' => 'delete',
                'restoreAny' => 'restore',
                'forceDeleteAny' => 'force-delete',
                default => Str::kebab($affix),
            };

            return Str::plural(Str::kebab($subject)).'.'.$action;
        });

        JsonApiResourceCollection::camelCasePaginationMeta();

        Relation::enforceMorphMap([
            'user' => User::class,
            'api_token' => ApiToken::class,
        ]);

        $this->bootAuth();

        URL::forceHttps();

        Scramble::configure()
            ->preferPatchMethod()
            ->withDocumentTransformers(RenamePathParametersTransformer::class)
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        Scramble::routes(function (Route $route) {
            return in_array('api', $route->gatherMiddleware());
        });
    }

    private function bootAuth(): void
    {
        Auth::viaRequest('api_token', function (Request $request) {
            $key = $request->bearerToken() ?? $request->header('api-key');

            if ($key === null) {
                return null;
            }

            return ApiToken::notExpired()
                ->where('key', hash('sha512', $key))
                ->firstOr(fn () => abort(401));
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', AzureProvider::class);
            $event->extendSocialite('auth0', Auth0Provider::class);
            $event->extendSocialite('google', GoogleProvider::class);
        });
    }
}
