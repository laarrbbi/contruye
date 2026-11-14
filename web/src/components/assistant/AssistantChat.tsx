import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowUp, Plus, Trash2 } from 'lucide-react'
import {
  useAskAssistant,
  useAssistantConversation,
  useAssistantConversations,
  useDeleteAssistantConversation,
  type AssistantMessage,
  type AssistantStatus,
} from '@/api/assistant'
import { apiErrorMessage } from '@/api/orders'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ProtectedArea } from '@/components/protection/ProtectedArea'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { cn, intlLocale } from '@/lib/utils'

const SUGGESTIONS = ['one', 'two', 'three'] as const

/**
 * The conversation with Nicolás: the student's conversations on one side,
 * the thread and the box to ask on the other. Answers come rendered from the
 * server (Markdown without raw HTML, the reader's fingerprint in it) and are
 * shown inside a ProtectedArea, like the lesson they quote; each one lists
 * the pieces of the course it cites, linked to the lesson.
 */
export function AssistantChat({ status }: { status: AssistantStatus }) {
  const { t, i18n } = useTranslation()
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [draft, setDraft] = useState('')
  const [pending, setPending] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const conversations = useAssistantConversations(true)
  const conversation = useAssistantConversation(conversationId)
  const ask = useAskAssistant()
  const remove = useDeleteAssistantConversation()
  const thread = useRef<HTMLDivElement>(null)
  const input = useRef<HTMLTextAreaElement>(null)

  const messages: AssistantMessage[] = conversationId === null ? [] : (conversation.data?.messages ?? [])
  const max = status.max_question_chars ?? 2000

  // The latest message in view, inside the thread (the page itself does not jump).
  useEffect(() => {
    const element = thread.current
    if (element) element.scrollTop = element.scrollHeight
  }, [messages.length, pending])

  const send = (text: string) => {
    const question = text.trim()
    if (!question || ask.isPending || question.length > max) return
    setPending(question)
    setError(null)
    setDraft('')
    ask.mutate(
      { question, conversation_id: conversationId },
      {
        onSuccess: (result) => {
          setConversationId(result.conversation.id)
          setPending(null)
        },
        onError: (failure) => {
          setPending(null)
          setDraft(question)
          setError(apiErrorMessage(failure) ?? t('assistantChat.error'))
        },
      },
    )
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    send(draft)
  }

  // Enter sends, Shift+Enter is a new line (not while an IME is composing).
  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault()
      send(draft)
    }
  }

  const startNew = () => {
    setConversationId(null)
    setError(null)
    input.current?.focus()
  }

  const renews = status.credits ? new Date(status.credits.resets_at).toLocaleDateString(intlLocale(i18n.language), { day: 'numeric', month: 'long' }) : null

  return (
    <div className="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-6">
      <aside className="min-w-0">
        <Button type="button" variant="outline" className="w-full justify-start" onClick={startNew}>
          <Plus className="h-4 w-4" aria-hidden />
          {t('assistantChat.newConversation')}
        </Button>
        <p className="etiquette mt-6 text-plume/60">{t('assistantChat.conversations')}</p>
        {conversations.data && conversations.data.length === 0 ? (
          <p className="mt-2 text-sm text-muted-foreground">{t('assistantChat.noConversations')}</p>
        ) : (
          <ul className="mt-2 flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible lg:pb-0">
            {conversations.data?.map((item) => (
              <li key={item.id} className="shrink-0 lg:shrink">
                <button
                  type="button"
                  onClick={() => {
                    setConversationId(item.id)
                    setError(null)
                  }}
                  aria-current={item.id === conversationId ? 'true' : undefined}
                  className={cn(
                    'min-h-10 w-full max-w-[16rem] truncate rounded-md px-3 py-2 text-start text-sm transition-colors',
                    item.id === conversationId ? 'bg-primary/[0.08] font-medium text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                  )}
                >
                  {item.title}
                </button>
              </li>
            ))}
          </ul>
        )}
      </aside>

      <section className="flex min-w-0 flex-col rounded-xl border bg-card" aria-label={status.name}>
        {conversationId !== null && (
          <div className="flex items-center justify-between gap-3 border-b px-4 py-2">
            <p className="truncate text-sm font-medium">{conversation.data?.title}</p>
            <Button type="button" variant="ghost" size="icon" onClick={() => setConfirmDelete(true)} aria-label={t('assistantChat.delete')} title={t('assistantChat.delete')}>
              <Trash2 className="h-4 w-4" aria-hidden />
            </Button>
          </div>
        )}

        <div ref={thread} className="h-[min(62svh,40rem)] overflow-y-auto overscroll-contain px-4 py-5 sm:px-6" aria-live="polite">
          {messages.length === 0 && pending === null ? (
            <div className="mx-auto flex h-full max-w-lg flex-col justify-center text-center">
              <h2 className="text-xl font-semibold">{t('assistantChat.emptyTitle')}</h2>
              <p className="mt-2 text-sm text-muted-foreground">{t('assistantChat.emptyLead')}</p>
              <div className="mt-6 grid gap-2">
                {SUGGESTIONS.map((key) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => send(t(`assistantChat.suggestions.${key}`))}
                    className="min-h-10 rounded-lg border px-4 py-2.5 text-start text-sm transition-colors hover:bg-muted active:bg-muted"
                  >
                    {t(`assistantChat.suggestions.${key}`)}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <ProtectedArea className="space-y-6">
              {messages.map((message) => (
                <Message key={message.id} message={message} />
              ))}
              {pending !== null && (
                <>
                  <Question text={pending} />
                  <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <span aria-hidden className="h-2 w-2 animate-pulse rounded-full bg-primary motion-reduce:animate-none" />
                    {t('assistantChat.thinking')}
                  </p>
                </>
              )}
            </ProtectedArea>
          )}
        </div>

        <form onSubmit={onSubmit} className="border-t p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] sm:p-4">
          {error && (
            <p role="alert" className="mb-2 text-sm text-destructive">
              {error}
            </p>
          )}
          <div className="flex items-end gap-2">
            <textarea
              ref={input}
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={onKeyDown}
              rows={2}
              maxLength={max}
              enterKeyHint="send"
              placeholder={t('assistantChat.placeholder')}
              aria-label={t('assistantChat.placeholder')}
              className="min-h-[3rem] w-full resize-none rounded-lg border border-input bg-background px-3 py-2.5 text-base leading-snug placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:text-sm"
            />
            <Button type="submit" size="icon" className="h-11 w-11 shrink-0 rounded-full" disabled={!draft.trim() || ask.isPending} aria-label={t('assistantChat.send')}>
              <ArrowUp className="h-5 w-5" aria-hidden />
            </Button>
          </div>
          <div className="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {status.credits && (
              <p>
                <span className="font-medium tabular-nums text-foreground">{t('assistantChat.credits', { count: status.credits.balance })}</span>
                {renews && <> · {t('assistantChat.renews', { date: renews })}</>}
                {' · '}
                {t('assistantChat.cost', { small: status.credits.small, large: status.credits.large })}
              </p>
            )}
            {draft.length > max * 0.8 && (
              <p className="tabular-nums">
                {draft.length}/{max}
              </p>
            )}
          </div>
          <p className="mt-1 text-xs text-muted-foreground">{t('assistantChat.disclaimer')}</p>
        </form>
      </section>

      <AlertDialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('assistantChat.deleteConfirm')}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('assistantChat.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (conversationId === null) return
                remove.mutate(conversationId, { onSuccess: () => setConversationId(null) })
              }}
            >
              {t('assistantChat.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function Question({ text }: { text: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex justify-end">
      <p className="allow-select max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-ee-sm bg-primary/[0.07] px-4 py-2.5 text-[0.9375rem] leading-relaxed">
        <span className="sr-only">{t('assistantChat.you')}: </span>
        {text}
      </p>
    </div>
  )
}

function Message({ message }: { message: AssistantMessage }) {
  const { t, i18n } = useTranslation()
  if (message.role === 'user') return <Question text={message.content} />

  return (
    <article className="max-w-[46rem]">
      {message.html ? (
        <MarkdownContent html={message.html} lang={i18n.language} className="text-[0.9375rem]" />
      ) : (
        <p className="whitespace-pre-wrap text-[0.9375rem] leading-relaxed">{message.content}</p>
      )}
      {message.sources.length > 0 && (
        <div className="mt-3">
          <p className="text-xs font-medium text-muted-foreground">{t('assistantChat.sources')}</p>
          <ol className="mt-1 space-y-1 text-sm">
            {message.sources.map((source) => (
              <li key={source.n} className="flex gap-2">
                <span className="tabular-nums text-muted-foreground">[{source.n}]</span>
                {source.path ? (
                  <Link to={source.path} className="text-primary underline-offset-4 hover:underline">
                    {source.title}
                  </Link>
                ) : (
                  <span>{source.title}</span>
                )}
              </li>
            ))}
          </ol>
        </div>
      )}
      {message.route === 'cache' && <p className="mt-2 text-xs text-muted-foreground">{t('assistantChat.fromCache')}</p>}
    </article>
  )
}
