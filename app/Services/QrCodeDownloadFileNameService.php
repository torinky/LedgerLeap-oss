<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Lang;
use Throwable;

class QrCodeDownloadFileNameService
{
    public function forPageShare(?string $url = null): string
    {
        $timestamp = now()->format('Ymd_His');
        [$contextName, $screenLabel] = $this->resolvePageShareContext($url);

        $baseName = $this->buildBaseName([
            $contextName,
            $screenLabel,
        ], fallback: $this->translate('ledger.qr_share.filename.contexts.page_share'));

        return "{$baseName}{$this->translate('ledger.qr_share.filename.suffix')}_{$timestamp}.svg";
    }

    public function forPrefill(?string $ledgerDefineName = null): string
    {
        $timestamp = now()->format('Ymd_His');
        $baseName = $this->buildBaseName([
            $ledgerDefineName,
            $this->translate('ledger.qr_share.filename.contexts.prefill'),
        ], fallback: $this->translate('ledger.qr_share.filename.contexts.prefill'));

        return "{$baseName}{$this->translate('ledger.qr_share.filename.suffix')}_{$timestamp}.svg";
    }

    protected function resolvePageShareContext(?string $url): array
    {
        $route = $this->matchRouteFromUrl($url);
        $routeName = $route?->getName() ?? '';
        $ledgerListLabel = $this->translate('ledger.qr_share.filename.screen_types.ledger_list');

        $screenLabel = match ($routeName) {
            'ledger.index', 'ledgersByFolderId', 'ledgersByDefineId' => $ledgerListLabel,
            'ledger.show' => $this->translate('ledger.qr_share.filename.screen_types.ledger_detail'),
            'ledger.edit' => $this->translate('ledger.qr_share.filename.screen_types.ledger_edit'),
            'ledger.create' => $this->translate('ledger.qr_share.filename.screen_types.ledger_create'),
            'ledger.import.show' => $this->translate('ledger.qr_share.filename.screen_types.ledger_import'),
            default => $this->translate('ledger.qr_share.filename.screen_types.page_share'),
        };

        $contextName = match ($routeName) {
            'ledger.show', 'ledger.edit' => $this->resolveLedgerName($route),
            'ledger.create', 'ledgersByDefineId', 'ledger.import.show' => $this->resolveLedgerDefineName($route),
            'ledgersByFolderId' => $this->resolveFolderName($route),
            default => '',
        };

        return [$contextName, $screenLabel];
    }

    protected function matchRouteFromUrl(?string $url): ?Route
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        try {
            return app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveLedgerName(Route $route): string
    {
        $ledger = $route->parameter('ledgerId') ?? $route->parameter('ledger');

        if ($ledger instanceof Ledger) {
            return $ledger->define?->title ?? '';
        }

        if (is_numeric($ledger)) {
            return Ledger::withoutTenancy()->with('define')->find($ledger)?->define?->title ?? '';
        }

        return '';
    }

    protected function resolveLedgerDefineName(Route $route): string
    {
        $ledgerDefine = $route->parameter('ledgerDefineId') ?? $route->parameter('defineId');

        if ($ledgerDefine instanceof LedgerDefine) {
            return $ledgerDefine->title ?? '';
        }

        if (is_numeric($ledgerDefine)) {
            return LedgerDefine::withoutTenancy()->find($ledgerDefine)?->title ?? '';
        }

        return '';
    }

    protected function resolveFolderName(Route $route): string
    {
        $folder = $route->parameter('folderId') ?? $route->parameter('folder');

        if ($folder instanceof Folder) {
            return $folder->title ?? '';
        }

        if (is_numeric($folder)) {
            return Folder::withoutTenancy()->find($folder)?->title ?? '';
        }

        return '';
    }

    protected function buildBaseName(array $segments, string $fallback): string
    {
        $normalizedSegments = array_values(array_filter(array_map(
            fn (?string $segment) => $this->sanitizeSegment($segment),
            $segments
        )));

        return $normalizedSegments !== [] ? implode('_', $normalizedSegments) : $fallback;
    }

    protected function sanitizeSegment(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $sanitized = trim(strip_tags($value));
        $sanitized = preg_replace('/[[:cntrl:]]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/[\\/:*?"<>|]+/u', '_', $sanitized) ?? '';
        $sanitized = preg_replace('/\s+/u', '_', $sanitized) ?? '';
        $sanitized = preg_replace('/_+/u', '_', $sanitized) ?? '';
        $sanitized = trim($sanitized, "_. \t\n\r\0\x0B");

        return mb_substr($sanitized, 0, 80);
    }

    protected function translate(string $key): string
    {
        return Lang::get($key);
    }
}
