<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class EchoTool extends Tool
{
    protected string $name = 'echo';

    protected string $description = 'Echoes back the given message.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->description('Message to echo back.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return Response::text('echo: '.(string) $request->get('message', ''));
    }
}
