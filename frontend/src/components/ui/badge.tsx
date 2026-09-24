import type { HTMLAttributes } from 'react'
import { cn } from '../../lib/cn'

type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
  tone?: 'neutral' | 'verified' | 'pending' | 'correction'
}

const tones = {
  neutral: 'border-line bg-paper-inset text-ink-muted',
  verified: 'border-archive/35 bg-archive-soft text-archive',
  pending: 'border-pending/50 bg-pending-soft text-[#6d5311]',
  correction: 'border-correction/35 bg-correction-soft text-correction',
}

export function Badge({ className, tone = 'neutral', ...props }: BadgeProps) {
  return (
    <span
      className={cn('inline-flex items-center rounded-[2px] border px-2 py-0.5 text-[11px] font-semibold leading-5', tones[tone], className)}
      {...props}
    />
  )
}
