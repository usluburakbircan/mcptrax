<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddTool extends Tool
{
    protected string $name = 'add';

    protected string $description = 'Adds two numbers.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'a' => $schema->number()->required(),
            'b' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $sum = (float) $request->get('a', 0) + (float) $request->get('b', 0);

        return Response::text('sum: '.$sum);
    }
}
