import { AlertTriangle, ArrowLeft, CheckCircle2, ListChecks, LockKeyhole, RotateCcw, Save, Table2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useBlocker, useParams, useSearchParams } from 'react-router-dom'
import { api, type IndicatorRow, type ReportingTableDetail, type TableOneWorksheet } from '../api'
import { useAuth } from '../auth'
import { ErrorState, LoadingState } from '../components/states'
import { StatusBadge } from '../components/status'
import { TableOneWorksheetView } from '../components/table-one-worksheet'
import { Button } from '../components/ui/button'
import { Input } from '../components/ui/field'
import { Panel } from '../components/ui/panel'
import { demoTableDetail } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function ReportingDetailPage() {
  const { id = '1' } = useParams()
  const [searchParams] = useSearchParams()
  const { session } = useAuth()
  const year = Number(searchParams.get('year')) || 2024
  const preview = session?.token.startsWith('preview-') ? demoTableDetail(id, year) : undefined
  const { data, error, loading } = useApiData(() => api.reportingTable(session!.token, id, year), [session?.token, id, year], preview)
  const [detail, setDetail] = useState<ReportingTableDetail | null>(preview || null)
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const [changingStatus, setChangingStatus] = useState(false)
  const [worksheetBusy, setWorksheetBusy] = useState(false)
  const [formDirty, setFormDirty] = useState(false)
  const [selectedHeaders, setSelectedHeaders] = useState<string[]>([])
  const [inputMode, setInputMode] = useState<'form' | 'worksheet'>('form')
  const hasUnsavedChanges = formDirty || worksheetBusy
  const blocker = useBlocker(hasUnsavedChanges)

  useEffect(() => { if (data) { setDetail(data); setFormDirty(false) } }, [data])
  useEffect(() => {
    if (!hasUnsavedChanges) return
    const beforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', beforeUnload)
    return () => window.removeEventListener('beforeunload', beforeUnload)
  }, [hasUnsavedChanges])
  useEffect(() => {
    if (blocker.state !== 'blocked') return
    const method = formDirty ? 'Input Ringkas' : 'Tabel Lengkap'
    if (window.confirm(`Perubahan ${method} belum tersimpan. Tinggalkan halaman dan abaikan perubahan?`)) {
      setFormDirty(false)
      blocker.proceed()
    } else blocker.reset()
  }, [blocker, formDirty])

  if (loading && !detail) return <LoadingState label="Memuat rincian Tabel Pelaporan…" />
  if (error || !detail) return <ErrorState message={error || 'Rincian tabel tidak tersedia.'} />

  const isAdmin = session?.user.role === 'administrator'
  const editable = detail.mappingStatus === 'ready' && !isAdmin && detail.status === 'not_started'
  const requiredRows = detail.rows.filter((row) => row.kind === 'base' && row.required)
  const missing = requiredRows.filter((row) => row.notApplicable ? !row.notApplicableReason.trim() : !row.value).length
  const hasWorksheet = detail.group === 'T01' && !session!.token.startsWith('preview-')
  const spreadsheetView = hasWorksheet && inputMode === 'worksheet'

  function updateRow(next: IndicatorRow) {
    setDetail((current) => current ? { ...current, rows: current.rows.map((row) => row.id === next.id ? next : row) } : current)
    setFormDirty(true)
    setNotice('')
  }

  async function save() {
    if (!detail) return
    setSaving(true)
    try {
      if (session!.token.startsWith('preview-')) {
        setFormDirty(false)
        setNotice('Draft tersimpan pada pratinjau sesi ini.')
      }
      else {
        setDetail(await api.saveTable(session!.token, detail))
        setFormDirty(false)
        setNotice('Draft berhasil disimpan.')
      }
    } catch (reason) {
      setNotice(reason instanceof Error ? reason.message : 'Gagal menyimpan draft.')
    } finally {
      setSaving(false)
    }
  }

  async function changeStatus(action: 'complete' | 'reopen' | 'verify' | 'unverify') {
    if (!detail) return
    const reason = action === 'reopen' || action === 'unverify'
      ? window.prompt(action === 'reopen' ? 'Alasan Perbaiki Input' : 'Alasan pembatalan Verifikasi')
      : undefined
    if ((action === 'reopen' || action === 'unverify') && !reason?.trim()) return

    if (session!.token.startsWith('preview-')) {
      const status = action === 'verify' ? 'verified' : action === 'complete' || action === 'unverify' ? 'completed' : 'not_started'
      setDetail({ ...detail, status })
      setNotice('Status Tabel Pelaporan berhasil diperbarui.')
      return
    }

    setChangingStatus(true)
    try {
      setDetail(await api.setTableStatus(session!.token, detail, action, reason || undefined))
      setNotice('Status Tabel Pelaporan berhasil diperbarui.')
    } catch (cause) {
      setNotice(cause instanceof Error ? cause.message : 'Perubahan status gagal.')
    } finally {
      setChangingStatus(false)
    }
  }

  async function mapIndicators() {
    if (!detail || !selectedHeaders.length) return
    setSaving(true)
    try {
      setDetail(await api.mapIndicators(session!.token, detail, selectedHeaders))
      setNotice('Indikator berhasil dipetakan sebagai Nilai Dasar numerik. Tinjau kembali tipe dan satuannya melalui Katalog Indikator.')
    } catch (cause) {
      setNotice(cause instanceof Error ? cause.message : 'Pemetaan Indikator gagal.')
    } finally {
      setSaving(false)
    }
  }

  async function showForm() {
    if (!detail || inputMode === 'form' || worksheetBusy) return
    setSaving(true)
    try {
      setDetail(await api.reportingTable(session!.token, detail.reportingTableId, detail.year))
      setInputMode('form')
    } catch (cause) {
      setNotice(cause instanceof Error ? cause.message : 'Form Indikator gagal dimuat.')
    } finally {
      setSaving(false)
    }
  }

  function showWorksheet() {
    if (saving || worksheetBusy || inputMode === 'worksheet') return
    if (formDirty && !window.confirm('Perubahan Input Ringkas belum disimpan. Beralih ke Tabel Lengkap dan abaikan perubahan?')) return
    setFormDirty(false)
    setInputMode('worksheet')
  }

  function syncWorksheet(worksheet: TableOneWorksheet) {
    const row = worksheet.rows.find((item) => item.editable)
    if (!row) return
    setDetail((current) => current ? {
      ...current,
      submissionId: row.submissionId,
      version: row.version,
      status: row.status,
      rows: current.rows.map((item) => ({ ...item, value: row.values[item.id] ?? item.value })),
    } : current)
  }

  return (
    <>
      <Link to="/reporting-tables" className="mb-5 inline-flex min-h-10 items-center gap-2 text-xs font-bold text-archive hover:underline"><ArrowLeft className="size-3.5" />Kembali ke Tabel Pelaporan</Link>
      <div className="mb-5 flex flex-col justify-between gap-4 border-b border-line-soft pb-5 xl:flex-row xl:items-end">
        <div className="min-w-0">
          <div className="mb-2 flex flex-wrap items-center gap-2"><span className="font-mono text-xs font-bold tracking-[0.06em] text-archive">TABEL {String(detail.number).padStart(2, '0')}</span><StatusBadge status={detail.status} />{detail.status === 'verified' ? <span className="inline-flex items-center gap-1 text-[11px] text-ink-muted"><LockKeyhole className="size-3" />Terkunci</span> : null}</div>
          <h1 className="max-w-5xl text-balance text-lg font-bold leading-snug tracking-[-0.02em] sm:text-xl">{detail.name}</h1>
          <p className="mt-2 font-mono text-[11px] text-ink-faint">{detail.region} <span aria-hidden="true">•</span> Tahun Pelaporan {detail.year}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {editable && !spreadsheetView ? <Button variant="outline" onClick={save} disabled={saving || !formDirty}><Save data-icon="inline-start" />{saving ? 'Menyimpan…' : 'Simpan draft'}</Button> : null}
          {editable ? <Button onClick={() => changeStatus('complete')} disabled={missing > 0 || !detail.submissionId || formDirty || worksheetBusy || changingStatus}><CheckCircle2 data-icon="inline-start" />{changingStatus ? 'Memproses…' : 'Selesai Input'}</Button> : null}
          {!isAdmin && detail.status === 'completed' ? <Button variant="outline" disabled={changingStatus} onClick={() => changeStatus('reopen')}><RotateCcw data-icon="inline-start" />Perbaiki Input</Button> : null}
          {isAdmin && detail.status === 'completed' ? <Button disabled={changingStatus} onClick={() => changeStatus('verify')}><CheckCircle2 data-icon="inline-start" />Verifikasi</Button> : null}
          {isAdmin && detail.status === 'verified' ? <Button variant="outline" disabled={changingStatus} onClick={() => changeStatus('unverify')}><RotateCcw data-icon="inline-start" />Batalkan Verifikasi</Button> : null}
        </div>
      </div>

      {hasWorksheet ? <div className="mb-4 flex flex-col justify-between gap-3 border-y border-line-soft py-3 sm:flex-row sm:items-center"><div><p className="text-xs font-bold">Metode input</p><p className="mt-0.5 text-[11px] text-ink-muted">Pilih tampilan kerja yang paling sesuai.</p></div><div className="grid w-full grid-cols-2 gap-2 sm:w-auto" role="group" aria-label="Metode input Tabel Pelaporan 1"><button type="button" disabled={saving || worksheetBusy} className={`flex min-h-14 items-center gap-2 rounded-[3px] border px-3 py-2 text-left transition-colors focus-visible:outline focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-50 sm:min-w-44 ${inputMode === 'form' ? 'border-archive bg-archive-soft text-archive' : 'border-line bg-paper-raised text-ink-muted hover:border-archive hover:text-archive'}`} aria-pressed={inputMode === 'form'} onClick={showForm}><ListChecks className="size-4 shrink-0" aria-hidden="true" /><span><span className="block text-xs font-bold">Input Ringkas</span><span className="block text-[10px] font-medium">Wilayah Anda · Disarankan</span></span></button><button type="button" disabled={saving || worksheetBusy} className={`flex min-h-14 items-center gap-2 rounded-[3px] border px-3 py-2 text-left transition-colors focus-visible:outline focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-50 sm:min-w-44 ${inputMode === 'worksheet' ? 'border-archive bg-archive-soft text-archive' : 'border-line bg-paper-raised text-ink-muted hover:border-archive hover:text-archive'}`} aria-pressed={inputMode === 'worksheet'} onClick={showWorksheet}><Table2 className="size-4 shrink-0" aria-hidden="true" /><span><span className="block text-xs font-bold">Tabel Lengkap</span><span className="block text-[10px] font-medium">Semua Kabupaten/Kota</span></span></button></div></div> : null}
      {detail.mappingStatus === 'pending' ? <div className="mb-4 flex items-start gap-3 rounded-[4px] border border-pending/60 bg-pending-soft px-4 py-3 text-xs text-[#5f4b19]"><AlertTriangle className="mt-0.5 size-4 shrink-0" /><p><strong>Indikator tabel ini masih perlu dipetakan.</strong> Administrator harus memvalidasi header workbook sebelum Operator dapat menginput data.</p></div> : null}
      {isAdmin && detail.mappingStatus === 'pending' ? <Panel className="mb-4 overflow-hidden"><div className="border-b border-line p-4"><p className="text-sm font-bold">Kandidat Header Workbook</p><p className="mt-1 text-xs text-ink-muted">Pilih hanya kolom yang benar-benar merupakan Nilai Dasar. Total, jumlah, rasio, dan persentase turunan jangan dipilih.</p></div><div className="grid gap-px bg-line-soft sm:grid-cols-2 xl:grid-cols-3">{detail.headerCandidates.map((header) => <label key={header} className="flex items-start gap-2 bg-paper-raised p-3 text-xs"><input type="checkbox" checked={selectedHeaders.includes(header)} onChange={(event) => setSelectedHeaders((current) => event.target.checked ? [...current, header] : current.filter((item) => item !== header))} /><span>{header}</span></label>)}</div><div className="border-t border-line p-4"><Button onClick={mapIndicators} disabled={!selectedHeaders.length || saving}>{saving ? 'Memetakan…' : `Tetapkan ${selectedHeaders.length} Indikator`}</Button></div></Panel> : null}
      {editable && (missing > 0 || (!detail.submissionId && !spreadsheetView)) ? <div className="mb-4 flex items-start gap-3 rounded-[4px] border border-pending/60 bg-pending-soft px-4 py-3 text-xs text-[#5f4b19]"><AlertTriangle className="mt-0.5 size-4 shrink-0" /><p>{missing > 0 ? <><strong>{missing} Nilai Indikator wajib belum diisi.</strong> Selesaikan nilai kosong atau tandai Tidak Berlaku dengan alasan.{!detail.submissionId && !spreadsheetView ? ' Setelah lengkap, simpan draft sebelum menyatakan Selesai Input.' : null}</> : <>Simpan draft terlebih dahulu sebelum menyatakan Selesai Input.</>}</p></div> : null}
      {formDirty ? <div className="mb-4 flex items-start gap-3 rounded-[4px] border border-pending/60 bg-pending-soft px-4 py-3 text-xs text-[#5f4b19]" role="status"><AlertTriangle className="mt-0.5 size-4 shrink-0" /><p><strong>Perubahan belum disimpan.</strong> Pilih Simpan draft sebelum berpindah atau menyatakan Selesai Input.</p></div> : null}
      {notice ? <p className="mb-4 rounded-[4px] border border-line bg-paper-raised px-4 py-3 text-xs" role="status">{notice}</p> : null}

      {spreadsheetView ? <TableOneWorksheetView token={session!.token} reportingTableId={detail.reportingTableId} onSynced={syncWorksheet} onBusyChange={setWorksheetBusy} /> : <Panel className="overflow-hidden">
        <div className="grid grid-cols-2 border-b border-line bg-paper-inset text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted sm:grid-cols-4"><div className="border-r border-line-soft px-4 py-2">Wajib <span className="block font-mono text-base text-ink">{requiredRows.length}</span></div><div className="border-r border-line-soft px-4 py-2">Sudah Diisi <span className="block font-mono text-base text-ink">{requiredRows.length - missing}</span></div><div className="border-r border-line-soft px-4 py-2">Belum Lengkap <span className="block font-mono text-base text-correction">{missing}</span></div><div className="px-4 py-2">Kelengkapan <span className="block font-mono text-base text-ink">{requiredRows.length ? Math.round((requiredRows.length - missing) / requiredRows.length * 100) : 0}%</span></div></div>
        {(['base', 'derived'] as const).map((kind) => <div key={kind}><div className="border-b border-line bg-paper-raised px-4 py-3"><h2 className="text-sm font-bold">{kind === 'base' ? 'Nilai Dasar' : 'Nilai Turunan'}</h2><p className="mt-0.5 text-[11px] text-ink-muted">{kind === 'base' ? 'Diisi oleh Operator Kabupaten/Kota.' : 'Dihitung otomatis dari Nilai Dasar.'}</p></div><div className="overflow-x-auto scrollbar-thin"><table className="w-full min-w-[680px] border-collapse text-left text-xs"><thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="px-4 py-2.5 font-bold">Indikator</th><th className="w-28 px-3 py-2.5 font-bold">Satuan</th><th className="w-72 px-4 py-2.5 font-bold">Nilai</th></tr></thead><tbody>{detail.rows.filter((row) => row.kind === kind).map((row) => <IndicatorTableRow key={row.id} row={row} editable={editable} onChange={updateRow} />)}</tbody></table></div></div>)}
      </Panel>}
    </>
  )
}

function IndicatorTableRow({ row, editable, onChange }: { row: IndicatorRow; editable: boolean; onChange: (row: IndicatorRow) => void }) {
  return (
    <tr className="border-t border-line-soft align-top">
      <td className="px-4 py-3"><p className="font-semibold">{row.name}</p><p className="mt-1 text-[10px] text-ink-faint">{row.category} · {row.required ? 'Wajib' : 'Opsional'} · {row.code}</p></td>
      <td className="px-3 py-3 text-ink-muted">{row.unit}</td>
      <td className="px-4 py-2">
        {row.kind === 'derived' ? <div className="flex h-9 items-center rounded-[3px] border border-line bg-paper-inset px-3 font-mono font-bold tabular">{row.value || 'Tidak Dapat Dihitung'}</div> : <div className="flex flex-col gap-2"><Input aria-label={`Nilai ${row.name}`} type={row.dataType === 'date' ? 'date' : 'text'} inputMode={row.dataType === 'numeric' ? 'decimal' : undefined} value={row.value} disabled={!editable || row.notApplicable} onChange={(event) => onChange({ ...row, value: event.target.value })} className="font-mono tabular" /><label className="flex items-center gap-2 text-[10px] font-semibold text-ink-muted"><input type="checkbox" checked={row.notApplicable} disabled={!editable} onChange={(event) => onChange({ ...row, notApplicable: event.target.checked, value: event.target.checked ? '' : row.value })} />Tidak Berlaku</label>{row.notApplicable ? <Input aria-label={`Alasan ${row.name} tidak berlaku`} placeholder="Alasan wajib" value={row.notApplicableReason} disabled={!editable} onChange={(event) => onChange({ ...row, notApplicableReason: event.target.value })} /> : null}</div>}
      </td>
    </tr>
  )
}
