import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { API_URL, api } from './client'
import type { User } from './types'

/** A device the account is signed in on. `id` names the row, never the cookie. */
export interface DeviceSession {
  id: string
  browser: string | null
  os: string | null
  ip: string | null
  last_active_at: string
  current: boolean
}

export function useUpdateEmail() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { email: string; current_password: string }) => {
      const { data } = await api.put('/me/email', payload)
      return data.data as User
    },
    onSuccess: (user) => qc.setQueryData(['user'], user),
  })
}

export function useUpdatePassword() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { current_password: string; password: string; password_confirmation: string }) => {
      const { data } = await api.put('/me/password', payload)
      return data.data as { closed_sessions: number }
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-sessions'] }),
  })
}

export function useDeviceSessions() {
  return useQuery({
    queryKey: ['device-sessions'],
    queryFn: async () => {
      const { data } = await api.get('/me/sessions')
      return {
        sessions: data.data as DeviceSession[],
        supported: Boolean(data.meta?.supported),
        max_devices: Number(data.meta?.max_devices ?? 2),
      }
    },
  })
}

export function useCloseSession() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      await api.delete(`/me/sessions/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-sessions'] }),
  })
}

export function useCloseOtherSessions() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.delete('/me/sessions')
      return data.data as { closed_sessions: number }
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-sessions'] }),
  })
}

/** The student's own records as a JSON file: plain navigation, so the cookie rides along. */
export const ACCOUNT_EXPORT_URL = `${API_URL}/api/me/export`
