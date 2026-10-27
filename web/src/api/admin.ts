import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ProvinceRef } from './provinces'
import { api, API_URL } from './client'

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type CourseStatus = 'draft' | 'published'

export interface AdminCourse {
  id: number
  slug: string
  title_es: string
  title_en: string
  description_es: string | null
  description_en: string | null
  price_dzd: number
  price_eur: number | null
  status: CourseStatus
  cover_image: string | null
  modules_count: number
  lessons_count: number
  enrollments_count: number
  created_at: string
}

export interface AdminAttachment {
  name: string
  path: string
}

export interface AdminLesson {
  id: number
  order: number
  title_es: string
  title_en: string
  bunny_video_id: string | null
  duration_seconds: number | null
  is_free_preview: boolean
  content_es: string | null
  content_en: string | null
  is_reflex_sheet?: boolean
  /** Each bullet with its admin-only "A VALIDER" state. */
  key_points?: { text: string; needs_validation: boolean }[]
  attachments: AdminAttachment[] | null
}

export interface AdminModule {
  id: number
  order: number
  title_es: string
  title_en: string
  description_es?: string | null
  /** Admin-only: the module description still needs an expert sign-off. */
  description_needs_validation?: boolean
  /** Tagged content blocks with their admin-only validation state. */
  blocks?: { id: number; type: string; body_es: string | null; needs_validation: boolean }[]
  /** Partners attached to this module, in display order. */
  partners?: ModulePartnerRow[]
  lessons: AdminLesson[]
}

export interface AdminCourseDetail extends Omit<AdminCourse, 'modules_count' | 'lessons_count' | 'enrollments_count'> {
  modules_count?: number
  lessons_count?: number
  enrollments_count?: number
  modules: AdminModule[]
}

export interface CoursePayload {
  title_es: string
  title_en: string
  slug?: string | null
  description_es?: string | null
  description_en?: string | null
  price_dzd: number
  price_eur?: number | null
  status: CourseStatus
}

export interface ModulePayload {
  title_es: string
  // Null while the Arabic translation is pending: the API falls back to
  // French, and an empty string would defeat that.
  title_en: string | null
}

export interface LessonPayload {
  title_es: string
  title_en: string | null
  bunny_video_id?: string | null
  duration_seconds?: number | null
  is_free_preview?: boolean
  content_es?: string | null
  content_en?: string | null
  attachments?: AdminAttachment[] | null
}

export interface AdminCoupon {
  id: number
  code: string
  percent_off: number
  max_uses: number | null
  uses: number
  expires_at: string | null
  created_at?: string
}

export interface CouponPayload {
  code: string
  percent_off: number
  max_uses?: number | null
  expires_at?: string | null
}

// ---------------------------------------------------------------------------
// Query keys
// ---------------------------------------------------------------------------

export const adminKeys = {
  courses: ['admin', 'courses'] as const,
  course: (id: number) => ['admin', 'courses', id] as const,
  coupons: ['admin', 'coupons'] as const,
  stats: ['admin', 'stats'] as const,
  orders: (filters: AdminOrderFilters) => ['admin', 'orders', filters] as const,
  order: (id: number) => ['admin', 'orders', 'detail', id] as const,
  students: (filters: AdminStudentFilters) => ['admin', 'students', filters] as const,
  student: (id: number) => ['admin', 'students', 'detail', id] as const,
  reviews: (filters: AdminReviewFilters) => ['admin', 'reviews', filters] as const,
  campaigns: ['admin', 'campaigns'] as const,
}

// ---------------------------------------------------------------------------
// Courses
// ---------------------------------------------------------------------------

export function useAdminCourses() {
  return useQuery<AdminCourse[]>({
    queryKey: adminKeys.courses,
    queryFn: async () => {
      const { data } = await api.get('/admin/courses')
      return data.data as AdminCourse[]
    },
  })
}

export function useAdminCourse(id: number) {
  return useQuery<AdminCourseDetail>({
    queryKey: adminKeys.course(id),
    queryFn: async () => {
      const { data } = await api.get(`/admin/courses/${id}`)
      return data.data as AdminCourseDetail
    },
    enabled: Number.isFinite(id) && id > 0,
  })
}

export function useCreateCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CoursePayload) => {
      const { data } = await api.post('/admin/courses', payload)
      return data.data as AdminCourse
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

export function useUpdateCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: CoursePayload & { id: number }) => {
      const { data } = await api.put(`/admin/courses/${id}`, payload)
      return data.data as AdminCourse
    },
    onSuccess: (_data, variables) => {
      qc.invalidateQueries({ queryKey: adminKeys.courses })
      qc.invalidateQueries({ queryKey: adminKeys.course(variables.id) })
    },
  })
}

export function useDeleteCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/courses/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

// ---------------------------------------------------------------------------
// Modules
// ---------------------------------------------------------------------------

export function useCreateModule(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ModulePayload) => {
      const { data } = await api.post(`/admin/courses/${courseId}/modules`, payload)
      return data.data as AdminModule
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

export function useUpdateModule(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: ModulePayload & { id: number }) => {
      const { data } = await api.put(`/admin/modules/${id}`, payload)
      return data.data as AdminModule
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
    },
  })
}

export function useDeleteModule(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/modules/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

/** Optimistic reorder of the modules of a course. */
export function useReorderModules(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (moduleIds: number[]) => {
      await api.put(`/admin/courses/${courseId}/modules/reorder`, { module_ids: moduleIds })
    },
    onMutate: async (moduleIds) => {
      await qc.cancelQueries({ queryKey: adminKeys.course(courseId) })
      const previous = qc.getQueryData<AdminCourseDetail>(adminKeys.course(courseId))
      if (previous) {
        const byId = new Map(previous.modules.map((m) => [m.id, m]))
        const reordered = moduleIds
          .map((id) => byId.get(id))
          .filter((m): m is AdminModule => m !== undefined)
        qc.setQueryData<AdminCourseDetail>(adminKeys.course(courseId), { ...previous, modules: reordered })
      }
      return { previous }
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) {
        qc.setQueryData(adminKeys.course(courseId), context.previous)
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
    },
  })
}

// ---------------------------------------------------------------------------
// Lessons
// ---------------------------------------------------------------------------

export function useCreateLesson(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ moduleId, ...payload }: LessonPayload & { moduleId: number }) => {
      const { data } = await api.post(`/admin/modules/${moduleId}/lessons`, payload)
      return data.data as AdminLesson
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

export function useUpdateLesson(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: LessonPayload & { id: number }) => {
      const { data } = await api.put(`/admin/lessons/${id}`, payload)
      return data.data as AdminLesson
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
    },
  })
}

export function useDeleteLesson(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/lessons/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
      qc.invalidateQueries({ queryKey: adminKeys.courses })
    },
  })
}

/** Optimistic reorder of the lessons inside one module. */
export function useReorderLessons(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ moduleId, lessonIds }: { moduleId: number; lessonIds: number[] }) => {
      await api.put(`/admin/modules/${moduleId}/lessons/reorder`, { lesson_ids: lessonIds })
    },
    onMutate: async ({ moduleId, lessonIds }) => {
      await qc.cancelQueries({ queryKey: adminKeys.course(courseId) })
      const previous = qc.getQueryData<AdminCourseDetail>(adminKeys.course(courseId))
      if (previous) {
        const modules = previous.modules.map((m) => {
          if (m.id !== moduleId) return m
          const byId = new Map(m.lessons.map((l) => [l.id, l]))
          const reordered = lessonIds
            .map((id) => byId.get(id))
            .filter((l): l is AdminLesson => l !== undefined)
          return { ...m, lessons: reordered }
        })
        qc.setQueryData<AdminCourseDetail>(adminKeys.course(courseId), { ...previous, modules })
      }
      return { previous }
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) {
        qc.setQueryData(adminKeys.course(courseId), context.previous)
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
    },
  })
}

// ---------------------------------------------------------------------------
// Coupons
// ---------------------------------------------------------------------------

export function useAdminCoupons() {
  return useQuery<AdminCoupon[]>({
    queryKey: adminKeys.coupons,
    queryFn: async () => {
      const { data } = await api.get('/admin/coupons')
      return data.data as AdminCoupon[]
    },
  })
}

export function useCreateCoupon() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CouponPayload) => {
      const { data } = await api.post('/admin/coupons', payload)
      return data.data as AdminCoupon
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.coupons })
    },
  })
}

export function useUpdateCoupon() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: CouponPayload & { id: number }) => {
      const { data } = await api.put(`/admin/coupons/${id}`, payload)
      return data.data as AdminCoupon
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.coupons })
    },
  })
}

export function useDeleteCoupon() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/coupons/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.coupons })
    },
  })
}

// ---------------------------------------------------------------------------
// Orders, stats & students (M8 admin panel)
// ---------------------------------------------------------------------------

export type OrderStatus = 'pending' | 'awaiting_review' | 'paid' | 'failed' | 'refunded'
export type OrderMethod = 'chargily' | 'stripe' | 'manual_ccp' | 'manual_baridimob' | 'cash'
export type OrderScope = 'full' | 'modules'
export type Currency = 'DZD' | 'EUR'

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface AdminOrderModule {
  id: number
  title: string | null
  unit_price: number
}

export interface AdminOrder {
  id: number
  user: { id: number; name: string; email: string; phone: string | null }
  course: { id: number; slug: string; title: string }
  coupon_id: number | null
  amount: number
  discount_percent: number
  currency: Currency
  method: OrderMethod
  scope: OrderScope
  modules?: AdminOrderModule[]
  status: OrderStatus
  chargily_checkout_id: string | null
  stripe_session_id: string | null
  admin_note: string | null
  has_receipt: boolean
  /** When the buyer sent the CCP / BaridiMob receipt (ISO 8601). */
  receipt_uploaded_at: string | null
  receipt_url: string | null
  /** Present on the detail endpoint; null until the admin issues one. */
  invoice?: { id: number; number: string; issued_at: string; pdf_url: string } | null
  paid_at: string | null
  created_at: string
}

export interface AdminOrderFilters {
  status?: OrderStatus | ''
  method?: OrderMethod | ''
  search?: string
  page?: number
}

export function useAdminOrders(filters: AdminOrderFilters) {
  return useQuery<Paginated<AdminOrder>>({
    queryKey: adminKeys.orders(filters),
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const params: Record<string, string | number> = {}
      if (filters.status) params.status = filters.status
      if (filters.method) params.method = filters.method
      if (filters.search) params.search = filters.search
      if (filters.page) params.page = filters.page
      const { data } = await api.get('/admin/orders', { params })
      return data as Paginated<AdminOrder>
    },
  })
}

export function useAdminOrder(id: number | null) {
  return useQuery<AdminOrder>({
    queryKey: adminKeys.order(id ?? 0),
    enabled: id !== null,
    queryFn: async () => {
      const { data } = await api.get(`/admin/orders/${id}`)
      return data.data as AdminOrder
    },
  })
}

/** Absolute URL for the filtered CSV export — opened via the browser so the Sanctum cookie rides along. */
export function adminOrdersExportUrl(filters: AdminOrderFilters): string {
  const params = new URLSearchParams()
  if (filters.status) params.set('status', filters.status)
  if (filters.method) params.set('method', filters.method)
  if (filters.search) params.set('search', filters.search)
  const qs = params.toString()
  return `${API_URL}/api/admin/orders/export${qs ? `?${qs}` : ''}`
}

function useOrderAction<TVars>(request: (vars: TVars) => Promise<unknown>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: request,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
      qc.invalidateQueries({ queryKey: adminKeys.stats })
    },
  })
}

export function useApproveOrder() {
  return useOrderAction((id: number) => api.post(`/admin/orders/${id}/approve`))
}

export function useGrantOrder() {
  return useOrderAction((id: number) => api.post(`/admin/orders/${id}/grant`))
}

export function useRejectOrder() {
  return useOrderAction(({ id, reason }: { id: number; reason: string }) =>
    api.post(`/admin/orders/${id}/reject`, { reason }),
  )
}

// ---------------------------------------------------------------------------
// Cash paid at the office
// ---------------------------------------------------------------------------

export interface CashOrderPayload {
  course_id: number
  scope: 'full' | 'modules'
  module_ids?: number[]
}

export interface CashQuote {
  scope: 'full' | 'modules'
  total: number
  currency: 'DZD'
  items: { module_id: number; title: string; unit_price: number }[]
}

/**
 * POST /admin/students/{id}/cash-orders. Priced by the server with the same
 * rules as checkout: `preview` returns the amount to collect, `record` books
 * the paid order and enrolls the student.
 */
export function useCashOrder(studentId: number) {
  const qc = useQueryClient()
  const preview = useMutation({
    mutationFn: async (payload: CashOrderPayload) => {
      const { data } = await api.post(`/admin/students/${studentId}/cash-orders`, { ...payload, confirm: false })
      return data.data.quote as CashQuote
    },
  })
  const record = useMutation({
    mutationFn: async (payload: CashOrderPayload) => {
      const { data } = await api.post(`/admin/students/${studentId}/cash-orders`, { ...payload, confirm: true })
      return data.data as AdminOrder
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.student(studentId) })
      qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
      qc.invalidateQueries({ queryKey: ['admin', 'students'] })
      qc.invalidateQueries({ queryKey: adminKeys.stats })
    },
  })
  return { preview, record }
}

// ---------------------------------------------------------------------------
// Invoicing (facture on demand)
// ---------------------------------------------------------------------------

/** The seller (émetteur) block printed on every invoice. */
export interface InvoicingSeller {
  business_name: string
  legal_form: string
  capital: string
  address: string
  rc: string
  nif: string
  nis: string
  ai: string
  phone: string
  email: string
  bank_details: string
  /** Public contact: shown on the site once filled. */
  whatsapp: string
  support_hours: string
}

export interface InvoicingSettings {
  complete: boolean
  missing: (keyof InvoicingSeller)[]
  seller: InvoicingSeller
}

export interface AdminInvoiceLine {
  designation: string
  quantity: number
  unit_gross_c: number
  unit_net_c: number
  total_net_c: number
}

export interface AdminInvoice {
  id: number
  number: string
  issued_at: string
  order: {
    id: number
    user: { id: number; name: string; email: string }
    course_title: string
    scope: OrderScope
    method: OrderMethod
  }
  buyer_name: string
  buyer_address: string
  buyer_rc: string | null
  buyer_nif: string | null
  buyer_nis: string | null
  buyer_ai: string | null
  lines: AdminInvoiceLine[]
  iva_rate: number
  subtotal_net_c: number
  discount_net_c: number
  total_net_c: number
  iva_c: number
  total_gross_c: number
  currency: Currency
  payment_method_label: string
  payment_date: string | null
  notes: string | null
  pdf_url: string
  created_at: string
}

export interface InvoicePayload {
  buyer_name: string
  buyer_address: string
  buyer_rc?: string | null
  buyer_nif?: string | null
  buyer_nis?: string | null
  buyer_ai?: string | null
  iva_rate: number
  notes?: string | null
}

export interface AdminInvoiceFilters {
  search?: string
  page?: number
}

export function useInvoicingSettings(enabled = true) {
  return useQuery<InvoicingSettings>({
    queryKey: ['admin', 'invoicing-settings'],
    enabled,
    queryFn: async () => {
      const { data } = await api.get('/admin/invoicing/settings')
      return data as InvoicingSettings
    },
  })
}

export function useSaveInvoicingSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (seller: Partial<InvoicingSeller>) => {
      const { data } = await api.put('/admin/invoicing/settings', seller)
      return data as InvoicingSettings
    },
    onSuccess: (data) => {
      qc.setQueryData(['admin', 'invoicing-settings'], data)
      // The same block feeds the public footer, FAQ and payment pages.
      qc.invalidateQueries({ queryKey: ['business'] })
    },
  })
}

export function useAdminInvoices(filters: AdminInvoiceFilters) {
  return useQuery<Paginated<AdminInvoice>>({
    queryKey: ['admin', 'invoices', filters],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const params: Record<string, string | number> = {}
      if (filters.search) params.search = filters.search
      if (filters.page) params.page = filters.page
      const { data } = await api.get('/admin/invoices', { params })
      return data as Paginated<AdminInvoice>
    },
  })
}

function useInvoiceAction<TVars>(request: (vars: TVars) => Promise<AdminInvoice>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: request,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'invoices'] })
      qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
    },
  })
}

export function useCreateInvoice() {
  return useInvoiceAction(async ({ orderId, payload }: { orderId: number; payload: InvoicePayload }) => {
    const { data } = await api.post(`/admin/orders/${orderId}/invoice`, payload)
    return data.data as AdminInvoice
  })
}

export function useUpdateInvoice() {
  return useInvoiceAction(async ({ id, payload }: { id: number; payload: InvoicePayload }) => {
    const { data } = await api.put(`/admin/invoices/${id}`, payload)
    return data.data as AdminInvoice
  })
}

export function useSendInvoice() {
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post(`/admin/invoices/${id}/send`)
      return data as { message: string }
    },
  })
}

export interface AdminStats {
  revenue_this_month: { DZD: number; EUR: number }
  orders_by_method: Record<string, { total: number; paid: number }>
  orders_awaiting_review: number
  /** Receipts sent more than RECEIPT_REVIEW_HOURS ago and still unreviewed. */
  orders_awaiting_review_overdue: number
  enrollments_total: number
  enrollments_this_month: number
  students_total: number
  completion_rate: number
  recent_orders: AdminOrder[]
}

export function useAdminStats() {
  return useQuery<AdminStats>({
    queryKey: adminKeys.stats,
    queryFn: async () => {
      const { data } = await api.get('/admin/stats')
      return data.data as AdminStats
    },
  })
}

export interface AdminStudentListItem {
  id: number
  name: string
  email: string
  phone: string | null
  locale: string
  enrollments_count: number
  created_at: string
}

export interface AdminStudentFilters {
  search?: string
  page?: number
}

export function useAdminStudents(filters: AdminStudentFilters) {
  return useQuery<Paginated<AdminStudentListItem>>({
    queryKey: adminKeys.students(filters),
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const params: Record<string, string | number> = {}
      if (filters.search) params.search = filters.search
      if (filters.page) params.page = filters.page
      const { data } = await api.get('/admin/students', { params })
      return data as Paginated<AdminStudentListItem>
    },
  })
}

export interface AdminStudentEnrollment {
  id: number
  course: { id: number; slug: string; title: string }
  full_access: boolean
  owned_modules_count: number
  total_modules_count: number
  expires_at: string | null
  progress_percent: number
  completed_lessons: number
  total_lessons: number
  created_at: string
}

export interface AdminStudentOrder {
  id: number
  course_title: string | null
  amount: number
  currency: Currency
  method: OrderMethod
  status: OrderStatus
  created_at: string
}

export interface AdminStudentDetail {
  id: number
  name: string
  email: string
  phone: string | null
  locale: string
  created_at: string
  enrollments: AdminStudentEnrollment[]
  orders: AdminStudentOrder[]
}

export type ReviewStatus = 'pending' | 'approved' | 'rejected'

export interface AdminReview {
  id: number
  rating: number
  body: string | null
  status: ReviewStatus
  admin_note: string | null
  user: { id: number; name: string; email: string }
  course: { id: number; title: string }
  created_at: string
}

export interface AdminReviewFilters {
  status?: ReviewStatus | ''
  page?: number
}

export interface AdminReviewsResponse {
  data: AdminReview[]
  meta: { current_page: number; last_page: number; total: number; pending_count: number }
}

export function useAdminReviews(filters: AdminReviewFilters) {
  return useQuery<AdminReviewsResponse>({
    queryKey: adminKeys.reviews(filters),
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const params: Record<string, string | number> = {}
      if (filters.status) params.status = filters.status
      if (filters.page) params.page = filters.page
      const { data } = await api.get('/admin/reviews', { params })
      return data as AdminReviewsResponse
    },
  })
}

/** Approve or reject a review. Admins never edit the student's words. */
export function useModerateReview() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      status,
      admin_note,
    }: {
      id: number
      status: 'approved' | 'rejected'
      admin_note?: string | null
    }) => {
      const { data } = await api.post(`/admin/reviews/${id}/moderate`, { status, admin_note })
      return data.data as { id: number; status: ReviewStatus }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'reviews'] })
    },
  })
}

export function useDeleteReview() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/reviews/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'reviews'] })
    },
  })
}

export interface AdminCampaign {
  id: number
  course: { id: number; title: string }
  label_es: string
  /** Null until someone writes it; Arabic visitors read the French name. */
  label_en: string | null
  percent_off: number
  starts_at: string | null
  ends_at: string
  is_active: boolean
  /** Enabled AND started AND not finished — i.e. discounting right now. */
  is_running: boolean
  created_at: string
}

export interface CampaignPayload {
  course_id: number
  label_es: string
  label_en: string | null
  percent_off: number
  starts_at?: string | null
  ends_at: string
  is_active?: boolean
}

export function useAdminCampaigns() {
  return useQuery<AdminCampaign[]>({
    queryKey: adminKeys.campaigns,
    queryFn: async () => {
      const { data } = await api.get('/admin/campaigns')
      return data.data as AdminCampaign[]
    },
  })
}

export function useCreateCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CampaignPayload) => {
      const { data } = await api.post('/admin/campaigns', payload)
      return data.data as AdminCampaign
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: adminKeys.campaigns }),
  })
}

export function useUpdateCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: CampaignPayload & { id: number }) => {
      const { data } = await api.put(`/admin/campaigns/${id}`, payload)
      return data.data as AdminCampaign
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: adminKeys.campaigns }),
  })
}

export function useDeleteCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/campaigns/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: adminKeys.campaigns }),
  })
}

export function useAdminStudent(id: number | null) {
  return useQuery<AdminStudentDetail>({
    queryKey: adminKeys.student(id ?? 0),
    enabled: id !== null,
    queryFn: async () => {
      const { data } = await api.get(`/admin/students/${id}`)
      return data.data as AdminStudentDetail
    },
  })
}

// ---------------------------------------------------------------------------
// Partners (M10)
// ---------------------------------------------------------------------------

export const PARTNER_CATEGORIES = [
  'notario',
  'asesor_fiscal',
  'banco',
  'arquitectura',
  'aseguradora',
  'constructora',
  'abogado',
  'marketing',
  'otro',
] as const

export type PartnerCategory = (typeof PARTNER_CATEGORIES)[number]

export interface AdminPartner {
  id: number
  name: string
  category: PartnerCategory
  description_es: string | null
  description_en: string | null
  website: string | null
  phone: string | null
  email: string | null
  /** Covers every province (bank, insurer, online service) — `provinces` is then empty. */
  is_national: boolean
  provinces: ProvinceRef[]
  is_active: boolean
  modules_count: number
  created_at: string
}

export interface PartnerPayload {
  name: string
  category: PartnerCategory
  description_es?: string | null
  description_en?: string | null
  website?: string | null
  phone?: string | null
  email?: string | null
  is_national?: boolean
  province_ids?: number[]
  is_active?: boolean
}

export interface AdminPartnerList {
  data: AdminPartner[]
  /** Only sent when a province filter is active: the categories with nobody there. */
  meta: { gaps: PartnerCategory[] } | null
}

export function useAdminPartners(search?: string, provinceId?: number) {
  return useQuery<AdminPartnerList>({
    queryKey: ['admin', 'partners', search ?? '', provinceId ?? 0],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.get('/admin/partners', {
        params: {
          ...(search ? { search } : {}),
          ...(provinceId ? { province_id: provinceId } : {}),
        },
      })
      return data as AdminPartnerList
    },
  })
}

export function useCreatePartner() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: PartnerPayload) => {
      const { data } = await api.post('/admin/partners', payload)
      return data.data as AdminPartner
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'partners'] }),
  })
}

export function useUpdatePartner() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: PartnerPayload & { id: number }) => {
      const { data } = await api.put(`/admin/partners/${id}`, payload)
      return data.data as AdminPartner
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'partners'] }),
  })
}

export function useDeletePartner() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/partners/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'partners'] }),
  })
}

export interface ModulePartnerRow {
  partner_id: number
  name: string
  category: PartnerCategory
  order: number
  note_es: string | null
  note_en: string | null
}

export interface ModulePartnerInput {
  partner_id: number
  note_es?: string | null
  note_en?: string | null
}

/** Replace a module's partner list, in display order. */
export function useSyncModulePartners(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ moduleId, partners }: { moduleId: number; partners: ModulePartnerInput[] }) => {
      const { data } = await api.put(`/admin/modules/${moduleId}/partners`, { partners })
      return data.data as ModulePartnerRow[]
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: adminKeys.course(courseId) })
      qc.invalidateQueries({ queryKey: ['admin', 'partners'] })
    },
  })
}
