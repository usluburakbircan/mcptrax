<template>
  <GuideArticle slug="mcp-server-monitoring" cta="signup">
    <p>
      MCP servers have a failure profile that classic uptime tooling was never designed for. An HTTP
      monitor tells you whether a URL returns 200. An MCP server can return 200 all day while every
      agent that depends on it is broken. This guide covers why that happens, what a meaningful MCP
      health check looks like, and how to build monitoring that catches the failures your users
      actually hit.
    </p>

    <h2>Why MCP servers fail silently</h2>
    <p>
      The Model Context Protocol runs JSON-RPC 2.0 over a transport — today usually Streamable HTTP,
      sometimes the older HTTP+SSE pair, or stdio for local servers. A client doesn't just fetch a
      page; it performs a lifecycle: an <code>initialize</code> request that negotiates protocol
      version and capabilities, an <code>initialized</code> notification, and only then real work
      like <code>tools/list</code> and <code>tools/call</code>.
    </p>
    <p>
      Every step in that lifecycle is a place to fail that a ping never sees:
    </p>
    <ul>
      <li>The endpoint responds 200 to GET but rejects the JSON-RPC POST — a misconfigured route or a proxy serving a fallback page.</li>
      <li><code>initialize</code> returns an error because a deploy changed the supported protocol version, broke session handling, or invalidated credentials.</li>
      <li>The handshake succeeds but <code>tools/list</code> comes back empty because a feature flag, a database dependency, or an upstream API is down.</li>
      <li>The tool list is intact but a tool's behavior broke — it errors on every call or returns garbage after an upstream schema change.</li>
      <li>Everything works, but a renamed tool or changed input schema broke every client that had already discovered the old definition — see
        <NuxtLink to="/guides/mcp-tool-drift">tool drift</NuxtLink>.</li>
    </ul>
    <p>
      None of these produce a down status on an HTTP monitor. All of them produce broken agents, and
      you usually learn about them from a user, hours later.
    </p>

    <h2>The four layers of an MCP health check</h2>

    <h3>1. Connection and handshake</h3>
    <p>
      The baseline check is behaving like a real client: open a connection, send
      <code>initialize</code> with a current <code>protocolVersion</code>, and verify the server
      answers with a valid result — its own protocol version, capabilities, and
      <code>serverInfo</code>. This single step catches TLS problems, dead processes, broken routing,
      auth regressions, and version mismatches. It fails for surprisingly mundane reasons; we've
      collected the common ones in
      <NuxtLink to="/guides/mcp-server-initialize-failures">why initialize fails</NuxtLink>.
    </p>

    <h3>2. tools/list and drift detection</h3>
    <p>
      After the handshake, request <code>tools/list</code> and record what comes back: tool names,
      descriptions, input schemas. Two distinct signals live here. First, availability — an empty or
      erroring tool list on a server that had twelve tools yesterday is an incident. Second,
      <strong>drift</strong> — compare today's list against the last known good one. A removed or
      renamed tool, or a changed schema, is invisible to uptime math but breaks clients immediately.
    </p>

    <h3>3. Synthetic tool calls</h3>
    <p>
      Listing tools proves the server can describe itself, not that it works. A synthetic check goes
      one step further: call one real tool with fixed arguments on every check and assert on the
      response — at minimum that it isn't an error, ideally that it contains an expected substring.
      This is the only layer that catches "the server is up but the tool's upstream dependency is
      down", which in practice is one of the most common real-world failures.
    </p>

    <h3>4. Latency</h3>
    <p>
      Record timings per phase — connect, <code>tools/list</code>, tool call — not just a single
      total. Agents typically enforce tool timeouts; a server that slides from 300 ms to 9 seconds is
      effectively down for them long before it stops responding entirely. Percentiles (p50/p95) over
      days tell you whether a slow check was a blip or a trend.
    </p>

    <h2>Choosing intervals and alerting</h2>
    <p>
      A 15-minute interval is fine for a personal project; for a server other people's products
      depend on, a 1-minute interval means you know about a failure roughly when the first user hits
      it, not after hundreds have. Whatever the interval, alerting discipline matters more than
      frequency:
    </p>
    <ul>
      <li>Alert on <strong>state transitions</strong> (up → down, drift detected), not on every failed check, or you'll train yourself to ignore the channel.</li>
      <li>Include the failing phase and the actual error message in the alert. "Down" is a page; "initialize returned 401 after 14:32 deploy" is a diagnosis.</li>
      <li>Resolve notifications matter: knowing an incident lasted 4 minutes instead of 4 hours changes how you respond.</li>
    </ul>

    <h2>How mcptrax implements this</h2>
    <p>
      mcptrax runs the full lifecycle on every check: connect, <code>initialize</code>,
      <code>initialized</code>, <code>tools/list</code>, and optionally a synthetic
      <code>tools/call</code> with your arguments and an expected substring. Each phase is timed
      separately, the tool list is diffed against the previous check for drift, and alerts go to
      email, Slack, or a webhook with the failing phase and error attached. Auth headers are
      supported for private servers, and each monitor can expose a public status page with 90 days
      of history.
    </p>
    <p>
      If you want to see what a full protocol check looks like before wiring anything up, the
      <NuxtLink to="/guides/check-mcp-server-online">manual curl walkthrough</NuxtLink> shows every
      request on the wire — and the free checker on the homepage runs the same sequence against any
      URL you give it.
    </p>
  </GuideArticle>
</template>
