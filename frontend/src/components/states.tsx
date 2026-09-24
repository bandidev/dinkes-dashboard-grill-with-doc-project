import { AlertCircle, Inbox } from 'lucide-react'
import { Button } from './ui/button'
import { Panel } from './ui/panel'

export function LoadingState({ label = 'Memuat data…' }: { label?: string }) {
  return (
    <Panel className="flex min-h-48 items-center justify-center p-6" aria-live="polite">
      <div className="flex items-center gap-3 text-sm text-ink-muted">
        <span className="size-3 animate-pulse rounded-full bg-archive" />
        {label}
      </div>
    </Panel>
  )
}

export function ErrorState({ message, retry }: { message: string; retry?: () => void }) {
  return (
    <Panel className="flex min-h-48 flex-col items-center justify-center gap-3 p-6 text-center" role="alert">
      <AlertCircle className="size-6 text-correction" aria-hidden="true" />
      <div>
        <p className="font-semibold">Data belum dapat ditampilkan</p>
        <p className="mt-1 max-w-lg text-sm text-ink-muted">{message}</p>
      </div>
      {retry ? <Button variant="outline" size="sm" onClick={retry}>Coba lagi</Button> : null}
    </Panel>
  )
}

export function EmptyState({ title, description }: { title: string; description: string }) {
  return (
    <Panel className="flex min-h-48 flex-col items-center justify-center gap-3 p-6 text-center">
      <Inbox className="size-6 text-ink-faint" aria-hidden="true" />
      <div>
        <p className="font-semibold">{title}</p>
        <p className="mt-1 max-w-lg text-sm text-ink-muted">{description}</p>
      </div>
    </Panel>
  )
}
