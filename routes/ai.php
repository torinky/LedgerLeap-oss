<?php

use App\Mcp\Servers\LedgerLeapServer;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

Mcp::web('/mcp/ledgerleap', LedgerLeapServer::class)
    ->middleware([
        InitializeTenancyByDomain::class,
        'auth:sanctum',
    ]);

Mcp::local('ledgerleap:mcp', LedgerLeapServer::class);
