import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react'
import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '../../lib/cn'

type FeedbackTone = 'info' | 'warning' | 'success' | 'error'

const styles = {
  info: 'border-archive/35 bg-archive-soft text-archive',
  warning: 'border-pending/70 bg-pending-soft text-[#5f4b19]',
  success: 'border-archive/45 bg-archive-soft text-archive',
  error: 'border-correction/45 bg-correction-soft text-correction',
}

const icons = { info: Info, warning: AlertTriangle, success: CheckCircle2, error: AlertCircle }

export function FeedbackBanner({ tone = 'info', title, children, action, className, ...props }: HTMLAttributes<HTMLDivElement> & { tone?: FeedbackTone; title: string; action?: ReactNode }) {
  const Icon = icons[tone]
  return (
    <div className={cn('feedback-banner flex flex-wrap items-start gap-3 rounded-[4px] border px-4 py-3 shadow-[0_1px_0_rgba(32,36,31,0.04)] sm:flex-nowrap', styles[tone], className)} {...props}>
      <span className="grid size-8 shrink-0 place-items-center rounded-[3px] bg-paper-raised/70"><Icon className="size-4" aria-hidden="true" /></span>
      <div className="min-w-0 flex-1"><p className="text-xs font-bold">{title}</p><div className="mt-0.5 text-[11px] leading-5 opacity-85">{children}</div></div>
      {action ? <div className="ml-11 w-full self-center sm:ml-0 sm:w-auto sm:shrink-0">{action}</div> : null}
    </div>
  )
}
