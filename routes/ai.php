<?php

use App\Mcp\Servers\LedgerLeapServer;
use Laravel\Mcp\Facades\Mcp;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

Mcp::local('ledgerleap:mcp', LedgerLeapServer::class);
