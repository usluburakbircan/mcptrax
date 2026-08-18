<template>
  <GuideArticle slug="check-mcp-server-online" cta="checker">
    <p>
      "Is my MCP server up?" is not answered by opening the URL in a browser — most MCP endpoints
      return an error page or 405 to a plain GET even when they're perfectly healthy. The honest
      answer requires speaking the protocol: an <code>initialize</code> handshake and a
      <code>tools/list</code> call. Both are plain JSON-RPC over HTTP, so curl can do it. Here's the
      complete walkthrough for a Streamable HTTP server.
    </p>

    <h2>Step 1: the initialize request</h2>
    <p>
      Send a JSON-RPC <code>initialize</code> to your endpoint. The <code>-i</code> flag matters —
      you'll need a response header in step 2. The <code>Accept</code> header must offer both
      content types or compliant servers will refuse with 406:
    </p>
    <pre v-pre><code>curl -si -X POST https://your-server.com/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{
    "protocolVersion":"2025-06-18",
    "capabilities":{},
    "clientInfo":{"name":"health-check","version":"1.0"}}}'</code></pre>
    <p>
      A healthy server responds 200 with either a JSON body or an SSE-framed message
      (<code>event: message</code> followed by a <code>data:</code> line — same JSON, different
      wrapper). What you want to see in it:
    </p>
    <pre v-pre><code>{"jsonrpc":"2.0","id":1,"result":{
  "protocolVersion":"2025-06-18",
  "capabilities":{"tools":{}},
  "serverInfo":{"name":"my-server","version":"1.4.2"}}}</code></pre>
    <p>
      Check three things: it's a <code>result</code> (not an <code>error</code>), the
      <code>protocolVersion</code> is one your clients can accept, and <code>capabilities</code>
      includes <code>tools</code> if you expect tools. Also look at the response headers for
      <code>Mcp-Session-Id</code> — if present, copy it; every following request must carry it.
    </p>

    <h2>Step 2: the initialized notification</h2>
    <p>
      Before making requests, a client is supposed to confirm the handshake. Notifications have no
      <code>id</code> and expect no body back — a <code>202 Accepted</code> is the normal response:
    </p>
    <pre v-pre><code>curl -si -X POST https://your-server.com/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -H 'Mcp-Session-Id: PASTE-YOUR-SESSION-ID' \
  -d '{"jsonrpc":"2.0","method":"notifications/initialized"}'</code></pre>
    <p>
      Omit the <code>Mcp-Session-Id</code> line if step 1 didn't return one. Lenient servers work
      without this step; strict ones reject <code>tools/list</code> until they've seen it, so a
      thorough check includes it.
    </p>

    <h2>Step 3: list the tools</h2>
    <pre v-pre><code>curl -si -X POST https://your-server.com/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -H 'Mcp-Session-Id: PASTE-YOUR-SESSION-ID' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'</code></pre>
    <p>
      The result contains a <code>tools</code> array with each tool's <code>name</code>,
      <code>description</code>, and <code>inputSchema</code>. This is the step that separates "the
      process is running" from "the server is actually serving": count the tools and eyeball the
      names. An empty array on a server that should have tools is an outage in every way that
      matters, whatever the status code says.
    </p>

    <h2>Reading the failures</h2>
    <ul>
      <li><code>406</code> — your <code>Accept</code> header doesn't offer both content types.</li>
      <li><code>401</code> / <code>403</code> — auth. Add your <code>Authorization</code> (or custom) header to every request above.</li>
      <li><code>400</code> on step 2 or 3 — usually a missing <code>Mcp-Session-Id</code> or <code>MCP-Protocol-Version</code> header.</li>
      <li><code>404</code> — wrong path, or an expired session id; re-run step 1.</li>
      <li><code>405</code> on POST — possibly a legacy HTTP+SSE server that wants a GET to <code>/sse</code> first; see <NuxtLink to="/guides/streamable-http-vs-sse">the transport guide</NuxtLink>.</li>
      <li>A hang with no response — almost always a buffering proxy or a cold start; the <NuxtLink to="/guides/mcp-server-initialize-failures">initialize failure guide</NuxtLink> covers both.</li>
    </ul>

    <h2>Checking a legacy HTTP+SSE server</h2>
    <p>
      If the POST in step 1 comes back 404 or 405, you may be talking to a server on the old
      transport. Those expect you to open the SSE stream first:
    </p>
    <pre v-pre><code>curl -sN https://your-server.com/sse -H 'Accept: text/event-stream'</code></pre>
    <p>
      A healthy legacy server immediately emits an <code>endpoint</code> event whose
      <code>data:</code> line is a URL, usually containing a session token. Keep that curl running
      in one terminal, POST the same <code>initialize</code> body from step 1 to the endpoint URL in
      another, and watch the response arrive <em>on the stream</em> rather than in the POST reply —
      the POST itself just returns 202. If the stream opens but no <code>endpoint</code> event ever
      arrives, a buffering proxy is holding it back. The <code>-N</code> flag disables curl's own
      buffering so you can tell the difference.
    </p>

    <h2>Making it repeatable</h2>
    <p>
      Once the three requests work, wrap them in a script and pipe the results through
      <code>jq</code> so a human doesn't have to read raw JSON-RPC:
    </p>
    <pre v-pre><code>curl -s ... -d "$TOOLS_LIST_BODY" \
  | sed -n 's/^data: //p' \
  | jq -r '.result.tools[].name'</code></pre>
    <p>
      The <code>sed</code> line strips SSE framing when present and passes plain JSON through
      untouched, so the same pipeline handles both response styles. A one-line cron job comparing
      that output against yesterday's copy is already a primitive drift detector.
    </p>

    <h2>From one check to continuous checks</h2>
    <p>
      This walkthrough proves your server is up <em>right now</em>. The failures that hurt are the
      ones that happen at 3am after a deploy, and the ones a handshake alone can't see — a tool list
      that quietly shrank, a tool whose upstream dependency died. That's a monitoring problem: the
      same sequence, every minute, with the tool list diffed between runs and an alert when anything
      changes. The <NuxtLink to="/guides/mcp-server-monitoring">complete monitoring guide</NuxtLink>
      covers the full setup — or paste your URL into the free checker below and get this entire
      walkthrough run for you in about two seconds.
    </p>
  </GuideArticle>
</template>
