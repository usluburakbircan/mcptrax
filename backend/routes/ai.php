<?php

use App\Mcp\Servers\FixtureServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/demo-mcp', FixtureServer::class);
