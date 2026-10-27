import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

export type ReviewStatus = 'pending' | 'approved' | 'rejected'

export interface Review {
  id: number
  rating: number
  body: string | null
  author_name: string | null
  verified_purchase: boolean
  status: ReviewStatus
  created_at: string
}

export interface ReviewSummary {
  count: number
  /** Null until at least one review is approved — never a placeholder score. */
  average: number | null
  /** Star (1-5) → number of approved reviews with that rating. */
  distribution: Record<string, number>
  can_review: boolean
  my_review: Review | null
  current_page: number
  last_page: number
}

export interface ReviewsResponse {
  data: Review[]
  meta: ReviewSummary
}

/**
 * Reviews ten at a time; every page carries the same summary (count, average,
 * histogram), computed over all approved reviews.
 */
export function useReviews(slug: string | undefined) {
  return useInfiniteQuery({
    queryKey: ['reviews', slug],
    enabled: !!slug,
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      const { data } = await api.get(`/courses/${slug}/reviews`, { params: { page: pageParam } })
      return data as ReviewsResponse
    },
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined,
  })
}

export interface ReviewPayload {
  rating: number
  body?: string | null
}

export function useSubmitReview(slug: string | undefined) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ReviewPayload) => {
      const { data } = await api.post(`/courses/${slug}/reviews`, payload)
      return data.data as Review
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['reviews', slug] })
      qc.invalidateQueries({ queryKey: ['course', slug] })
    },
  })
}
