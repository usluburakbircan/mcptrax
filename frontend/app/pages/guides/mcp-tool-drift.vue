<template>
  <GuideArticle slug="mcp-tool-drift" cta="signup">
    <p>
      Every MCP client builds its picture of your server from one call: <code>tools/list</code>. The
      names, descriptions, and input schemas it returns become the model's vocabulary for using your
      server. <strong>Tool drift</strong> is when that picture changes — a tool renamed, removed, or
      reshaped — while the server itself stays perfectly "up". It's the failure mode unique to MCP,
      and no conventional monitor can see it.
    </p>

    <h2>Why drift is invisible</h2>
    <p>
      Uptime monitoring answers "does it respond?". Drift is a change in <em>what</em> it says, not
      <em>whether</em> it says it. A server that renamed <code>search_docs</code> to
      <code>searchDocuments</code> passes every health check ever devised: TCP connects, TLS is
      valid, <code>initialize</code> succeeds, <code>tools/list</code> returns 200 with a perfectly
      valid list. Meanwhile every agent, prompt, and workflow built against the old name fails on its
      next call with an "unknown tool" error — typically JSON-RPC <code>-32602</code> — and the
      humans behind them file bug reports against the wrong component.
    </p>

    <h2>Four real failure scenarios</h2>

    <h3>The rename</h3>
    <p>
      An SDK upgrade or a well-meaning refactor changes tool naming convention from
      <code>snake_case</code> to <code>camelCase</code>. Clients that discover tools fresh each
      session recover on reconnect, but plenty of production setups pin tool names: hardcoded
      <code>tools/call</code> invocations in pipelines, allowlists in client configs, prompts that
      instruct the model to "use the search_docs tool". All of them break at once, and the server
      logs show nothing but a few unknown-method errors.
    </p>

    <h3>The schema change</h3>
    <p>
      A new required parameter is added to a tool's <code>inputSchema</code> — say
      <code>region</code> becomes mandatory. Callers built against the old schema now fail
      validation on every call. This one is nastier than a rename because the tool still
      <em>exists</em>: discovery looks fine at a glance, and the errors are per-call and
      argument-dependent, so they're easy to misdiagnose as a model problem ("the LLM keeps calling
      the tool wrong") rather than a contract change.
    </p>

    <h3>The silent description change</h3>
    <p>
      Descriptions are prompts. Models decide when and how to use tools largely from
      <code>description</code> text, so a rewritten description shifts behavior with no error at
      all: a tool stops being chosen, or starts being chosen for the wrong tasks. Teams routinely
      A/B their own prompts while shipping description changes to tools unreviewed. Drift detection
      at least makes these changes visible and timestamped, so behavior shifts can be correlated
      with them.
    </p>

    <h3>The disappearing tool</h3>
    <p>
      A feature flag flips, a plugin fails to load, an upstream credential expires — and the server
      starts advertising 9 tools instead of 12. Nothing errors during startup; the tools are simply
      absent. This scenario is the strongest argument for drift detection as an <em>availability</em>
      signal: a shrinking tool list is very often the first observable symptom of a partial outage,
      appearing well before anything returns a 5xx.
    </p>

    <h2>Detecting drift</h2>
    <p>
      The mechanics are simple; the discipline is the point:
    </p>
    <ul>
      <li>On every check, fetch <code>tools/list</code> (the <NuxtLink to="/guides/check-mcp-server-online">curl walkthrough</NuxtLink> shows the raw call) and normalize it — sort tools by name, canonicalize the JSON.</li>
      <li>Compare against the last known good snapshot: added names, removed names, and changed <code>inputSchema</code> or <code>description</code> per tool.</li>
      <li>Treat removals and schema changes as alert-worthy events with the same urgency as downtime; treat additions as informational.</li>
      <li>Keep history. "When did this tool's schema change?" is the question you'll actually ask mid-incident, and it needs timestamps, not a boolean.</li>
    </ul>
    <p>
      mcptrax does exactly this on every scheduled check: each run's tool list is diffed against the
      previous one, drifted checks are flagged in the history, and a drift opens an alert through
      the same channels as an outage. Combined with the
      <NuxtLink to="/guides/mcp-server-monitoring">other monitoring layers</NuxtLink> — handshake,
      synthetic calls, latency — it closes the gap between "the server responds" and "the server
      still honors its contract".
    </p>

    <h2>Preventing it</h2>
    <ul>
      <li>Treat tool names and schemas as a public API: additive changes only, deprecation windows for removals, and never rename — add the new name and keep the old one delegating to it.</li>
      <li>Put <code>tools/list</code> output in code review: a snapshot test that fails CI when the list changes makes drift a decision instead of an accident.</li>
      <li>Version your server in <code>serverInfo.version</code> and bump it on any contract change, so clients and monitors can correlate drift with releases.</li>
    </ul>
    <p>
      Drift will still happen — dependencies update themselves, flags flip, humans forget. The
      difference monitoring makes is whether you find out from a diff in an alert, or from a user
      three days later.
    </p>
  </GuideArticle>
</template>
