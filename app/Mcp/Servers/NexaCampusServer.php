<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\UiConventionsResource;
use App\Mcp\Tools\ListAppRoutesTool;
use App\Mcp\Tools\ListDomainResourcesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('NexaCampus Server')]
#[Version('0.1.0')]
#[Instructions('NexaCampus is an Indonesian higher-education academic information system (SIAKAD) built with Laravel 12. This server exposes READ-ONLY discovery tools: use list-domain-resources for the CRUD page map per domain area, list-app-routes for portal URLs, and the ui-conventions resource before generating any UI code. All tools are read-only; no data is ever modified.')]
class NexaCampusServer extends Server
{
    protected array $tools = [
        ListDomainResourcesTool::class,
        ListAppRoutesTool::class,
    ];

    protected array $resources = [
        UiConventionsResource::class,
    ];

    protected array $prompts = [
        //
    ];
}
