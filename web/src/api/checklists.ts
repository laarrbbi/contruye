import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

export interface ChecklistItemRow {
  id: number
  text: string
  checked: boolean
}

export interface CustomTaskRow {
  id: number
  body: string
  is_done: boolean
}

export interface ModuleChecklistData {
  id: number
  order: number
  title: string
  is_locked: boolean
  items_count: number
  /** Empty when the module is locked — the steps are paid content. */
  items: ChecklistItemRow[]
  custom_tasks: CustomTaskRow[]
}

export interface ChecklistsData {
  modules: ModuleChecklistData[]
}

const keyFor = (slug: string | undefined) => ['checklists', slug]

export function useChecklists(slug: string | undefined, enabled = true) {
  return useQuery<ChecklistsData>({
    queryKey: keyFor(slug),
    enabled: enabled && !!slug,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/checklists`)
      return data.data as ChecklistsData
    },
  })
}

/** Mutate the cached checklists for one module, optimistically. */
function patchModule(
  data: ChecklistsData | undefined,
  moduleId: number,
  patch: (module: ModuleChecklistData) => ModuleChecklistData,
): ChecklistsData | undefined {
  if (!data) return data
  return { modules: data.modules.map((m) => (m.id === moduleId ? patch(m) : m)) }
}

export function useToggleChecklistItem(slug: string | undefined, moduleId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ itemId, checked }: { itemId: number; checked: boolean }) => {
      await api.put(`/checklist-items/${itemId}`, { checked })
    },
    onMutate: async ({ itemId, checked }) => {
      await qc.cancelQueries({ queryKey: keyFor(slug) })
      const previous = qc.getQueryData<ChecklistsData>(keyFor(slug))
      qc.setQueryData<ChecklistsData>(keyFor(slug), (data) =>
        patchModule(data, moduleId, (m) => ({
          ...m,
          items: m.items.map((i) => (i.id === itemId ? { ...i, checked } : i)),
        })),
      )
      return { previous }
    },
    onError: (_e, _v, ctx) => ctx?.previous && qc.setQueryData(keyFor(slug), ctx.previous),
    onSettled: () => qc.invalidateQueries({ queryKey: keyFor(slug) }),
  })
}

export function useAddTask(slug: string | undefined, moduleId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (body: string) => {
      const { data } = await api.post(`/modules/${moduleId}/tasks`, { body })
      return data.data as CustomTaskRow
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: keyFor(slug) }),
  })
}

export function useUpdateTask(slug: string | undefined, moduleId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ taskId, ...patch }: { taskId: number; body?: string; is_done?: boolean }) => {
      await api.patch(`/tasks/${taskId}`, patch)
    },
    onMutate: async ({ taskId, ...patch }) => {
      await qc.cancelQueries({ queryKey: keyFor(slug) })
      const previous = qc.getQueryData<ChecklistsData>(keyFor(slug))
      qc.setQueryData<ChecklistsData>(keyFor(slug), (data) =>
        patchModule(data, moduleId, (m) => ({
          ...m,
          custom_tasks: m.custom_tasks.map((t) => (t.id === taskId ? { ...t, ...patch } : t)),
        })),
      )
      return { previous }
    },
    onError: (_e, _v, ctx) => ctx?.previous && qc.setQueryData(keyFor(slug), ctx.previous),
    onSettled: () => qc.invalidateQueries({ queryKey: keyFor(slug) }),
  })
}

export function useDeleteTask(slug: string | undefined, moduleId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (taskId: number) => {
      await api.delete(`/tasks/${taskId}`)
    },
    onMutate: async (taskId) => {
      await qc.cancelQueries({ queryKey: keyFor(slug) })
      const previous = qc.getQueryData<ChecklistsData>(keyFor(slug))
      qc.setQueryData<ChecklistsData>(keyFor(slug), (data) =>
        patchModule(data, moduleId, (m) => ({
          ...m,
          custom_tasks: m.custom_tasks.filter((t) => t.id !== taskId),
        })),
      )
      return { previous }
    },
    onError: (_e, _v, ctx) => ctx?.previous && qc.setQueryData(keyFor(slug), ctx.previous),
    onSettled: () => qc.invalidateQueries({ queryKey: keyFor(slug) }),
  })
}
