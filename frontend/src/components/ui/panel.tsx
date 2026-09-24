import type { HTMLAttributes } from 'react'
import { cn } from '../../lib/cn'

export function Panel({ className, ...props }: HTMLAttributes<HTMLElement>) {
  return <section className={cn('rounded-[4px] border border-line bg-paper-raised', className)} {...props} />
}

export function PanelHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex flex-wrap items-start justify-between gap-3 border-b border-line-soft px-4 py-3', className)} {...props} />
}

export function PanelTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return <h2 className={cn('text-sm font-bold tracking-[-0.01em] text-ink', className)} {...props} />
}
