import { ArrowLeft, CheckCircle2, ListChecks, RotateCcw, Save, Table2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useBlocker, useParams, useSearchParams } from 'react-router-dom'
import { api, type IndicatorRow, type ReportingTableDetail, type TableOneWorksheet } from '../api'
import { useAuth } from '../auth'
import { ErrorState, LoadingState } from '../components/states'
import { StatusBadge } from '../components/status'
import { TableOneWorksheetView } from '../components/table-one-worksheet'
import { TableTwoAgeGrid } from '../components/table-two-age-grid'
import { TableThreeEducationGrid } from '../components/table-three-education-grid'
import { Button } from '../components/ui/button'
import { FeedbackBanner } from '../components/ui/feedback-banner'
import { Input, Select } from '../components/ui/field'
import { Panel } from '../components/ui/panel'
import { ReasonDialog } from '../components/ui/reason-dialog'
import { demoTableDetail } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function ReportingDetailPage() {
  const { id = '1' } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const { session } = useAuth()
  const year = Number(searchParams.get('year')) || 2024
  const isAdmin = session?.user.role === 'administrator'
  const previewSession = session?.token.startsWith('preview-') ?? false
  const regionId = Number(searchParams.get('regionId')) || (isAdmin ? undefined : Number(session?.user.regionId) || undefined)
  const preview = session?.token.startsWith('preview-') ? demoTableDetail(id, year) : undefined
  const { data, error, loading } = useApiData(() => isAdmin && !regionId && !previewSession ? api.provinceTable(session!.token, id, year) : api.reportingTable(session!.token, id, year, regionId), [session?.token, id, year, regionId, isAdmin, previewSession], preview)
  const { data: regions, error: regionsError } = useApiData(() => isAdmin && !previewSession ? api.regions(session!.token) : Promise.resolve([]), [session?.token, isAdmin, previewSession])
  const [detail, setDetail] = useState<ReportingTableDetail | null>(preview || null)
  const [notice, setNotice] = useState<{ tone: 'success' | 'error' | 'info'; message: string } | null>(null)
  const [saving, setSaving] = useState(false)
  const [changingStatus, setChangingStatus] = useState(false)
  const [worksheetBusy, setWorksheetBusy] = useState(false)
  const [formDirty, setFormDirty] = useState(false)
  const [dirtyRows, setDirtyRows] = useState<string[]>([])
  const [selectedHeaders, setSelectedHeaders] = useState<string[]>([])
  const [inputMode, setInputMode] = useState<'form' | 'worksheet'>('form')
  const [statusDialog, setStatusDialog] = useState<'reopen' | 'unverify' | null>(null)
  const hasUnsavedChanges = formDirty || worksheetBusy
  const blocker = useBlocker(hasUnsavedChanges)

  useEffect(() => { if (data) { setDetail(data); setFormDirty(false); setDirtyRows([]) } }, [data])
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
      setDirtyRows([])
      blocker.proceed()
    } else blocker.reset()
  }, [blocker, formDirty])

  if (isAdmin && !previewSession && !regionId && !loading && !error && data === null) return <>
    <Link to="/reporting-tables" className="mb-4 inline-flex min-h-10 items-center gap-2 text-xs font-bold text-archive hover:underline"><ArrowLeft className="size-3.5" />Tabel Pelaporan</Link>
    <Panel className="max-w-xl overflow-hidden">
      <div className="border-b border-line-soft p-5"><h1 className="text-base font-bold">Pilih Kabupaten/Kota</h1><p className="mt-1 text-sm text-ink-muted">Rincian Nilai Indikator dan status ditampilkan untuk satu wilayah.</p></div>
      <div className="p-5"><label htmlFor="detail-region" className="text-xs font-semibold">Kabupaten/Kota</label>{regionsError ? <p className="mt-2 text-sm text-correction">{regionsError}</p> : <Select id="detail-region" className="mt-1.5" value={regionId || ''} disabled={!regions?.length} onChange={(event) => setSearchParams((current) => { const next = new URLSearchParams(current); next.set('regionId', event.target.value); next.set('year', String(year)); return next })}><option value="" disabled>{regions?.length ? 'Pilih wilayah…' : 'Memuat wilayah…'}</option>{regions?.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}</Select>}</div>
    </Panel>
  </>
  const staleDetail = Boolean(detail && (detail.reportingTableId !== id || detail.year !== year || (isAdmin && !previewSession && (regionId ? detail.regionId !== String(regionId) || detail.scope === 'province' : detail.scope !== 'province'))))
  if (error) return <ErrorState message={error} />
  if (loading || !detail || staleDetail) return <LoadingState label="Memuat rincian Tabel Pelaporan…" />

  const editable = detail.mappingStatus === 'ready' && !isAdmin && detail.status === 'not_started'
  const requiredRows = detail.rows.filter((row) => row.kind === 'base' && row.required)
  const numericTable = ['T01', 'T02', 'T03'].includes(detail.group)
  const missing = requiredRows.filter((row) => numericTable ? !row.value.trim() : row.notApplicable ? !row.notApplicableReason.trim() : !row.value.trim()).length
  const hasWorksheet = detail.group === 'T01' && !session!.token.startsWith('preview-')
  const spreadsheetView = hasWorksheet && inputMode === 'worksheet'

  function updateRow(next: IndicatorRow) {
    setDetail((current) => current ? { ...current, rows: current.rows.map((row) => row.id === next.id ? next : row) } : current)
    setFormDirty(true)
    setDirtyRows((current) => current.includes(next.id) ? current : [...current, next.id])
    setNotice(null)
  }

  async function save() {
    if (!detail) return
    setSaving(true)
    try {
      if (session!.token.startsWith('preview-')) {
        setFormDirty(false)
        setDirtyRows([])
        setNotice({ tone: 'success', message: 'Draft tersimpan pada pratinjau sesi ini.' })
      }
      else {
        setDetail(await api.saveTable(session!.token, detail))
        setFormDirty(false)
        setDirtyRows([])
        setNotice({ tone: 'success', message: missing ? `Draft tersimpan. Lengkapi ${missing} nilai wajib untuk menyelesaikan input.` : 'Draft tersimpan. Siap dinyatakan Selesai Input.' })
      }
    } catch (reason) {
      setNotice({ tone: 'error', message: reason instanceof Error ? reason.message : 'Gagal menyimpan draft.' })
    } finally {
      setSaving(false)
    }
  }

  async function changeStatus(action: 'complete' | 'reopen' | 'verify' | 'unverify') {
    if (!detail) return
    if (action === 'reopen' || action === 'unverify') { setStatusDialog(action); return }
    await submitStatusChange(action)
  }

  async function submitStatusChange(action: 'complete' | 'reopen' | 'verify' | 'unverify', reason?: string) {
    if (!detail) return

    if (session!.token.startsWith('preview-')) {
      const status = action === 'verify' ? 'verified' : action === 'complete' || action === 'unverify' ? 'completed' : 'not_started'
      setDetail({ ...detail, status })
      setStatusDialog(null)
      setNotice({ tone: 'success', message: 'Status Tabel Pelaporan berhasil diperbarui.' })
      return
    }

    setChangingStatus(true)
    try {
      setDetail(await api.setTableStatus(session!.token, detail, action, reason || undefined))
      setStatusDialog(null)
      setNotice({ tone: 'success', message: 'Status Tabel Pelaporan berhasil diperbarui.' })
    } catch (cause) {
      setNotice({ tone: 'error', message: cause instanceof Error ? cause.message : 'Perubahan status gagal.' })
    } finally {
      setChangingStatus(false)
    }
  }

  async function mapIndicators() {
    if (!detail || !selectedHeaders.length) return
    setSaving(true)
    try {
      setDetail(await api.mapIndicators(session!.token, detail, selectedHeaders))
      setNotice({ tone: 'success', message: 'Indikator berhasil dipetakan sebagai Nilai Dasar numerik. Tinjau kembali tipe dan satuannya melalui Katalog Indikator.' })
    } catch (cause) {
      setNotice({ tone: 'error', message: cause instanceof Error ? cause.message : 'Pemetaan Indikator gagal.' })
    } finally {
      setSaving(false)
    }
  }

  async function showForm() {
    if (!detail || inputMode === 'form' || worksheetBusy) return
    setSaving(true)
    try {
      setDetail(await api.reportingTable(session!.token, detail.reportingTableId, detail.year, Number(detail.regionId)))
      setInputMode('form')
      setNotice(null)
    } catch (cause) {
      setNotice({ tone: 'error', message: cause instanceof Error ? cause.message : 'Form Indikator gagal dimuat.' })
    } finally {
      setSaving(false)
    }
  }

  function showWorksheet() {
    if (saving || worksheetBusy || inputMode === 'worksheet') return
    if (formDirty && !window.confirm('Perubahan Input Ringkas belum disimpan. Beralih ke Tabel Lengkap dan abaikan perubahan?')) return
    setFormDirty(false)
    setDirtyRows([])
    setInputMode('worksheet')
    setNotice(null)
  }

  function syncWorksheet(worksheet: TableOneWorksheet) {
    const row = worksheet.rows.find((item) => item.editable)
    if (!row) return
    setDetail((current) => current ? {
      ...current,
      submissionId: row.submissionId,
      version: row.version,
      status: row.status,
      rows: current.rows.map((item) => row.values[item.id] !== undefined ? { ...item, value: row.values[item.id], notApplicable: false, notApplicableReason: '' } : item),
    } : current)
  }

  return (
    <>
      <Link to={`/reporting-tables${isAdmin ? `?year=${year}${detail.scope === 'province' ? '' : `&regionId=${detail.regionId}`}` : ''}`} className="mb-4 inline-flex min-h-10 items-center gap-2 text-xs font-bold text-archive hover:underline"><ArrowLeft className="size-3.5" />Tabel Pelaporan</Link>
      <div className="mb-4 flex flex-col justify-between gap-4 border-b border-line-soft pb-4 xl:flex-row xl:items-end">
        <div className="min-w-0">
          <div className="mb-2 flex flex-wrap items-center gap-2"><span className="font-mono text-xs font-bold tracking-[0.06em] text-archive">TABEL {String(detail.number).padStart(2, '0')}</span>{detail.scope !== 'province' ? <StatusBadge status={detail.status} /> : null}</div>
          <h1 className="max-w-5xl text-balance text-lg font-bold leading-snug tracking-[-0.02em] sm:text-xl">{detail.name}</h1>
          <p className="mt-2 text-xs text-ink-muted">{detail.region} <span aria-hidden="true">·</span> Tahun Pelaporan {detail.year}</p>
        </div>
        {isAdmin && regions?.length ? <label className="flex min-w-52 flex-col gap-1 text-[11px] font-semibold text-ink-muted">{['T02', 'T03'].includes(detail.group) ? 'Wilayah' : 'Kabupaten/Kota'}<Select value={detail.regionId} disabled={changingStatus || statusDialog !== null} onChange={(event) => setSearchParams((current) => { const next = new URLSearchParams(current); if (event.target.value) next.set('regionId', event.target.value); else next.delete('regionId'); next.set('year', String(year)); return next })}>{['T02', 'T03'].includes(detail.group) ? <option value="">Provinsi</option> : null}{regions.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}</Select></label> : null}
        <div className="flex flex-wrap gap-2">
          {editable && !spreadsheetView ? <Button variant="outline" onClick={save} disabled={saving || !formDirty}><Save data-icon="inline-start" />{saving ? 'Menyimpan…' : 'Simpan draft'}</Button> : null}
          {editable ? <Button onClick={() => changeStatus('complete')} disabled={missing > 0 || !detail.submissionId || formDirty || worksheetBusy || changingStatus}><CheckCircle2 data-icon="inline-start" />{changingStatus ? 'Memproses…' : 'Selesai Input'}</Button> : null}
          {!isAdmin && detail.status === 'completed' ? <Button variant="outline" disabled={changingStatus} onClick={() => changeStatus('reopen')}><RotateCcw data-icon="inline-start" />Perbaiki Input</Button> : null}
          {isAdmin && detail.scope !== 'province' && detail.status === 'completed' ? <Button disabled={changingStatus} onClick={() => changeStatus('verify')}><CheckCircle2 data-icon="inline-start" />Verifikasi</Button> : null}
          {isAdmin && detail.scope !== 'province' && detail.status === 'verified' ? <Button variant="outline" disabled={changingStatus} onClick={() => changeStatus('unverify')}><RotateCcw data-icon="inline-start" />Batalkan Verifikasi</Button> : null}
        </div>
      </div>

      {hasWorksheet ? <div className="mb-4 flex flex-col justify-between gap-3 border-y border-line-soft py-3 sm:flex-row sm:items-center"><p className="text-xs font-bold">Metode input</p><div className="grid w-full grid-cols-2 gap-2 sm:w-auto" role="group" aria-label="Metode input Tabel Pelaporan 1"><button type="button" disabled={saving || worksheetBusy} className={`flex min-h-14 items-center gap-2 rounded-[3px] border px-3 py-2 text-left transition-colors focus-visible:outline focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-50 sm:min-w-44 ${inputMode === 'form' ? 'border-archive bg-archive-soft text-archive' : 'border-line bg-paper-raised text-ink-muted hover:border-archive hover:text-archive'}`} aria-pressed={inputMode === 'form'} onClick={showForm}><ListChecks className="size-4 shrink-0" aria-hidden="true" /><span><span className="block text-xs font-bold">Input Ringkas</span><span className="block text-[11px] font-medium">Wilayah Anda · Simpan manual</span></span></button><button type="button" disabled={saving || worksheetBusy} className={`flex min-h-14 items-center gap-2 rounded-[3px] border px-3 py-2 text-left transition-colors focus-visible:outline focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-50 sm:min-w-44 ${inputMode === 'worksheet' ? 'border-archive bg-archive-soft text-archive' : 'border-line bg-paper-raised text-ink-muted hover:border-archive hover:text-archive'}`} aria-pressed={inputMode === 'worksheet'} onClick={showWorksheet}><Table2 className="size-4 shrink-0" aria-hidden="true" /><span><span className="block text-xs font-bold">Tabel Lengkap</span><span className="block text-[11px] font-medium">Semua wilayah · Otomatis per sel</span></span></button></div></div> : null}
      {detail.mappingStatus === 'pending' ? <FeedbackBanner className="mb-4" tone="warning" title="Indikator belum siap diinput">{detail.group === 'T02' ? 'Pemetaan kelompok umur Tabel 2 perlu dijalankan oleh pengelola sistem.' : detail.group === 'T03' ? 'Pemetaan jumlah dan persentase Tabel 3 perlu dijalankan oleh pengelola sistem.' : 'Administrator harus memvalidasi header workbook sebelum Operator dapat menginput data.'}</FeedbackBanner> : null}
      {isAdmin && detail.mappingStatus === 'pending' && !['T02', 'T03'].includes(detail.group) ? <Panel className="mb-4 overflow-hidden"><div className="border-b border-line p-4"><p className="text-sm font-bold">Kandidat Header Workbook</p><p className="mt-1 text-xs text-ink-muted">Pilih hanya kolom yang benar-benar merupakan Nilai Dasar. Total, jumlah, rasio, dan persentase turunan jangan dipilih.</p></div><div className="grid gap-px bg-line-soft sm:grid-cols-2 xl:grid-cols-3">{detail.headerCandidates.map((header) => <label key={header} className="flex items-start gap-2 bg-paper-raised p-3 text-xs"><input type="checkbox" checked={selectedHeaders.includes(header)} onChange={(event) => setSelectedHeaders((current) => event.target.checked ? [...current, header] : current.filter((item) => item !== header))} /><span>{header}</span></label>)}</div><div className="border-t border-line p-4"><Button onClick={mapIndicators} disabled={!selectedHeaders.length || saving}>{saving ? 'Memetakan…' : `Tetapkan ${selectedHeaders.length} Indikator`}</Button></div></Panel> : null}
      {notice?.tone === 'error' ? <FeedbackBanner className="mb-4" tone="error" title="Tindakan belum berhasil" role="alert">{notice.message}</FeedbackBanner>
        : formDirty ? <FeedbackBanner className="mb-4" tone="warning" title="Perubahan belum disimpan" role="status" action={<Button size="sm" className="w-full sm:w-auto" onClick={save} disabled={saving}><Save data-icon="inline-start" />{saving ? 'Menyimpan…' : 'Simpan draft'}</Button>}>Simpan draft sebelum berpindah atau menyatakan Selesai Input.</FeedbackBanner>
          : notice ? <FeedbackBanner className="mb-4" tone={notice.tone} title={notice.tone === 'success' ? 'Berhasil' : 'Informasi'} role="status">{notice.message}</FeedbackBanner>
            : editable && missing > 0 ? <FeedbackBanner className="mb-4" tone="warning" title={`${missing} nilai wajib belum lengkap`}>{numericTable ? 'Isi semua nilai yang kosong; ketik 0 jika hasilnya memang nol.' : 'Isi nilai yang kosong atau tandai Tidak Berlaku beserta alasannya.'}</FeedbackBanner>
              : editable && !detail.submissionId && !spreadsheetView ? <FeedbackBanner className="mb-4" tone="info" title="Simpan draft">Simpan sebelum menyatakan Selesai Input.</FeedbackBanner> : null}

      {spreadsheetView ? <TableOneWorksheetView token={session!.token} reportingTableId={detail.reportingTableId} onSynced={syncWorksheet} onBusyChange={setWorksheetBusy} /> : <Panel className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-paper-inset px-4 py-3 text-xs"><span className="font-semibold text-ink-muted">{detail.scope === 'province' ? 'Data lengkap seluruh wilayah' : 'Nilai wajib'} <strong className="ml-1 font-mono text-base text-ink">{requiredRows.length - missing}/{requiredRows.length}</strong></span><span className={missing ? 'font-semibold text-correction' : 'font-semibold text-archive'}>{missing ? detail.scope === 'province' ? `${missing} rekap belum lengkap` : `${missing} belum lengkap` : 'Lengkap'}</span></div>
        {detail.group === 'T02' && detail.mappingStatus === 'ready' && !previewSession ? <TableTwoAgeGrid rows={detail.rows} editable={editable} dirtyRows={dirtyRows} onChange={updateRow} totalLabel={detail.scope === 'province' ? 'Provinsi' : 'Kabupaten/Kota'} province={detail.scope === 'province'} /> : detail.group === 'T03' && detail.mappingStatus === 'ready' && !previewSession ? <TableThreeEducationGrid rows={detail.rows} editable={editable} dirtyRows={dirtyRows} province={detail.scope === 'province'} regionName={detail.region} onChange={updateRow} /> : <div className="overflow-x-auto scrollbar-thin"><table className="w-full min-w-[680px] border-collapse text-left text-xs">
          <thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="px-4 py-2.5 font-bold">Indikator</th><th className="w-28 px-3 py-2.5 font-bold">Satuan</th><th className="w-72 px-4 py-2.5 font-bold">Nilai</th></tr></thead>
          {(['base', 'derived'] as const).map((kind) => <tbody key={kind}>
            <tr className="border-t border-line"><th colSpan={3} scope="rowgroup" className="bg-paper-raised px-4 py-2.5 text-left"><span className="font-bold">{kind === 'base' ? 'Data yang perlu diisi' : 'Hasil perhitungan'}</span>{kind === 'derived' ? <span className="ml-2 rounded-sm bg-paper-inset px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-ink-muted">Otomatis</span> : null}</th></tr>
            {detail.rows.filter((row) => row.kind === kind).map((row) => <IndicatorTableRow key={row.id} row={row} editable={editable} dirty={dirtyRows.includes(row.id)} onChange={updateRow} noNotApplicable={numericTable} />)}
          </tbody>)}
        </table></div>}
      </Panel>}
      <ReasonDialog open={statusDialog !== null} title={statusDialog === 'unverify' ? 'Batalkan Verifikasi' : 'Perbaiki Input'} description={statusDialog === 'unverify' ? 'Tabel akan kembali ke status Sudah Diinput dan dapat ditinjau ulang. Alasan dicatat pada Riwayat Revisi.' : 'Tabel akan dibuka kembali agar Operator Kabupaten/Kota dapat memperbaiki Nilai Indikator.'} confirmLabel={statusDialog === 'unverify' ? 'Batalkan Verifikasi' : 'Buka untuk diperbaiki'} busy={changingStatus} onClose={() => setStatusDialog(null)} onConfirm={(reason) => { if (statusDialog) void submitStatusChange(statusDialog, reason) }} />
    </>
  )
}

function IndicatorTableRow({ row, editable, dirty, onChange, noNotApplicable = false }: { row: IndicatorRow; editable: boolean; dirty: boolean; onChange: (row: IndicatorRow) => void; noNotApplicable?: boolean }) {
  return (
    <tr className="border-t border-line-soft align-top">
      <td className="px-4 py-3"><p className="font-semibold">{row.name}</p></td>
      <td className="px-3 py-3 text-ink-muted">{row.unit}</td>
      <td className="px-4 py-2">
        {row.kind === 'derived' ? <span className="flex min-h-9 items-center px-3 font-mono font-semibold tabular text-ink">{row.value || 'Tidak Dapat Dihitung'}</span> : <div className="flex flex-col gap-2"><Input aria-label={`Nilai ${row.name}`} type={row.dataType === 'date' ? 'date' : 'text'} inputMode={row.dataType === 'numeric' ? 'decimal' : undefined} value={row.value} disabled={!editable || (!noNotApplicable && row.notApplicable)} onChange={(event) => onChange({ ...row, value: event.target.value, ...(noNotApplicable ? { notApplicable: false, notApplicableReason: '' } : {}) })} className={`font-mono tabular ${dirty ? 'border-pending bg-pending-soft/35 shadow-[inset_3px_0_0_var(--color-pending)] focus:border-pending focus:outline-pending/30' : ''}`} />{!noNotApplicable && <><label className="flex items-center gap-2 text-[10px] font-semibold text-ink-muted"><input type="checkbox" checked={row.notApplicable} disabled={!editable} onChange={(event) => onChange({ ...row, notApplicable: event.target.checked, value: event.target.checked ? '' : row.value })} />Tidak Berlaku</label>{row.notApplicable ? <Input aria-label={`Alasan ${row.name} tidak berlaku`} placeholder="Alasan wajib" value={row.notApplicableReason} disabled={!editable} onChange={(event) => onChange({ ...row, notApplicableReason: event.target.value })} className={dirty ? 'border-pending bg-pending-soft/35' : ''} /> : null}</>}{noNotApplicable && row.notApplicable && !editable ? <span className="text-[10px] text-ink-muted">Tidak Berlaku (data lama)</span> : null}</div>}
      </td>
    </tr>
  )
}
