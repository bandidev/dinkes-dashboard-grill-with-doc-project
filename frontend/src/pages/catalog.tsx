import { Search } from 'lucide-react'
import { useDeferredValue, useState } from 'react'
import { api } from '../api'
import { useAuth } from '../auth'
import { PageHeading } from '../components/page-heading'
import { EmptyState, ErrorState, LoadingState } from '../components/states'
import { Input, Select } from '../components/ui/field'
import { Panel } from '../components/ui/panel'
import { Badge } from '../components/ui/badge'
import { demoCatalog } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function CatalogPage() {
  const { session } = useAuth()
  const [year, setYear] = useState(2024)
  const [query, setQuery] = useState('')
  const deferredQuery = useDeferredValue(query.toLowerCase())
  const preview = session?.token.startsWith('preview-') ? demoCatalog : undefined
  const { data, error, loading } = useApiData(() => api.catalog(session!.token, year), [session?.token, year], preview)

  if (loading) return <LoadingState label="Memuat Katalog Indikator…" />
  if (error) return <ErrorState message={error} />
  if (!data?.length) return <><PageHeading eyebrow="Definisi Pelaporan" title="Katalog Indikator" description="Definisi Nilai Indikator yang wajib dilaporkan pada Tahun Pelaporan." />{session?.user.role === 'administrator' ? <CatalogImportSummary token={session.token} year={year} /> : null}<EmptyState title="Katalog belum siap" description="Struktur Tabel Pelaporan sudah diimpor, tetapi Indikator masih perlu dipetakan dan divalidasi dari header workbook." /></>

  const filtered = data.filter((item) => `${item.code} ${item.name} ${item.tableName}`.toLowerCase().includes(deferredQuery))
  return (
    <><PageHeading eyebrow="Definisi Pelaporan" title="Katalog Indikator" description="Definisi Nilai Indikator yang wajib dilaporkan pada Tahun Pelaporan." actions={<label className="flex items-center gap-2 text-xs font-semibold">Tahun<Select className="w-24" value={year} onChange={(event) => setYear(Number(event.target.value))}><option>2024</option><option>2023</option></Select></label>} />{session?.user.role === 'administrator' ? <CatalogImportSummary token={session.token} year={year} /> : null}<Panel className="overflow-hidden"><div className="border-b border-line p-4"><label className="relative block max-w-md"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" /><Input className="pl-9" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari kode, nama, atau Tabel Pelaporan…" aria-label="Cari Katalog Indikator" /></label></div><div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-xs"><thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="w-28 px-4 py-2.5">Kode</th><th className="px-3 py-2.5">Indikator</th><th className="w-44 px-3 py-2.5">Satuan</th><th className="px-4 py-2.5">Tabel Pelaporan</th></tr></thead><tbody>{filtered.map((item) => <tr key={item.id} className="border-t border-line-soft"><td className="px-4 py-3 font-mono font-bold text-archive">{item.code}</td><td className="px-3 py-3 font-semibold">{item.name}</td><td className="px-3 py-3 text-ink-muted">{item.unit}</td><td className="px-4 py-3 text-ink-muted">{item.tableName}</td></tr>)}</tbody></table></div>{filtered.length === 0 ? <p className="p-8 text-center text-sm text-ink-muted">Tidak ada Indikator yang sesuai dengan pencarian.</p> : null}</Panel></>
  )
}

function CatalogImportSummary({ token, year }: { token: string; year: number }) {
  const { data, error, loading } = useApiData(() => api.catalogImport(token, year), [token, year])
  if (loading) return <Panel className="mb-4 p-4 text-xs text-ink-muted">Memuat laporan impor katalog…</Panel>
  if (error || !data) return null

  return <Panel className="mb-4 overflow-hidden"><div className="grid gap-px bg-line-soft sm:grid-cols-4"><div className="bg-paper-raised p-4"><p className="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted">Lembar workbook</p><p className="mt-1 font-mono text-xl font-bold">{data.report.workbook_sheets}</p></div><div className="bg-paper-raised p-4"><p className="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted">Tabel Pelaporan</p><p className="mt-1 font-mono text-xl font-bold">{data.report.reporting_tables}</p></div><div className="bg-paper-raised p-4"><p className="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted">Siap input</p><p className="mt-1 font-mono text-xl font-bold text-archive">{data.report.mapping_ready}</p></div><div className="bg-paper-raised p-4"><p className="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted">Perlu pemetaan</p><p className="mt-1 font-mono text-xl font-bold text-correction">{data.report.mapping_pending}</p></div></div><div className="flex flex-wrap items-center gap-2 border-t border-line p-4 text-xs text-ink-muted"><Badge tone="pending">Impor katalog</Badge><span>{data.filename}</span><span>•</span><span>{new Date(data.imported_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })}</span></div></Panel>
}
