import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

/*
 * Nicolás, the course's AI assistant. The browser only ever talks to this
 * API; the model, its key, the credits and the guard all live on the server
 * (api/app/Services/Assistant).
 */

export type AssistantReason = 'not_configured' | 'coming_soon' | 'not_enrolled' | 'paused' | 'no_credits' | 'busy'

export interface AssistantStatus {
  name: string
  available: boolean
  reason: AssistantReason | null
  launched: boolean
  /** An admin trying it before it opens to students. */
  preview?: boolean
  course?: string
  credits: { balance: number; monthly: number; resets_at: string; small: number; large: number } | null
  max_question_chars?: number
}

export interface AssistantSource {
  n: number
  title: string
  path: string | null
}

export interface AssistantMessage {
  id: number
  role: 'user' | 'assistant'
  content: string
  /** The answer as HTML (server-rendered Markdown, raw HTML stripped, the reader's fingerprint in it). */
  html: string | null
  sources: AssistantSource[]
  /** small | large | escalated | cache, or blocked | refusal | error. */
  route: string | null
  created_at: string | null
}

export interface AssistantConversationSummary {
  id: number
  title: string
  updated_at: string | null
}

/** Whether Nicolás has opened to students: the menu drops its «Pronto». */
export function usePublicAssistant() {
  return useQuery({
    queryKey: ['assistant-public'],
    staleTime: 5 * 60_000,
    queryFn: async () => {
      const { data } = await api.get('/public/assistant')
      return data.data as { name: string; launched: boolean }
    },
  })
}

export function useAssistantStatus(enabled: boolean) {
  return useQuery({
    queryKey: ['assistant-status'],
    enabled,
    queryFn: async () => {
      const { data } = await api.get('/assistant')
      return data.data as AssistantStatus
    },
  })
}

export function useAssistantConversations(enabled: boolean) {
  return useQuery({
    queryKey: ['assistant-conversations'],
    enabled,
    queryFn: async () => {
      const { data } = await api.get('/assistant/conversations')
      return data.data as AssistantConversationSummary[]
    },
  })
}

export function useAssistantConversation(id: number | null) {
  return useQuery({
    queryKey: ['assistant-conversation', id],
    enabled: id !== null,
    queryFn: async () => {
      const { data } = await api.get(`/assistant/conversations/${id}`)
      return data.data as { id: number; title: string; messages: AssistantMessage[] }
    },
  })
}

export interface AskResult {
  conversation: { id: number; title: string }
  question: AssistantMessage
  answer: AssistantMessage
  credits: { balance: number; spent: number }
}

export function useAskAssistant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { question: string; conversation_id: number | null }) => {
      const { data } = await api.post('/assistant/messages', payload)
      return data.data as AskResult
    },
    onSuccess: (result) => {
      queryClient.setQueryData(['assistant-conversation', result.conversation.id], (old: { id: number; title: string; messages: AssistantMessage[] } | undefined) => ({
        id: result.conversation.id,
        title: result.conversation.title,
        messages: [...(old?.messages ?? []), result.question, result.answer],
      }))
      queryClient.setQueryData(['assistant-status'], (old: AssistantStatus | undefined) =>
        old?.credits ? { ...old, credits: { ...old.credits, balance: result.credits.balance } } : old,
      )
      void queryClient.invalidateQueries({ queryKey: ['assistant-conversations'] })
    },
  })
}

export function useDeleteAssistantConversation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/assistant/conversations/${id}`)
      return id
    },
    onSuccess: (id) => {
      queryClient.removeQueries({ queryKey: ['assistant-conversation', id] })
      void queryClient.invalidateQueries({ queryKey: ['assistant-conversations'] })
    },
  })
}

// ── Admin ──────────────────────────────────────────────────────────────

export interface AdminAssistantOverview {
  name: string
  configured: boolean
  enabled: boolean
  launched: boolean
  models: Record<'small' | 'large', { id: string; effort: string; credits: number }>
  prompt_version: string
  index: { version: string | null; chunks: number; built_at: string | null }
  eval: {
    passed: boolean
    last: { passed: boolean; score: number; total: number; passed_count: number; ran_at: string | null; failures: string[] } | null
  }
  budget: { daily_usd: number; spent_today_usd: number }
  credits: { monthly: Record<string, number> }
  last_30_days: { questions: number; cost_usd: number; by_route: { route: string; count: number; cost_usd: number; credits: number }[] }
  evals: { id: number; passed: boolean; score: number; total: number; passed_count: number; prompt_version: string; models: string; index_version: string; ran_at: string | null }[]
}

export interface AdminAssistantTrace {
  id: number
  created_at: string | null
  user: { id: number; name: string; email: string } | null
  route: string
  status: string
  model: string | null
  prompt_version: string | null
  index_version: string | null
  tokens: { input: number; output: number; cache_read: number; cache_write: number }
  cost_usd: number
  credits: number
  latency_ms: number
  chunks: number
  tools: { name: string; ok: boolean }[]
  flags: string[]
  error: string | null
}

export function useAdminAssistant() {
  return useQuery({
    queryKey: ['admin-assistant'],
    queryFn: async () => {
      const { data } = await api.get('/admin/assistant')
      return data.data as AdminAssistantOverview
    },
  })
}

export function useAdminAssistantTraces(filters: { route: string | null; flagged: boolean; page: number }) {
  return useQuery({
    queryKey: ['admin-assistant-traces', filters],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.get('/admin/assistant/traces', {
        params: { route: filters.route ?? undefined, flagged: filters.flagged ? 1 : undefined, page: filters.page },
      })
      return data as { data: AdminAssistantTrace[]; meta: { current_page: number; last_page: number; total: number } }
    },
  })
}

export function useGrantAssistantCredits() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { email: string; credits: number; note: string }) => {
      const { data } = await api.post('/admin/assistant/credits', payload)
      return data.data as { user: { id: number; email: string }; credits: number; balance: number }
    },
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['admin-assistant'] }),
  })
}
