import type { ReactNode } from 'react'

export function PageHeading({ eyebrow, title, description, actions }: { eyebrow: string; title: string; description: string; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-col justify-between gap-4 md:flex-row md:items-end">
      <div>
        <p className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-archive">{eyebrow}</p>
        <h1 className="mt-1 text-xl font-bold tracking-[-0.025em] sm:text-2xl">{title}</h1>
        <p className="mt-1 max-w-3xl text-sm text-ink-muted">{description}</p>
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  )
}
