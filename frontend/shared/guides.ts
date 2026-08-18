/**
 * The guide registry — single source of truth for the list page, the
 * article shells (SEO + JSON-LD), cross-links and the sitemap.
 */
export interface GuideMeta {
  slug: string
  title: string
  description: string
  datePublished: string
  dateModified: string
  readMinutes: number
}

export const GUIDES: GuideMeta[] = [
  {
    slug: 'mcp-server-monitoring',
    title: 'MCP Server Monitoring: The Complete Guide',
    description: 'Why MCP servers fail silently, what to monitor — initialize handshake, tools/list drift, synthetic tool calls, latency — and how to set up monitoring that catches real failures.',
    datePublished: '2026-08-18',
    dateModified: '2026-08-18',
    readMinutes: 8,
  },
  {
    slug: 'mcp-server-initialize-failures',
    title: 'Why Your MCP Server Fails the initialize Handshake',
    description: 'The five most common causes of MCP initialize failures — Accept headers, protocol version mismatches, auth, session handling, timeouts — and how to debug each one.',
    datePublished: '2026-08-18',
    dateModified: '2026-08-18',
    readMinutes: 7,
  },
  {
    slug: 'streamable-http-vs-sse',
    title: 'Streamable HTTP vs SSE: MCP Transports Explained',
    description: 'How MCP went from HTTP+SSE to Streamable HTTP, how the two transports actually differ on the wire, what clients expect in 2026, and how to migrate.',
    datePublished: '2026-08-18',
    dateModified: '2026-08-18',
    readMinutes: 7,
  },
  {
    slug: 'check-mcp-server-online',
    title: 'How to Check if Your MCP Server Is Up',
    description: 'A hands-on curl walkthrough of the MCP initialize and tools/list calls — what a healthy response looks like, the errors you will actually see, and how to automate the check.',
    datePublished: '2026-08-18',
    dateModified: '2026-08-18',
    readMinutes: 6,
  },
  {
    slug: 'mcp-tool-drift',
    title: 'Tool Drift: The Silent MCP Server Killer',
    description: 'A renamed tool or a changed input schema breaks every agent that depends on your MCP server — while every uptime check stays green. What tool drift is and how to detect it.',
    datePublished: '2026-08-18',
    dateModified: '2026-08-18',
    readMinutes: 6,
  },
]
