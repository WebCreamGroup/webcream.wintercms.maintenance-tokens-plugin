<?php

namespace WebCream\MaintenanceTokens;

use Backend\Facades\BackendAuth;
use Cms\Classes\Controller;
use Cms\Models\MaintenanceSetting;
use Illuminate\Support\Carbon;
use System\Classes\PluginBase;
use Winter\Storm\Support\Facades\Event;
use Winter\Storm\Support\Facades\Input;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Maintenance Tokens',
            'description' => 'Provide ability to issue access tokens in maintenance mode.',
            'author' => 'WebCream Group',
            'icon' => 'icon-leaf',
            'homepage' => 'https://webcream.eu',
        ];
    }

    public function boot()
    {
        parent::boot();

        MaintenanceSetting::extend(function (MaintenanceSetting $model) {
            $model->settingsFields = __DIR__ . '/fields.yaml';
        });

        Event::listen('cms.page.beforeDisplay', function(Controller $controller, $url, $page) {
            if (!MaintenanceSetting::isConfigured()) {
                return $page;
            }

            if (!MaintenanceSetting::get('is_enabled', false)) {
                return $page;
            }

            if (BackendAuth::getUser()) {
                return $page;
            }

            $routeToken = Input::get('maintenanceToken');

            if (!$routeToken) {
                return $page;
            }

            $accessToken = collect(MaintenanceSetting::get('accessTokens', []))
                ->filter(function ($accessToken) {
                    if (!(bool)$accessToken['isActive']) {
                        return false;
                    }

                    $activeFrom = $accessToken['activeFrom'] ? Carbon::create($accessToken['activeFrom']) : null;
                    $activeTo = $accessToken['activeTo'] ? Carbon::create($accessToken['activeTo']) : null;

                    if ($activeFrom && $activeFrom->isAfter(now())) {
                        return false;
                    }

                    return !($activeTo && $activeTo->isBefore(now()));
                })
                ->first(function ($accessToken) use ($routeToken) {
                    return $accessToken['token'] == $routeToken;
                });

            if (!$accessToken) {
                return $page;
            }

            $controller->setStatusCode(200);

            $page = $controller->getRouter()->findByUrl($url);

            if ($page && $page->is_hidden && !BackendAuth::getUser()) {
                $page = null;
            }

            return $page;
        });
    }
}
