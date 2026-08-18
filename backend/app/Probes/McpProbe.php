<?php

namespace App\Probes;

use App\Models\Monitor;
use Laravel\Mcp\Client;
use Throwable;

class McpProbe
{
    public const PHASE_INITIALIZE = 'initialize';

    public const PHASE_TOOLS_LIST = 'tools_list';

    public const PHASE_TOOL_CALL = 'tool_call';

    public function __construct(protected float $timeoutSeconds = 10.0)
    {
    }

    public function run(Monitor $monitor): ProbeResult
    {
        $client = Client::web($monitor->url)->withTimeout($this->timeoutSeconds);

        if ($monitor->auth_header_name && $monitor->auth_header_value) {
            $client->withHeaders([$monitor->auth_header_name => $monitor->auth_header_value]);
        }

        $connectMs = null;
        $toolsListMs = null;
        $toolCallMs = null;
        $serverName = null;
        $serverVersion = null;
        $protocolVersion = null;
        $toolNames = [];
        $toolsHash = null;

        try {
            $start = hrtime(true);

            try {
                $client->connect();
                $connectMs = $this->elapsedMs($start);
            } catch (Throwable $e) {
                return $this->failure(self::PHASE_INITIALIZE, $e, connectMs: $this->elapsedMs($start));
            }

            $init = $client->initializeResult();

            if ($init !== null) {
                $serverName = $init->serverInfo->name;
                $serverVersion = $init->serverInfo->version;
                $protocolVersion = $init->protocolVersion;
            }

            $start = hrtime(true);

            try {
                $tools = $client->tools();
                $toolsListMs = $this->elapsedMs($start);
            } catch (Throwable $e) {
                return $this->failure(self::PHASE_TOOLS_LIST, $e,
                    connectMs: $connectMs,
                    toolsListMs: $this->elapsedMs($start),
                    serverName: $serverName,
                    serverVersion: $serverVersion,
                    protocolVersion: $protocolVersion,
                );
            }

            $toolNames = $tools->keys()->sort()->values()->all();
            $toolsHash = $this->hashTools($tools->map(fn ($tool) => $tool->inputSchema)->all());
            $toolDetails = $tools->values()
                ->map(fn ($tool) => ['name' => $tool->name, 'description' => $tool->description])
                ->sortBy('name')
                ->values()
                ->all();

            if ($monitor->synthetic_tool_name) {
                $start = hrtime(true);

                try {
                    $result = $client->callTool(
                        $monitor->synthetic_tool_name,
                        $monitor->synthetic_tool_args ?? [],
                    );
                    $toolCallMs = $this->elapsedMs($start);

                    if ($result->isError) {
                        return $this->failure(self::PHASE_TOOL_CALL,
                            new ProbeAssertionException('Tool returned isError=true: '.mb_substr($result->text(), 0, 500)),
                            connectMs: $connectMs, toolsListMs: $toolsListMs, toolCallMs: $toolCallMs,
                            serverName: $serverName, serverVersion: $serverVersion, protocolVersion: $protocolVersion,
                            toolNames: $toolNames, toolsHash: $toolsHash,
                        );
                    }

                    $expect = $monitor->synthetic_expect_substring;

                    if ($expect !== null && $expect !== '' && ! str_contains($result->text(), $expect)) {
                        return $this->failure(self::PHASE_TOOL_CALL,
                            new ProbeAssertionException("Expected substring not found in tool result: \"{$expect}\""),
                            connectMs: $connectMs, toolsListMs: $toolsListMs, toolCallMs: $toolCallMs,
                            serverName: $serverName, serverVersion: $serverVersion, protocolVersion: $protocolVersion,
                            toolNames: $toolNames, toolsHash: $toolsHash,
                        );
                    }
                } catch (Throwable $e) {
                    return $this->failure(self::PHASE_TOOL_CALL, $e,
                        connectMs: $connectMs, toolsListMs: $toolsListMs, toolCallMs: $this->elapsedMs($start),
                        serverName: $serverName, serverVersion: $serverVersion, protocolVersion: $protocolVersion,
                        toolNames: $toolNames, toolsHash: $toolsHash,
                    );
                }
            }

            return new ProbeResult(
                ok: true,
                connectMs: $connectMs,
                toolsListMs: $toolsListMs,
                toolCallMs: $toolCallMs,
                serverName: $serverName,
                serverVersion: $serverVersion,
                protocolVersion: $protocolVersion,
                toolNames: $toolNames,
                toolsHash: $toolsHash,
                toolDetails: $toolDetails,
            );
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // Kopan oturumu kapatamamak probun sonucunu değiştirmez.
            }
        }
    }

    /**
     * Araç adı => inputSchema haritasının deterministik özeti; değişim
     * "tool drift" olayı olarak raporlanır.
     *
     * @param  array<string, array<string, mixed>>  $toolSchemas
     */
    protected function hashTools(array $toolSchemas): string
    {
        ksort($toolSchemas);

        foreach ($toolSchemas as &$schema) {
            $this->ksortRecursive($schema);
        }

        return hash('sha256', json_encode($toolSchemas));
    }

    protected function ksortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    protected function elapsedMs(int $startHrtime): int
    {
        return (int) ((hrtime(true) - $startHrtime) / 1_000_000);
    }

    /**
     * @param  list<string>  $toolNames
     */
    protected function failure(
        string $phase,
        Throwable $e,
        ?int $connectMs = null,
        ?int $toolsListMs = null,
        ?int $toolCallMs = null,
        ?string $serverName = null,
        ?string $serverVersion = null,
        ?string $protocolVersion = null,
        array $toolNames = [],
        ?string $toolsHash = null,
    ): ProbeResult {
        return new ProbeResult(
            ok: false,
            failedPhase: $phase,
            errorClass: $e::class,
            errorMessage: mb_substr($e->getMessage(), 0, 2000),
            connectMs: $connectMs,
            toolsListMs: $toolsListMs,
            toolCallMs: $toolCallMs,
            serverName: $serverName,
            serverVersion: $serverVersion,
            protocolVersion: $protocolVersion,
            toolNames: $toolNames,
            toolsHash: $toolsHash,
        );
    }
}
