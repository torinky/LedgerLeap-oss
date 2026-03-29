<?php

namespace App\Services\Ledger;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Throwable;

class LedgerShareUrlService
{
    public function canonicalize(?string $url): string
    {
        if (! is_string($url) || trim($url) === '') {
            return '';
        }

        $route = $this->matchRouteFromUrl($url);

        if (! $route) {
            return $url;
        }

        return $this->buildAbsoluteRouteUrl(
            $route->getName(),
            $route->parameters(),
            Request::create($url, 'GET')->query()
        );
    }

    public function canonicalizeCurrent(Request $request): string
    {
        $route = $request->route();

        if (! $route) {
            return $request->fullUrl();
        }

        return $this->buildAbsoluteRouteUrl(
            $route->getName(),
            $route->parameters(),
            $request->query()
        );
    }

    public function buildAbsoluteRouteUrl(?string $routeName, array $routeParameters = [], array $query = []): string
    {
        if (! is_string($routeName) || trim($routeName) === '') {
            return config('app.url');
        }

        $allowedQueryKeys = $this->allowedQueryKeys($routeName);
        $query = Arr::only($query, $allowedQueryKeys);
        $query = $this->removeBlankQueryValues($query);
        ksort($query);

        $path = route($routeName, array_merge($routeParameters, $query), false);

        return rtrim((string) config('ledgerleap.auto_links.base_url', config('app.url')), '/').$path;
    }

    protected function allowedQueryKeys(string $routeName): array
    {
        return match ($routeName) {
            'ledger.index', 'ledgersByFolderId', 'ledgersByDefineId' => [
                'q',
                'l',
                'f',
                'cf',
                'dl',
                'sort',
                'dir',
                'status',
                'pp',
                'sem',
                'syn',
                'tt',
                'filter',
            ],
            'ledger.show' => [
                'tab',
                'dl',
                'sc',
                'td',
                'bd',
                'highlight',
            ],
            default => [],
        };
    }

    protected function matchRouteFromUrl(string $url): ?Route
    {
        try {
            return app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return null;
        }
    }

    protected function removeBlankQueryValues(array $query): array
    {
        return array_filter($query, static function ($value) {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });
    }
}
