<?php

use App\Http\Middleware\EnsureAuthenticatedUserHasCurrentTenantAccess;
use App\Http\Middleware\ForceMcpJsonAccept;
use App\Mcp\Servers\LedgerLeapServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

Route::middleware([ForceMcpJsonAccept::class])->group(function () {
    Mcp::web('/mcp/ledgerleap', LedgerLeapServer::class)
        ->middleware([
            InitializeTenancyByDomain::class,
            'auth:sanctum',
            EnsureAuthenticatedUserHasCurrentTenantAccess::class,
        ]);

    Mcp::web('/{tenant}/mcp/ledgerleap', LedgerLeapServer::class)
        ->middleware([
            InitializeTenancyByPath::class,
            'auth:sanctum',
            EnsureAuthenticatedUserHasCurrentTenantAccess::class,
        ]);
});

Mcp::local('ledgerleap:mcp', LedgerLeapServer::class);
