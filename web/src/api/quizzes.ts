import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

export interface QuizQuestion {
  numero: number
  pregunta: string
  opciones: { letra: string; texto: string }[]
}

export interface QuizAttemptMeta {
  id: number
  started_at: string
  /** Set on timed attempts (the final exam). */
  expires_at: string | null
  submitted_at: string | null
  score: number | null
  total: number
  passed: boolean | null
}

export interface QuizData {
  id: number
  kind: 'module' | 'final'
  module_id: number | null
  title: string
  pass_mark: number
  questions_count: number
  duration_minutes: number | null
  /** The final exam's questions only come with a running attempt. */
  questions: QuizQuestion[] | null
  open_attempt: QuizAttemptMeta | null
  attempts?: QuizAttemptMeta[]
}

export interface QuizResult extends QuizAttemptMeta {
  results: { numero: number; answer: string | null; is_correct: boolean; correct_answer: string | null }[]
}

export function useQuiz(quizId: number | undefined) {
  return useQuery<QuizData>({
    queryKey: ['quiz', quizId],
    enabled: quizId !== undefined,
    retry: false,
    queryFn: async () => {
      const { data } = await api.get(`/quizzes/${quizId}`)
      return data.data as QuizData
    },
  })
}

/** Start (or resume) an attempt: the response carries the questions. */
export function useStartQuiz(quizId: number | undefined) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.post(`/quizzes/${quizId}/attempts`)
      return data.data as QuizData
    },
    onSuccess: (quiz) => qc.setQueryData(['quiz', quizId], (old: QuizData | undefined) => ({ ...old, ...quiz })),
  })
}

export function useSubmitQuiz(slug: string | undefined, quizId: number | undefined) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ attemptId, answers }: { attemptId: number; answers: Record<number, string> }) => {
      const { data } = await api.post(`/quiz-attempts/${attemptId}/submit`, { answers })
      return data.data as QuizResult
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['quiz', quizId] })
      // The sidebar shows the pass badge.
      qc.invalidateQueries({ queryKey: ['learn', slug] })
    },
  })
}
