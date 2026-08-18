<?php

namespace App\Probes;

class ProbeResult
{
    /**
     * @param  list<string>  $toolNames
     * @param  list<array{name: string, description: string|null}>  $toolDetails
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $failedPhase = null,
        public readonly ?string $errorClass = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $connectMs = null,
        public readonly ?int $toolsListMs = null,
        public readonly ?int $toolCallMs = null,
        public readonly ?string $serverName = null,
        public readonly ?string $serverVersion = null,
        public readonly ?string $protocolVersion = null,
        public readonly array $toolNames = [],
        public readonly ?string $toolsHash = null,
        public readonly array $toolDetails = [],
    ) {
    }

    public function totalMs(): ?int
    {
        $phases = array_filter([$this->connectMs, $this->toolsListMs, $this->toolCallMs], fn ($v) => $v !== null);

        return $phases === [] ? null : array_sum($phases);
    }
}
