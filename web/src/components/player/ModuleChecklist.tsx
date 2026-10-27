import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ClipboardList, Plus, X } from 'lucide-react'
import type { ModuleChecklistData } from '@/api/checklists'
import { useAddTask, useDeleteTask, useToggleChecklistItem, useUpdateTask } from '@/api/checklists'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'

function CheckRow({
  checked,
  label,
  onToggle,
  onDelete,
}: {
  checked: boolean
  label: string
  onToggle: () => void
  onDelete?: () => void
}) {
  const { t } = useTranslation()

  return (
    <li className="group flex items-start gap-3 py-2">
      <button
        type="button"
        role="checkbox"
        aria-checked={checked}
        aria-label={label}
        onClick={onToggle}
        className={cn(
          // Ticked tens of times a session: the press answers instantly
          // (100ms scale) and the state itself lands with no crossfade.
          'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
          'transition-transform duration-100 ease-out-strong active:scale-90',
          checked
            ? 'border-success bg-success text-white'
            : 'border-muted-foreground/40 hover:border-primary',
        )}
      >
        {checked && <Check className="h-3.5 w-3.5" aria-hidden />}
      </button>

      <span
        className={cn(
          'bidi-auto flex-1 text-sm leading-relaxed',
          checked && 'text-muted-foreground line-through decoration-muted-foreground/50',
        )}
      >
        {label}
      </span>

      {onDelete && (
        <button
          type="button"
          aria-label={t('checklist.deleteTask')}
          onClick={onDelete}
          className="mt-0.5 shrink-0 rounded p-0.5 text-muted-foreground/50 transition-colors hover:text-destructive focus-visible:text-destructive sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </li>
  )
}

/**
 * The module's action checklist: curated steps distilled from the course
 * content, plus the student's own tasks, Google-Tasks style. Check states
 * are per-student and persist server-side.
 */
export function ModuleChecklist({
  slug,
  module,
}: {
  slug: string | undefined
  module: ModuleChecklistData
}) {
  const { t } = useTranslation()
  const toggleItem = useToggleChecklistItem(slug, module.id)
  const addTask = useAddTask(slug, module.id)
  const updateTask = useUpdateTask(slug, module.id)
  const deleteTask = useDeleteTask(slug, module.id)
  const [draft, setDraft] = useState('')

  // Optimistic updates roll the UI back on failure. Rolling back silently
  // reads as "the app lost my work", so say what happened instead.
  const failed =
    toggleItem.isError || addTask.isError || updateTask.isError || deleteTask.isError

  const total = module.items.length + module.custom_tasks.length
  const done =
    module.items.filter((i) => i.checked).length +
    module.custom_tasks.filter((task) => task.is_done).length
  const percent = total > 0 ? Math.round((done / total) * 100) : 0

  const submitDraft = () => {
    const body = draft.trim()
    if (!body || addTask.isPending) return
    addTask.mutate(body, { onSuccess: () => setDraft('') })
  }

  return (
    <section className="rounded-lg border">
      <div className="space-y-2 border-b px-4 py-3">
        <div className="flex items-center justify-between gap-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <ClipboardList className="h-4 w-4 shrink-0 text-primary" />
            {t('checklist.title')}
          </h2>
          <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
            {done}/{total}
          </span>
        </div>
        <Progress value={percent} className="h-1.5" aria-label={t('checklist.title')} />
        <p className="text-xs text-muted-foreground">{t('checklist.subtitle')}</p>
        {failed && (
          <p role="status" className="text-xs font-medium text-destructive">
            {t('checklist.saveFailed')}
          </p>
        )}
      </div>

      <ul className="divide-y divide-border/60 px-4 py-1">
        {module.items.map((item) => (
          <CheckRow
            key={item.id}
            checked={item.checked}
            label={item.text}
            onToggle={() => toggleItem.mutate({ itemId: item.id, checked: !item.checked })}
          />
        ))}
      </ul>

      {/* The student's own tasks */}
      <div className="border-t px-4 pb-4 pt-3">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          {t('checklist.myTasks')}
        </h3>

        {module.custom_tasks.length > 0 && (
          <ul className="mt-1">
            {module.custom_tasks.map((task) => (
              <CheckRow
                key={task.id}
                checked={task.is_done}
                label={task.body}
                onToggle={() => updateTask.mutate({ taskId: task.id, is_done: !task.is_done })}
                onDelete={() => deleteTask.mutate(task.id)}
              />
            ))}
          </ul>
        )}

        <form
          className="mt-2 flex items-center gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            submitDraft()
          }}
        >
          <Input
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            placeholder={t('checklist.addPlaceholder')}
            maxLength={500}
            className="h-9"
          />
          <Button type="submit" size="sm" variant="outline" disabled={!draft.trim() || addTask.isPending}>
            <Plus className="h-4 w-4" />
            {t('checklist.add')}
          </Button>
        </form>
      </div>
    </section>
  )
}
