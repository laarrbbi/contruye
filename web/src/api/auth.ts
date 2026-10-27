import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
import type { Locale, User } from './types'

export interface RegisterPayload {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  locale?: Locale
}

export interface LoginPayload {
  email: string
  password: string
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export function useUser() {
  return useQuery<User | null>({
    queryKey: ['user'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/me')
        return data.data as User
      } catch {
        return null
      }
    },
    staleTime: 5 * 60 * 1000,
    retry: false,
  })
}

/**
 * Update the signed-in user's own preferences (currently their wilaya). The
 * `['user']` cache is patched so every partner filter reacts at once.
 */
export function useUpdateProfile() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { wilaya_id?: number | null; locale?: Locale }) => {
      const { data } = await api.patch('/me', payload)
      return data.data as User
    },
    onSuccess: (user) => {
      qc.setQueryData(['user'], user)
    },
  })
}

export function useLogin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      const { data } = await api.post('/login', payload)
      return data.data as User
    },
    onSuccess: (user) => {
      qc.setQueryData(['user'], user)
    },
  })
}

export function useRegister() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: RegisterPayload) => {
      const { data } = await api.post('/register', payload)
      return data.data as User
    },
    onSuccess: (user) => {
      qc.setQueryData(['user'], user)
    },
  })
}

export function useForgotPassword() {
  return useMutation({
    mutationFn: async (payload: ForgotPasswordPayload) => {
      await api.post('/forgot-password', payload)
    },
  })
}

export function useResetPassword() {
  return useMutation({
    mutationFn: async (payload: ResetPasswordPayload) => {
      await api.post('/reset-password', payload)
    },
  })
}

export function useLogout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      await api.post('/logout')
    },
    onSuccess: () => {
      qc.setQueryData(['user'], null)
      qc.clear()
    },
  })
}
