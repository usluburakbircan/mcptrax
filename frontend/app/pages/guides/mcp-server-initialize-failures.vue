<template>
  <GuideArticle slug="mcp-server-initialize-failures" cta="checker">
    <p>
      The <code>initialize</code> handshake is the front door of every MCP session: the client sends
      its protocol version, capabilities, and client info; the server answers with its own. When it
      fails, nothing else works — and because most MCP clients surface handshake failures as a vague
      "failed to connect", the real cause hides in the HTTP layer. These are the five causes we see
      most often across monitored servers, roughly in order of frequency, with how to debug each.
    </p>

    <h2>1. Wrong or missing Accept header</h2>
    <p>
      Streamable HTTP requires the client to send both content types in its <code>Accept</code>
      header, because the server may answer a POST with either plain JSON or an SSE stream:
    </p>
    <pre v-pre><code>Accept: application/json, text/event-stream</code></pre>
    <p>
      Spec-compliant servers reject requests without it — typically with <code>406 Not
      Acceptable</code>. The confusing part is the asymmetry: your curl test with the right headers
      works, while a client library with a stripped-down default fails, or the reverse. Proxies and
      API gateways that rewrite or drop headers cause the same symptom. Debug it by replaying the
      exact request with curl and toggling only the <code>Accept</code> header; if behavior changes,
      you've found it. The same applies to <code>Content-Type: application/json</code> on the POST
      itself.
    </p>

    <h2>2. Protocol version mismatch</h2>
    <p>
      MCP versions are dates: <code>2024-11-05</code>, <code>2025-03-26</code>,
      <code>2025-06-18</code>, and later revisions. The client proposes one in
      <code>initialize</code>; a server that can't support it should respond with the closest version
      it can, and the client decides whether to proceed. Two failure modes show up in practice:
    </p>
    <ul>
      <li>Strict servers that return a JSON-RPC error for any version they don't recognize — often after an SDK upgrade quietly changed which version the client proposes.</li>
      <li>Since <code>2025-06-18</code>, clients must send an <code>MCP-Protocol-Version</code> header on subsequent HTTP requests. Servers that enforce this return <code>400</code> on <code>tools/list</code> even though <code>initialize</code> succeeded — a handshake that "passes" but a session that doesn't.</li>
    </ul>
    <p>
      Debug by reading the <em>server's</em> <code>protocolVersion</code> in the initialize result
      and comparing it with what your client sends afterwards. Pin SDK versions on both sides when
      you upgrade.
    </p>

    <h2>3. Authentication: 401 and 403</h2>
    <p>
      Remote servers increasingly sit behind OAuth 2.1 or a static bearer token. A <code>401</code>
      on <code>initialize</code> means missing or expired credentials; <code>403</code> usually means
      valid credentials without the required scope. The failures worth monitoring for are the slow
      ones: tokens that expire after 30 or 90 days, rotated API keys that one environment missed, or
      an auth provider change that invalidates existing tokens. All of them look like "worked
      yesterday, 401 today". If your server uses OAuth discovery, also verify the protected resource
      metadata endpoint responds — clients fail the handshake when discovery itself is broken.
    </p>

    <h2>4. Session handling</h2>
    <p>
      Streamable HTTP servers may issue a session on the initialize response via the
      <code>Mcp-Session-Id</code> header. From then on the client must echo that header on every
      request; requests without it get <code>400</code>, and requests with an expired or unknown
      session get <code>404</code>, which the client should treat as "start a new session". Bugs
      cluster in three places:
    </p>
    <ul>
      <li>Servers behind load balancers that keep sessions in per-instance memory — the next request lands on a different instance and 404s intermittently.</li>
      <li>Clients that keep a session across a server restart and don't handle the 404-means-reinitialize rule.</li>
      <li>Proxies that strip the <code>Mcp-Session-Id</code> header in one direction.</li>
    </ul>
    <p>
      Intermittent handshake failures under load are almost always this category. Use sticky
      sessions or shared session storage, and test the restart path deliberately.
    </p>

    <h2>5. Timeouts and buffering proxies</h2>
    <p>
      Serverless MCP servers add cold starts: the first <code>initialize</code> after idle can take
      several seconds, and clients with tight connect timeouts give up first — a failure that
      disappears the moment you test it manually, because your test warmed the function. The other
      classic is a reverse proxy that buffers responses: SSE streams need buffering off
      (<code>proxy_buffering off</code> in nginx, or an <code>X-Accel-Buffering: no</code> response
      header), otherwise the initialize response sits in a buffer until timeout. If curl with
      <code>--no-buffer</code> against the origin works but going through the proxy hangs, it's the
      proxy.
    </p>

    <h2>A debugging checklist</h2>
    <ul>
      <li>Replay <code>initialize</code> with curl, exact headers, straight at the origin — the <NuxtLink to="/guides/check-mcp-server-online">walkthrough</NuxtLink> has the full commands.</li>
      <li>Compare origin vs proxy behavior; diff the headers both directions.</li>
      <li>Read the response status precisely: 406 → Accept, 400 → version header or session, 401/403 → auth, 404 → session or route, hang → buffering or cold start.</li>
      <li>Check what protocol version each side actually sends, not what you think it sends.</li>
    </ul>
    <p>
      And because most of these regress on deploys rather than appearing spontaneously, a scheduled
      handshake check — the core of
      <NuxtLink to="/guides/mcp-server-monitoring">MCP monitoring</NuxtLink> — turns each of them
      from a user report into an alert with the failing phase attached.
    </p>
  </GuideArticle>
</template>
