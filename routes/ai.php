<?php

use App\Http\Middleware\EnsureAuthenticatedUserHasCurrentTenantAccess;
use App\Mcp\Servers\LedgerLeapServer;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

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

Mcp::local('ledgerleap:mcp', LedgerLeapServer::class);
