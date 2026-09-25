import { AlertTriangle, ArrowRight } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'
import { PageHeading } from '../components/page-heading'
import { ErrorState, LoadingState } from '../components/states'
import { StatusBadge } from '../components/status'
import { StatusRail } from '../components/status-rail'
import { Badge } from '../components/ui/badge'
import { Select } from '../components/ui/field'
import { Panel, PanelHeader, PanelTitle } from '../components/ui/panel'
import { demoDashboard } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function DashboardPage() {
  const { session } = useAuth()
  const [year, setYear] = useState(2024)
  const preview = session?.token.startsWith('preview-') ? demoDashboard(year) : undefined
  const { data, error, loading } = useApiData(() => api.dashboard(session!.token, year), [session?.token, year], preview)

  if (loading) return <LoadingState label="Memuat ringkasan Profil Kesehatan…" />
  if (error || !data) return <ErrorState message={error || 'Data dashboard tidak tersedia.'} />

  const counts = data.reportingTables.reduce((result, table) => {
    result[table.status] += 1
    return result
  }, { verified: 0, completed: 0, not_started: 0 })
  const tableCount = data.reportingTables.length
  const completion = tableCount ? Math.round(((counts.verified + counts.completed) / tableCount) * 100) : 0

  return (
    <>
      <PageHeading
        eyebrow="Ringkasan Provinsi"
        title={`Profil Kesehatan ${data.year}`}
        description={`Pantau kesiapan ${tableCount} Tabel Pelaporan dan tindak lanjuti Kabupaten/Kota yang masih membutuhkan penyelesaian.`}
        actions={<label className="flex items-center gap-2 text-xs font-semibold">Tahun Pelaporan<Select className="w-28" value={year} onChange={(event) => setYear(Number(event.target.value))}>{data.availableYears.map((item) => <option key={item}>{item}</option>)}</Select></label>}
      />

      <div className="mb-4 flex items-start gap-3 rounded-[4px] border border-pending/60 bg-pending-soft px-4 py-3 text-sm text-[#5f4b19]">
        <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <div><p className="font-semibold">Cakupan pelaporan belum lengkap</p><p className="mt-0.5 text-xs leading-5">{counts.not_started} Tabel Pelaporan belum memiliki data lengkap. Angka provinsi final hanya menggunakan data Terverifikasi.</p></div>
      </div>

      <Panel className="mb-4 overflow-hidden">
        <PanelHeader>
          <div><PanelTitle>Rel Status Tabel Pelaporan</PanelTitle><p className="mt-1 text-xs text-ink-muted">Setiap sel membuka rincian tabel. Nomor mengikuti urutan Katalog Indikator.</p></div>
          <div className="flex flex-wrap gap-2"><Badge tone="verified">{counts.verified} Terverifikasi</Badge><Badge tone="pending">{counts.completed} Sudah Diinput</Badge><Badge>{counts.not_started} Belum Diinput</Badge></div>
        </PanelHeader>
        <div className="p-4"><StatusRail tables={data.reportingTables} year={data.year} /></div>
        <div className="grid border-t border-line-soft sm:grid-cols-[1fr_auto]">
          <div className="p-4">
            <div className="mb-2 flex items-baseline justify-between gap-3"><span className="text-xs font-semibold">Tabel siap atau sedang diperiksa</span><span className="font-mono text-sm font-bold tabular">{completion}%</span></div>
            <div className="flex h-2 overflow-hidden rounded-[2px] border border-line bg-paper-inset"><div className="bg-archive" style={{ width: `${tableCount ? counts.verified / tableCount * 100 : 0}%` }} /><div className="bg-pending" style={{ width: `${tableCount ? counts.completed / tableCount * 100 : 0}%` }} /></div>
          </div>
          <Link to="/reporting-tables" className="flex items-center justify-center gap-2 border-t border-line-soft px-5 py-3 text-xs font-bold text-archive hover:bg-archive-soft sm:border-l sm:border-t-0">Buka daftar tabel<ArrowRight className="size-3.5" /></Link>
        </div>
      </Panel>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.75fr)]">
        <Panel className="overflow-hidden">
          <PanelHeader><div><PanelTitle>Perbandingan Kabupaten/Kota</PanelTitle><p className="mt-1 text-xs text-ink-muted">Urutan berdasarkan jumlah Tabel Pelaporan Terverifikasi.</p></div></PanelHeader>
          <div className="overflow-x-auto scrollbar-thin">
            <table className="w-full min-w-[620px] border-collapse text-left text-xs">
              <thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="px-4 py-2.5 font-bold">Kabupaten/Kota</th><th className="px-3 py-2.5 font-bold">Distribusi Tabel Pelaporan</th><th className="px-3 py-2.5 text-right font-bold">Terverifikasi</th></tr></thead>
              <tbody>{data.regions.toSorted((a, b) => b.verified - a.verified).map((region) => <tr key={region.id} className="border-t border-line-soft"><td className="px-4 py-3 font-semibold">{region.name}</td><td className="px-3 py-3"><div className="flex h-2 min-w-56 overflow-hidden rounded-[2px] border border-line bg-paper-inset"><div className="bg-archive" style={{ width: `${region.total ? region.verified / region.total * 100 : 0}%` }} /><div className="bg-pending" style={{ width: `${region.total ? region.completed / region.total * 100 : 0}%` }} /></div><p className="mt-1 font-mono text-[10px] text-ink-faint">{region.completed} input • {region.notStarted} belum</p></td><td className="px-3 py-3 text-right font-mono font-bold tabular">{region.verified}/{region.total}</td></tr>)}</tbody>
            </table>
          </div>
        </Panel>

        <Panel className="overflow-hidden">
          <PanelHeader><div><PanelTitle>Pembaruan Terakhir</PanelTitle><p className="mt-1 text-xs text-ink-muted">Aktivitas terbaru pada Tabel Pelaporan.</p></div></PanelHeader>
            <div>{data.recentTables.length ? data.recentTables.map((table) => <Link key={`${table.id}-${table.updatedAt}`} to={`/reporting-tables/${table.id}?year=${data.year}${table.regionId ? `&regionId=${table.regionId}` : ''}`} className="grid grid-cols-[32px_minmax(0,1fr)] gap-3 border-b border-line-soft px-4 py-3 last:border-b-0 hover:bg-paper-inset"><span className="grid size-8 place-items-center rounded-[2px] border border-line bg-paper font-mono text-[10px] font-bold">{String(table.number).padStart(2, '0')}</span><div className="min-w-0"><p className="truncate text-xs font-semibold">{table.name}</p><div className="mt-1 flex flex-wrap items-center gap-2"><StatusBadge status={table.status} /><span className="text-[10px] text-ink-faint">{table.updatedBy}</span></div></div></Link>) : <p className="p-6 text-xs text-ink-muted">Belum ada aktivitas pada Tahun Pelaporan ini.</p>}</div>
        </Panel>
      </div>
    </>
  )
}
