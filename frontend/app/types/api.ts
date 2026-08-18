export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message?: string
  errors?: Record<string, string[]>
}

export interface UserLimits {
  max_monitors: number | null
  min_interval_seconds: number
  synthetic_calls: boolean
  non_email_channels: boolean
  retention_days: number
}

export interface User {
  id: number
  name: string
  email: string
  monitors_count: number
  plan: 'free' | 'pro'
  plan_label: string
  pro_until: string | null
  has_billing_portal: boolean
  limits: UserLimits
}

export interface BillingConfig {
  environment: 'sandbox' | 'live'
  client_token: string
  prices: {
    pro_monthly: string
    pro_yearly: string
  }
  user_id: number
}

export interface BillingPortal {
  overview: string
  cancel: string | null
  payment_method: string | null
}

export interface StatusDay {
  date: string
  checks: number
  failures: number
}

export interface PublicStatus {
  name: string
  status: MonitorStatus
  last_checked_at: string | null
  uptime_90d: number | null
  days: StatusDay[]
  latency: {
    p50_ms: number | null
    p95_ms: number | null
  }
}

export type MonitorStatus = 'up' | 'down' | 'pending'

export interface Monitor {
  id: number
  name: string
  url: string
  status: MonitorStatus
  interval_seconds: number
  tools_count: number | null
  tool_names: string[] | null
  has_auth: boolean
  synthetic_tool_name: string | null
  is_public: boolean
  slug: string | null
  paused: boolean
  open_alerts_count: number
  last_checked_at: string | null
  last_status_change_at: string | null
}

export interface MonitorPayload {
  name: string
  url: string
  interval_seconds?: number
  auth_header_name?: string | null
  auth_header_value?: string | null
  synthetic_tool_name?: string | null
  synthetic_tool_args?: Record<string, unknown> | null
  synthetic_expect_substring?: string | null
  is_public?: boolean
}

export type CheckPhase = 'connect' | 'handshake' | 'tools_list' | 'tool_call'

export interface CheckRow {
  id: number
  started_at: string
  ok: boolean
  failed_phase: CheckPhase | string | null
  error_class: string | null
  error_message: string | null
  latency_ms: number | null
  connect_ms: number | null
  tools_list_ms: number | null
  tool_call_ms: number | null
  server_name: string | null
  server_version: string | null
  protocol_version: string | null
  tools_count: number | null
  tools_drift: boolean
}

export interface PublicTool {
  name: string
  description: string | null
}

export interface PublicCheckReport {
  ok: boolean
  failed_phase: CheckPhase | string | null
  error_class: string | null
  error_message: string | null
  connect_ms: number | null
  tools_list_ms: number | null
  total_ms: number | null
  server_name: string | null
  server_version: string | null
  protocol_version: string | null
  tools: PublicTool[] | null
}

export type ChannelType = 'email' | 'slack' | 'webhook'

export interface AlertChannel {
  id: number
  type: ChannelType
  target: string
  is_active: boolean
}

export interface Alert {
  id: number
  monitor_id: number
  monitor_name: string
  kind: string
  opened_at: string
  resolved_at: string | null
  reason: string | null
  error_message: string | null
}
