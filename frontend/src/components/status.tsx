import { Badge } from './ui/badge'
import type { ReportingStatus } from '../api'

export const statusLabel: Record<ReportingStatus, string> = {
  not_started: 'Belum Diinput',
  completed: 'Sudah Diinput',
  verified: 'Terverifikasi',
}

export function StatusBadge({ status }: { status: ReportingStatus }) {
  return (
    <Badge tone={status === 'verified' ? 'verified' : status === 'completed' ? 'pending' : 'neutral'}>
      {statusLabel[status]}
    </Badge>
  )
}
