import { Link, useLocation } from 'react-router-dom'
import type { ReportingTable } from '../api'
import { cn } from '../lib/cn'

const statusColor = {
  verified: 'border-archive bg-archive text-paper-raised hover:bg-[#274b38]',
  completed: 'border-pending bg-pending text-ink hover:bg-[#c39728]',
  not_started: 'border-line bg-paper-inset text-ink-muted hover:border-ink-faint',
}

export function StatusRail({ tables, compact = false, year = 2024 }: { tables: ReportingTable[]; compact?: boolean; year?: number }) {
  const location = useLocation()
  const regionId = new URLSearchParams(location.search).get('regionId')
  return (
    <div className={cn('grid grid-cols-[repeat(15,minmax(28px,1fr))] gap-1 sm:grid-cols-[repeat(18,minmax(28px,1fr))] lg:grid-cols-[repeat(29,minmax(26px,1fr))]', compact && 'lg:grid-cols-[repeat(22,minmax(28px,1fr))]')} aria-label={`Matriks status ${tables.length} Tabel Pelaporan`}>
      {tables.map((table) => (
        <Link
          key={table.id}
          to={`/reporting-tables/${table.id}?year=${year}${table.regionId ? `&regionId=${table.regionId}` : regionId ? `&regionId=${regionId}` : ''}`}
          title={table.regionCounts
            ? `Tabel ${table.number}: ${table.name} — ${table.regionCounts.verified} terverifikasi, ${table.regionCounts.completed} sudah diinput, ${table.regionCounts.notStarted} belum diinput`
            : `Tabel ${table.number}: ${table.name} — ${table.completion}%`}
          aria-label={table.regionCounts
            ? `Tabel ${table.number}, ${table.name}: ${table.regionCounts.verified} terverifikasi, ${table.regionCounts.completed} sudah diinput, ${table.regionCounts.notStarted} belum diinput`
            : `Tabel ${table.number}, ${table.name}, ${table.completion} persen`}
          className={cn('grid aspect-square min-h-7 place-items-center rounded-[2px] border font-mono text-[9px] font-bold transition-colors focus-visible:outline focus-visible:outline-2', statusColor[table.status])}
        >
          {String(table.number).padStart(2, '0')}
        </Link>
      ))}
    </div>
  )
}
