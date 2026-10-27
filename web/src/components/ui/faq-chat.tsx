import { useState, type ReactNode } from 'react'
import * as Accordion from '@radix-ui/react-accordion'
import { cn } from '@/lib/utils'

export interface FaqChatItem {
  id: string
  question: string
  /** Text, or text followed by links (e.g. the live contact options). */
  answer: ReactNode
}

/**
 * FAQ rendered as a conversation: the question is a bubble you tap, the answer
 * arrives as a reply bubble underneath.
 *
 * Design ported from the Aurea project. Rebuilt on the Radix accordion this
 * app already ships (with its accordion-down/up keyframes) instead of
 * framer-motion, so it adds no new dependency. Direction-aware: the reply is
 * indented from the inline start, so it mirrors correctly in Arabic RTL.
 */
export function FaqChat({
  items,
  className,
  questionClassName,
  answerClassName,
}: {
  items: FaqChatItem[]
  className?: string
  questionClassName?: string
  answerClassName?: string
}) {
  const [openItem, setOpenItem] = useState<string>('')

  return (
    <Accordion.Root
      type="single"
      collapsible
      value={openItem}
      onValueChange={setOpenItem}
      className={cn('space-y-2', className)}
    >
      {items.map((item) => {
        const isOpen = openItem === item.id

        return (
          <Accordion.Item value={item.id} key={item.id} className="border-none">
            <Accordion.Header>
              <Accordion.Trigger className="group flex w-full items-center justify-start gap-x-3 text-start">
                {/* The question bubble */}
                <div
                  className={cn(
                    // Press feedback: the bubble is the thing being tapped.
                    'relative rounded-2xl px-4 py-2.5 transition-[color,background-color,transform] duration-150 ease-out-strong group-active:scale-[0.98]',
                    isOpen
                      ? 'bg-primary/15 text-primary'
                      : 'bg-secondary text-foreground group-hover:bg-primary/10',
                    questionClassName,
                  )}
                >
                  <span className="font-medium">{item.question}</span>
                </div>

                <span
                  className={cn(
                    'shrink-0 transition-colors',
                    isOpen ? 'text-primary' : 'text-muted-foreground',
                  )}
                  aria-hidden
                >
                  {/* One icon that morphs: the vertical bar collapses, plus
                      becomes minus, on the same curve as the reply opening. */}
                  <svg
                    viewBox="0 0 24 24"
                    className="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeLinecap="round"
                  >
                    <path d="M5 12h14" />
                    <path
                      d="M12 5v14"
                      className="origin-center transition-transform duration-200 ease-out-strong group-data-[state=open]:scale-y-0"
                      style={{ transformBox: 'fill-box' }}
                    />
                  </svg>
                </span>
              </Accordion.Trigger>
            </Accordion.Header>

            {/* The reply bubble */}
            <Accordion.Content className="overflow-hidden data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down">
              <div className="ms-7 mt-1.5 md:ms-16">
                <div
                  className={cn(
                    'relative max-w-xl rounded-2xl bg-primary px-4 py-3 text-primary-foreground',
                    answerClassName,
                  )}
                >
                  {item.answer}
                </div>
              </div>
            </Accordion.Content>
          </Accordion.Item>
        )
      })}
    </Accordion.Root>
  )
}
