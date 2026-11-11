<?php

use App\Mcp\Servers\NexaCampusServer;
use Laravel\Mcp\Facades\Mcp;

// Local stdio server for AI coding agents (start with: php artisan mcp:start nexacampus).
// NOTE: do NOT expose via Mcp::web() until HTTP auth + per-role gating is designed.
Mcp::local('nexacampus', NexaCampusServer::class);
