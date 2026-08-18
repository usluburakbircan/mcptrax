<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

/**
 * MCP Streamable HTTP konuşmasını Http::fake ile taklit eder. Sıra:
 * initialize (id 1) → notifications/initialized (202) → tools/list (id 2)
 * → opsiyonel tools/call (id 3).
 */
trait FakesMcp
{
    /**
     * @param  list<array<string, mixed>>  $tools
     */
    protected function fakeMcpServer(
        string $url = 'https://example.test/mcp',
        array $tools = [['name' => 'echo', 'inputSchema' => ['type' => 'object']]],
        ?string $toolCallText = null,
        bool $toolCallIsError = false,
    ): void {
        $sequence = Http::sequence()
            ->push($this->jsonRpcResult(1, [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => ['name' => 'Fake Server', 'version' => '1.2.3'],
            ]))
            ->push('', 202)
            ->push($this->jsonRpcResult(2, ['tools' => $tools]));

        if ($toolCallText !== null) {
            $sequence->push($this->jsonRpcResult(3, [
                'content' => [['type' => 'text', 'text' => $toolCallText]],
                'isError' => $toolCallIsError,
            ]));
        }

        $sequence->whenEmpty(Http::response('', 202));

        Http::fake([$url => $sequence]);
    }

    protected function fakeMcpServerDown(string $url = 'https://example.test/mcp', int $status = 500): void
    {
        Http::fake([$url => Http::response('upstream error', $status)]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function jsonRpcResult(int $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }
}
