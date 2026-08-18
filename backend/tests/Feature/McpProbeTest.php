<?php

use App\Models\Monitor;
use App\Probes\McpProbe;
use Tests\Support\FakesMcp;

uses(FakesMcp::class);

it('probes a healthy server successfully', function () {
    $this->fakeMcpServer(tools: [
        ['name' => 'echo', 'inputSchema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]],
        ['name' => 'add', 'inputSchema' => ['type' => 'object']],
    ]);

    $monitor = Monitor::factory()->create();

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeTrue()
        ->and($result->serverName)->toBe('Fake Server')
        ->and($result->serverVersion)->toBe('1.2.3')
        ->and($result->protocolVersion)->toBe('2025-06-18')
        ->and($result->toolNames)->toBe(['add', 'echo'])
        ->and($result->toolsHash)->not->toBeNull()
        ->and($result->connectMs)->not->toBeNull()
        ->and($result->toolsListMs)->not->toBeNull();
});

it('reports initialize failure when the server errors', function () {
    $this->fakeMcpServerDown();

    $monitor = Monitor::factory()->create();

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeFalse()
        ->and($result->failedPhase)->toBe(McpProbe::PHASE_INITIALIZE)
        ->and($result->errorMessage)->not->toBeNull();
});

it('reports initialize failure on http 401', function () {
    $this->fakeMcpServerDown(status: 401);

    $monitor = Monitor::factory()->create();

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeFalse()
        ->and($result->failedPhase)->toBe(McpProbe::PHASE_INITIALIZE);
});

it('runs a synthetic tool call and passes when the expectation matches', function () {
    $this->fakeMcpServer(toolCallText: 'sum: 3');

    $monitor = Monitor::factory()->create([
        'synthetic_tool_name' => 'add',
        'synthetic_tool_args' => ['a' => 1, 'b' => 2],
        'synthetic_expect_substring' => 'sum: 3',
    ]);

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeTrue()
        ->and($result->toolCallMs)->not->toBeNull();
});

it('fails the tool_call phase when the expected substring is missing', function () {
    $this->fakeMcpServer(toolCallText: 'sum: 999');

    $monitor = Monitor::factory()->create([
        'synthetic_tool_name' => 'add',
        'synthetic_tool_args' => ['a' => 1, 'b' => 2],
        'synthetic_expect_substring' => 'sum: 3',
    ]);

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeFalse()
        ->and($result->failedPhase)->toBe(McpProbe::PHASE_TOOL_CALL);
});

it('fails the tool_call phase when the tool returns isError', function () {
    $this->fakeMcpServer(toolCallText: 'boom', toolCallIsError: true);

    $monitor = Monitor::factory()->create([
        'synthetic_tool_name' => 'add',
        'synthetic_tool_args' => ['a' => 1, 'b' => 2],
    ]);

    $result = (new McpProbe)->run($monitor);

    expect($result->ok)->toBeFalse()
        ->and($result->failedPhase)->toBe(McpProbe::PHASE_TOOL_CALL);
});

it('produces a stable tools hash regardless of key order', function () {
    $probe = new McpProbe;

    $this->fakeMcpServer(url: 'https://one.test/mcp', tools: [
        ['name' => 'echo', 'inputSchema' => ['type' => 'object', 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'number']]]],
    ]);
    $this->fakeMcpServer(url: 'https://two.test/mcp', tools: [
        ['name' => 'echo', 'inputSchema' => ['properties' => ['b' => ['type' => 'number'], 'a' => ['type' => 'string']], 'type' => 'object']],
    ]);

    $first = $probe->run(Monitor::factory()->create(['url' => 'https://one.test/mcp']));
    $second = $probe->run(Monitor::factory()->create(['url' => 'https://two.test/mcp']));

    expect($first->toolsHash)->toBe($second->toolsHash);
});
