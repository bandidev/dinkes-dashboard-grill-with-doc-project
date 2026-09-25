import { Search } from 'lucide-react'
import { useDeferredValue, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, type ReportingStatus } from '../api'
import { useAuth } from '../auth'
import { PageHeading } from '../components/page-heading'
import { ErrorState, LoadingState } from '../components/states'
import { StatusBadge } from '../components/status'
import { StatusRail } from '../components/status-rail'
import { Input, Select } from '../components/ui/field'
import { Badge } from '../components/ui/badge'
import { Panel, PanelHeader, PanelTitle } from '../components/ui/panel'
import { demoTables } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function ReportingTablesPage() {
  const { session } = useAuth()
  const [searchParams] = useSearchParams()
  const selectedRegionId = searchParams.get('regionId')
  const [year, setYear] = useState(2024)
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState<'all' | ReportingStatus>('all')
  const deferredQuery = useDeferredValue(query.trim().toLowerCase())
  const preview = session?.token.startsWith('preview-') ? demoTables() : undefined
  const isAdmin = session?.user.role === 'administrator'
  const { data, error, loading } = useApiData(() => api.reportingTables(session!.token, year), [session?.token, year], preview)

  if (loading) return <LoadingState label="Memuat Tabel Pelaporan…" />
  if (error || !data) return <ErrorState message={error || 'Daftar tabel tidak tersedia.'} />

  const filtered = data.filter((table) => {
    const statusMatches = status === 'all' || (isAdmin && table.regionCounts
      ? status === 'verified' ? table.regionCounts.verified > 0
        : status === 'completed' ? table.regionCounts.completed > 0
          : table.regionCounts.notStarted > 0
      : table.status === status)
    return statusMatches && (!deferredQuery || `${table.number} ${table.name} ${table.group}`.toLowerCase().includes(deferredQuery))
  })

  return (
    <>
      <PageHeading eyebrow="Tahun Pelaporan" title="Tabel Pelaporan" description={isAdmin ? 'Pantau distribusi status seluruh Kabupaten/Kota dan buka rincian per wilayah.' : 'Cari, pantau, dan buka seluruh kelompok Indikator yang dilaporkan sebagai satu kesatuan.'} actions={<label className="flex items-center gap-2 text-xs font-semibold">Tahun<Select className="w-24" value={year} onChange={(event) => setYear(Number(event.target.value))}><option>2024</option><option>2023</option></Select></label>} />
      <Panel className="mb-4 overflow-hidden"><PanelHeader><div><PanelTitle>Peta Keseluruhan</PanelTitle><p className="mt-1 text-xs text-ink-muted">{data.length} Tabel Pelaporan dalam satu pandangan.</p></div></PanelHeader><div className="p-4"><StatusRail tables={data} compact year={year} /></div></Panel>
      <Panel className="overflow-hidden">
        <div className="grid gap-3 border-b border-line p-4 md:grid-cols-[minmax(260px,1fr)_220px_auto] md:items-end">
          <label className="relative flex flex-col gap-1.5 text-xs font-semibold" htmlFor="table-search">Cari tabel<div className="relative"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" /><Input id="table-search" className="pl-9" placeholder="Nomor, nama, atau kelompok…" value={query} onChange={(event) => setQuery(event.target.value)} /></div></label>
          <label className="flex flex-col gap-1.5 text-xs font-semibold">{isAdmin ? 'Status di wilayah' : 'Status'}<Select value={status} onChange={(event) => setStatus(event.target.value as 'all' | ReportingStatus)}><option value="all">Semua status</option><option value="not_started">Belum Diinput</option><option value="completed">Sudah Diinput</option><option value="verified">Terverifikasi</option></Select></label>
          <p className="pb-2 text-right font-mono text-xs text-ink-muted">{filtered.length} dari {data.length} tabel</p>
        </div>
        <div className="overflow-x-auto scrollbar-thin">
          <table className="w-full min-w-[780px] border-collapse text-left text-xs">
            <thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="w-20 px-4 py-2.5 font-bold">No.</th><th className="px-3 py-2.5 font-bold">Tabel Pelaporan</th><th className="px-3 py-2.5 font-bold">Kelompok</th><th className="px-3 py-2.5 font-bold">{isAdmin ? 'Wilayah selesai' : 'Kelengkapan'}</th><th className="px-3 py-2.5 font-bold">{isAdmin ? 'Status per wilayah' : 'Status'}</th><th className="px-4 py-2.5 text-right font-bold">Akses</th></tr></thead>
            <tbody>{filtered.map((table) => { const regionId = table.regionId || selectedRegionId; return <tr key={table.id} className="border-t border-line-soft hover:bg-paper-inset"><td className="px-4 py-3 font-mono font-bold">{String(table.number).padStart(2, '0')}</td><td className="max-w-md px-3 py-3 font-semibold"><span>{table.name}</span>{table.mappingStatus === 'pending' ? <Badge className="ml-2">Perlu Pemetaan</Badge> : null}</td><td className="px-3 py-3 text-ink-muted">{table.group}</td><td className="px-3 py-3"><div className="flex items-center gap-2"><div className="h-1.5 w-20 overflow-hidden rounded-[2px] bg-paper-inset"><div className="h-full bg-archive" style={{ width: `${table.completion}%` }} /></div><span className="font-mono tabular">{table.regionCounts ? `${table.regionCounts.verified + table.regionCounts.completed}/${table.regionCounts.total} (${table.completion}%)` : `${table.completion}%`}</span></div></td><td className="px-3 py-3">{isAdmin && table.regionCounts ? <span className="text-[11px] leading-5"><span className="font-semibold text-archive">{table.regionCounts.verified} terverifikasi</span><span className="text-ink-faint"> · </span><span className="font-semibold text-[#6d5311]">{table.regionCounts.completed} diinput</span><span className="text-ink-faint"> · </span><span className="font-semibold text-ink-muted">{table.regionCounts.notStarted} belum selesai</span><span className="text-ink-faint"> / {table.regionCounts.total}</span></span> : <StatusBadge status={table.status} />}</td><td className="px-4 py-3 text-right"><Link className="font-bold text-archive underline-offset-4 hover:underline" to={`/reporting-tables/${table.id}?year=${year}${regionId ? `&regionId=${regionId}` : ''}`}>{isAdmin && ['T02', 'T03'].includes(table.group) && !regionId ? 'Buka provinsi' : isAdmin && !regionId ? 'Pilih wilayah' : table.mappingStatus === 'ready' ? 'Buka tabel' : 'Tinjau struktur'}</Link></td></tr> })}</tbody>
          </table>
        </div>
        {filtered.length === 0 ? <p className="p-8 text-center text-sm text-ink-muted">Tidak ada Tabel Pelaporan yang sesuai dengan pencarian.</p> : null}
      </Panel>
    </>
  )
}
