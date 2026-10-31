import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { CircleCheck, CircleX, ClipboardCheck, GraduationCap, Lock, Timer } from 'lucide-react'
import { useLearn } from '@/api/learn'
import { useQuiz, useStartQuiz, useSubmitQuiz, type QuizResult } from '@/api/quizzes'
import { usePageTitle } from '@/hooks/usePageTitle'
import { PlayerShell } from '@/components/player/PlayerShell'
import { PlayerSkeleton } from '@/components/player/PlayerSkeleton'
import { Button } from '@/components/ui/button'
import { cn, latinDir } from '@/lib/utils'

/** "59:07" */
function clock(seconds: number) {
  const s = Math.max(0, seconds)
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

/**
 * A module quiz or the final exam. The questions come without their answers;
 * the server grades the sheet. After a module quiz the right answers are
 * shown; after the exam, the score and which answers were wrong.
 *
 * The exam is timed from the moment it starts: the deadline comes from the
 * server, survives a reload, and the sheet is sent on its own at 0:00.
 */
export function QuizPage() {
  const { slug, quizId } = useParams<{ slug: string; quizId: string }>()
  const id = Number(quizId)
  const { t } = useTranslation()
  const learn = useLearn(slug)
  const quiz = useQuiz(id)
  const start = useStartQuiz(id)
  const submit = useSubmitQuiz(slug, id)
  const [answers, setAnswers] = useState<Record<number, string>>({})
  const [result, setResult] = useState<QuizResult | null>(null)
  const [now, setNow] = useState(() => Date.now())
  const autoSubmitted = useRef(false)

  usePageTitle(quiz.data?.title)

  const isFinal = quiz.data?.kind === 'final'
  const attempt = quiz.data?.open_attempt ?? null
  const questions = quiz.data?.questions ?? null
  const deadline = attempt?.expires_at ? new Date(attempt.expires_at).getTime() : null
  const remaining = deadline === null ? null : Math.round((deadline - now) / 1000)

  // A fresh page on a quiz with no attempt: a module quiz opens one at once
  // (its questions are already on screen); the exam waits for "Start".
  useEffect(() => {
    setAnswers({})
    setResult(null)
    autoSubmitted.current = false
  }, [id])

  useEffect(() => {
    if (deadline === null || result) return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [deadline, result])

  const send = async () => {
    let attemptId = attempt?.id
    if (!attemptId) {
      const started = await start.mutateAsync()
      attemptId = started.open_attempt?.id
    }
    if (!attemptId) return
    const graded = await submit.mutateAsync({ attemptId, answers })
    setResult(graded)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  // Time is up: send what has been answered.
  useEffect(() => {
    if (remaining !== null && remaining <= 0 && attempt && !result && !autoSubmitted.current) {
      autoSubmitted.current = true
      void send().catch(() => undefined)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [remaining])

  const answeredCount = Object.keys(answers).length
  const resultByNumber = useMemo(
    () => new Map((result?.results ?? []).map((row) => [row.numero, row])),
    [result],
  )

  if (learn.isLoading || quiz.isLoading) return <PlayerSkeleton />

  if (!learn.data || quiz.isError || !quiz.data) {
    const status = isAxiosError(quiz.error) ? quiz.error.response?.status : undefined
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <Lock className="h-10 w-10 text-muted-foreground" aria-hidden />
        <h1 className="text-lg font-semibold">{status === 403 ? t('quiz.locked') : t('common.error')}</h1>
        <Button asChild variant="outline">
          <Link to={slug ? `/learn/${slug}` : '/dashboard'}>{t('common.back')}</Link>
        </Button>
      </div>
    )
  }

  const data = quiz.data
  const Icon = isFinal ? GraduationCap : ClipboardCheck
  const submitError = submit.error && isAxiosError(submit.error) ? submit.error.response?.data?.errors?.answers?.[0] : null

  return (
    <PlayerShell data={learn.data} location={{ quizId: data.id }}>
      <header className="space-y-2">
        <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          <Icon className="h-4 w-4 text-primary" />
          {isFinal ? t('exam.navTitle') : t('quiz.navTitle')}
        </p>
        <h1 className="text-xl font-bold leading-snug sm:text-2xl">{data.title}</h1>
        <p className="text-sm text-muted-foreground">
          {isFinal
            ? t('exam.rules', { total: data.questions_count, pass: data.pass_mark, minutes: data.duration_minutes })
            : t('quiz.rules', { total: data.questions_count, pass: data.pass_mark })}
        </p>
      </header>

      {/* The result of the attempt just sent */}
      {result && (
        <section
          className={cn(
            'rounded-lg border p-4',
            result.passed ? 'border-success/40 bg-success/10' : 'border-warning/40 bg-warning/10',
          )}
          role="status"
        >
          <p className="flex items-center gap-2 text-base font-semibold">
            {result.passed ? <CircleCheck className="h-5 w-5 text-success" /> : <CircleX className="h-5 w-5 text-warning-foreground" />}
            {result.passed ? t('quiz.passed') : t('quiz.failed')}
          </p>
          <p className="mt-1 text-sm">
            {t('quiz.score', { score: result.score, total: result.total, pass: data.pass_mark })}
          </p>
          <Button
            variant="outline"
            size="sm"
            className="mt-3"
            onClick={() => {
              setResult(null)
              setAnswers({})
              autoSubmitted.current = false
              window.scrollTo({ top: 0 })
            }}
          >
            {t('quiz.retry')}
          </Button>
        </section>
      )}

      {/* The exam before it starts: the clock only runs once asked */}
      {isFinal && !questions && !result && (
        <section className="rounded-lg border p-6 text-center">
          <Timer className="mx-auto h-8 w-8 text-primary" />
          <p className="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
            {t('exam.startHint', { minutes: data.duration_minutes })}
          </p>
          <Button className="mt-4" onClick={() => start.mutate()} disabled={start.isPending}>
            {t('exam.start')}
          </Button>
        </section>
      )}

      {/* The running exam's clock */}
      {isFinal && remaining !== null && !result && questions && (
        <div
          className={cn(
            'sticky top-16 z-10 flex items-center justify-between rounded-lg border bg-background/95 px-4 py-2 text-sm shadow-sm backdrop-blur',
            remaining < 300 && 'border-warning text-warning-foreground',
          )}
        >
          <span className="flex items-center gap-2 font-medium">
            <Timer className="h-4 w-4" />
            {t('exam.timeLeft')} <span className="tabular-nums">{clock(remaining)}</span>
          </span>
          <span className="text-muted-foreground">
            {t('quiz.answered', { count: answeredCount, total: data.questions_count })}
          </span>
        </div>
      )}

      {questions && (
        <form
          className="space-y-5"
          onSubmit={(event) => {
            event.preventDefault()
            void send()
          }}
        >
          {questions.map((question) => {
            const graded = resultByNumber.get(question.numero)
            return (
              <fieldset key={question.numero} className="rounded-lg border p-4" disabled={!!result}>
                <legend className="px-1 text-sm font-semibold leading-snug">
                  {question.numero}. {question.pregunta}
                </legend>
                <div className="mt-2 space-y-1.5">
                  {question.opciones.map((option) => {
                    const chosen = answers[question.numero] === option.letra
                    const isRight = graded?.correct_answer === option.letra
                    const isWrongChoice = graded && chosen && !graded.is_correct
                    return (
                      <label
                        key={option.letra}
                        className={cn(
                          'flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm transition-colors',
                          chosen ? 'border-primary bg-primary/5' : 'border-transparent hover:bg-secondary',
                          isRight && 'border-success bg-success/10',
                          isWrongChoice && 'border-warning bg-warning/10',
                        )}
                      >
                        <input
                          type="radio"
                          name={`q-${question.numero}`}
                          value={option.letra}
                          checked={chosen}
                          onChange={() => setAnswers((prev) => ({ ...prev, [question.numero]: option.letra }))}
                          className="mt-1 accent-primary"
                        />
                        <span className="font-semibold">{option.letra}</span>
                        <span className="flex-1">
                          <bdi dir={latinDir(option.texto)}>{option.texto}</bdi>
                        </span>
                      </label>
                    )
                  })}
                </div>
                {graded && (
                  <p className={cn('mt-2 text-xs font-medium', graded.is_correct ? 'text-success' : 'text-warning-foreground')}>
                    {graded.is_correct
                      ? t('quiz.correct')
                      : graded.correct_answer
                        ? t('quiz.incorrectWithAnswer', { answer: graded.correct_answer })
                        : t('quiz.incorrect')}
                  </p>
                )}
              </fieldset>
            )
          })}

          {!result && (
            <div className="flex flex-wrap items-center gap-3 border-t pt-4">
              <Button type="submit" disabled={submit.isPending || start.isPending || answeredCount === 0}>
                {isFinal ? t('exam.submit') : t('quiz.submit')}
              </Button>
              <span className="text-sm text-muted-foreground">
                {t('quiz.answered', { count: answeredCount, total: data.questions_count })}
              </span>
              {submitError && <p className="w-full text-sm text-destructive">{submitError}</p>}
            </div>
          )}
        </form>
      )}

      {/* Earlier attempts */}
      {data.attempts && data.attempts.length > 0 && (
        <section className="rounded-lg border">
          <h2 className="border-b px-4 py-3 text-sm font-semibold">{t('quiz.history')}</h2>
          <ul className="divide-y text-sm">
            {data.attempts.map((past) => (
              <li key={past.id} className="flex items-center justify-between px-4 py-2">
                <span className="text-muted-foreground">
                  {past.submitted_at ? new Date(past.submitted_at).toLocaleString() : ''}
                </span>
                <span className={cn('font-medium tabular-nums', past.passed ? 'text-success' : 'text-muted-foreground')}>
                  {past.score}/{past.total}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </PlayerShell>
  )
}
