<template>
  <GuideArticle slug="streamable-http-vs-sse" cta="checker">
    <p>
      If you run a remote MCP server in 2026, you're dealing with two HTTP transports: the original
      HTTP+SSE pair from the first public spec, and Streamable HTTP, which replaced it. Plenty of
      servers still speak only the old one, plenty of clients still fall back to it, and the
      differences are exactly the kind of thing that breaks quietly during a migration. Here's how
      the two actually work on the wire and what to do about it.
    </p>

    <h2>A short history</h2>
    <p>
      The <code>2024-11-05</code> revision of MCP defined two transports: stdio for local servers,
      and HTTP+SSE for remote ones. The <code>2025-03-26</code> revision replaced HTTP+SSE with
      Streamable HTTP, and later revisions (<code>2025-06-18</code> onward) refined it — most
      notably requiring the <code>MCP-Protocol-Version</code> header on subsequent requests. HTTP+SSE
      has been deprecated for years now, but "deprecated" and "gone" are different things: a long
      tail of tutorials, templates, and deployed servers keeps it alive.
    </p>

    <h2>How HTTP+SSE works</h2>
    <p>
      The old transport splits the conversation across two endpoints:
    </p>
    <ul>
      <li>The client opens a long-lived <code>GET</code> to an SSE endpoint (conventionally <code>/sse</code>).</li>
      <li>The server's first SSE message is an <code>endpoint</code> event containing a URL, usually with a session token baked in.</li>
      <li>The client POSTs every JSON-RPC message to that URL, and every response comes back over the SSE stream — the POSTs themselves just return 202.</li>
    </ul>
    <p>
      The design works, but the always-open GET is the weakness: it ties a session to one connection
      on one server instance, which fights load balancers, serverless platforms that bill by
      connection time, and proxies that kill idle streams. Losing the stream loses the session.
    </p>

    <h2>How Streamable HTTP works</h2>
    <p>
      Streamable HTTP collapses everything onto a single endpoint (conventionally
      <code>/mcp</code>):
    </p>
    <ul>
      <li>The client POSTs each JSON-RPC message to the endpoint with <code>Accept: application/json, text/event-stream</code>.</li>
      <li>The server chooses per request: answer with a plain <code>application/json</code> body, or open an SSE stream for that request — useful for streaming progress before the final result.</li>
      <li>Sessions are explicit and header-based: the server may return <code>Mcp-Session-Id</code> on initialize, and the client echoes it on every later request.</li>
      <li>An optional standing <code>GET</code> lets servers push unsolicited messages; servers that don't need it simply return <code>405</code>.</li>
      <li>SSE messages can carry event IDs, and a client can resume a broken stream with <code>Last-Event-ID</code> — reconnection is part of the design rather than a session-ending event.</li>
    </ul>
    <p>
      The practical consequence: a Streamable HTTP server can be completely stateless per request,
      which is why it deploys cleanly to serverless platforms and behind ordinary load balancers.
    </p>

    <h2>What clients expect in 2026</h2>
    <p>
      Streamable HTTP is the default everywhere that matters — the official SDKs, Claude, and the
      major MCP client implementations all speak it first. Well-behaved clients still implement a
      fallback dance for legacy servers: try a POST to the given URL, and if it fails in a way that
      suggests an old server, attempt a GET expecting an SSE <code>endpoint</code> event. But you
      cannot rely on every client doing this, and the fallback adds a failed round trip to every
      connection. A new server has no reason to expose only SSE; an old one serving real traffic
      should migrate.
    </p>

    <h2>Migration notes</h2>
    <ul>
      <li><strong>Add, don't replace.</strong> Serve Streamable HTTP on <code>/mcp</code> while keeping the legacy <code>/sse</code> endpoint alive until traffic on it dies. Both can share the underlying server logic; only the transport layer differs.</li>
      <li><strong>Make sessions real.</strong> If you issue <code>Mcp-Session-Id</code>, store sessions somewhere all instances can reach, and return <code>404</code> for unknown sessions so clients know to re-initialize — see the <NuxtLink to="/guides/mcp-server-initialize-failures">session failure modes</NuxtLink>.</li>
      <li><strong>Fix your proxy config.</strong> SSE responses on the new endpoint still need buffering disabled and generous read timeouts, same as the old one.</li>
      <li><strong>Honor the version header.</strong> Accept <code>MCP-Protocol-Version</code> on requests and validate it against what you negotiated — silently ignoring it hides client bugs; rejecting it without supporting the negotiation flow breaks valid clients.</li>
      <li><strong>Watch both endpoints during the transition.</strong> A migration is precisely when handshake regressions appear, and they'll appear on whichever endpoint you're not manually testing. Scheduled checks against both — the approach in our <NuxtLink to="/guides/mcp-server-monitoring">monitoring guide</NuxtLink> — cover the gap.</li>
    </ul>
    <p>
      If you're unsure what your server actually speaks today, a single POST tells you: a Streamable
      HTTP server answers <code>initialize</code> directly; a legacy server typically returns 404 or
      405 on the POST and only makes sense once you GET its SSE endpoint. The
      <NuxtLink to="/guides/check-mcp-server-online">curl walkthrough</NuxtLink> shows the exact
      requests.
    </p>
  </GuideArticle>
</template>
