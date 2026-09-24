import { useEffect, useRef, useState } from 'react'
import { api, type TableOneWorksheet, type WorksheetRow } from '../api'

const columns = [
  ['LUAS_WILAYAH', 'Luas wilayah'],
  ['JUMLAH_DESA', 'Desa'],
  ['JUMLAH_KELURAHAN', 'Kelurahan'],
  ['JUMLAH_DESA_KELURAHAN', 'Desa + Kelurahan'],
  ['JUMLAH_PENDUDUK', 'Jumlah penduduk'],
  ['JUMLAH_RUMAH_TANGGA', 'Jumlah rumah tangga'],
  ['RATA_RATA_JIWA_RUMAH_TANGGA', 'Rata-rata jiwa/rumah tangga'],
  ['KEPADATAN_PENDUDUK', 'Kepadatan penduduk'],
] as const

const baseCodes = new Set(['LUAS_WILAYAH', 'JUMLAH_DESA', 'JUMLAH_KELURAHAN', 'JUMLAH_PENDUDUK', 'JUMLAH_RUMAH_TANGGA'])

type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error'

export function TableOneWorksheetView({ token, reportingTableId, onSynced, onBusyChange }: { token: string; reportingTableId: string; onSynced: (worksheet: TableOneWorksheet) => void; onBusyChange: (busy: boolean) => void }) {
  const [worksheet, setWorksheet] = useState<TableOneWorksheet | null>(null)
  const [saveState, setSaveState] = useState<SaveState>('idle')
  const [error, setError] = useState('')
  const worksheetRef = useRef<TableOneWorksheet | null>(null)
  const queueRef = useRef(new Map<string, string>())
  const processingRef = useRef(false)

  useEffect(() => {
    void loadWorksheet()
    // The worksheet is reloaded only when its table identity changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, reportingTableId])

  async function loadWorksheet() {
    setError('')
    setSaveState('idle')
    try {
      const data = await api.tableOneWorksheet(token, reportingTableId)
      setWorksheet(data)
      worksheetRef.current = data
      queueRef.current.clear()
      processingRef.current = false
      onSynced(data)
      onBusyChange(false)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Tampilan tabel lengkap tidak dapat dimuat.')
      setSaveState('error')
      onBusyChange(true)
    }
  }

  if (!worksheet) return <div className="worksheet-loading" role={saveState === 'error' ? 'alert' : 'status'}>{error || 'Memuat tampilan tabel lengkap…'}</div>

  const editableRow = worksheet.rows.find((row) => row.editable)
  const updateValue = (row: WorksheetRow, code: string, value: string) => {
    const indicatorId = worksheet.indicators[code]
    const next = { ...worksheet, rows: worksheet.rows.map((item) => item.regionId === row.regionId ? { ...item, values: previewCalculatedValues(worksheet, { ...item.values, [indicatorId]: value }) } : item) }
    worksheetRef.current = next
    setWorksheet(next)
    setSaveState('dirty')
    onBusyChange(true)
  }
  const processQueue = async () => {
    if (processingRef.current) return
    processingRef.current = true
    setSaveState('saving')
    setError('')
    while (queueRef.current.size) {
      const [code, value] = queueRef.current.entries().next().value as [string, string]
      queueRef.current.delete(code)
      const current = worksheetRef.current!
      const row = current.rows.find((item) => item.editable)!
      try {
        const server = await api.saveWorksheetCell(token, current, row, current.indicators[code], value)
        const pendingValues = new Map(queueRef.current)
        const next = pendingValues.size ? applyPendingValues(server, pendingValues) : server
        worksheetRef.current = next
        setWorksheet(next)
        onSynced(server)
      } catch (reason) {
        queueRef.current.set(code, value)
        setError(reason instanceof Error ? `${reason.message} Muat ulang data sebelum melanjutkan.` : 'Nilai belum tersimpan. Muat ulang data sebelum melanjutkan.')
        setSaveState('error')
        processingRef.current = false
        onBusyChange(true)
        return
      }
    }
    processingRef.current = false
    setSaveState('saved')
    onBusyChange(false)
  }
  const queueSave = (code: string, value: string) => {
    queueRef.current.set(code, value)
    void processQueue()
  }

  return (
    <section className="worksheet-shell" aria-label="Worksheet Tabel 1">
      <div className="worksheet-toolbar">
        <span>{editableRow ? `Wilayah Anda: ${shortRegion(editableRow.regionName)}` : 'Mode baca'}</span>
        <span className={saveState === 'saved' ? 'worksheet-saved' : saveState === 'error' ? 'worksheet-error' : ''} role={saveState === 'error' ? 'alert' : 'status'}>{saveState === 'dirty' ? 'Belum tersimpan' : saveState === 'saving' ? 'Menyimpan…' : saveState === 'saved' ? 'Semua perubahan tersimpan' : saveState === 'error' ? <>{error} <button type="button" className="worksheet-reload" onClick={() => void loadWorksheet()}>Muat ulang</button></> : 'Pilih sel berwarna kuning untuk mengubah nilai'}</span>
      </div>
      <div className="worksheet-scroll scrollbar-thin">
        <div className="worksheet-page">
          <p className="worksheet-number">TABEL 1</p>
          <h2>LUAS WILAYAH, JUMLAH DESA/KELURAHAN, JUMLAH PENDUDUK, JUMLAH RUMAH TANGGA,</h2>
          <h2>DAN KEPADATAN PENDUDUK MENURUT KECAMATAN</h2>
          <dl className="worksheet-meta"><dt>PROVINSI</dt><dd>KEPULAUAN BANGKA BELITUNG</dd><dt>TAHUN</dt><dd>{worksheet.year}</dd></dl>
          <table className="worksheet-table">
            <colgroup><col className="worksheet-col-no" /><col className="worksheet-col-region" /><col className="worksheet-col-area" /><col className="worksheet-col-village" /><col className="worksheet-col-ward" /><col className="worksheet-col-total" /><col className="worksheet-col-population" /><col className="worksheet-col-household" /><col className="worksheet-col-average" /><col className="worksheet-col-density" /></colgroup>
            <thead>
              <tr><th rowSpan={3}>NO</th><th rowSpan={3}>KABUPATEN / KOTA</th><th>LUAS WILAYAH</th><th colSpan={3}>JUMLAH</th><th rowSpan={3}>JUMLAH PENDUDUK</th><th rowSpan={3}>JUMLAH RUMAH TANGGA</th><th rowSpan={3}>RATA-RATA JIWA/RUMAH TANGGA</th><th rowSpan={2}>KEPADATAN PENDUDUK</th></tr>
              <tr><th rowSpan={2}>(km²)</th><th rowSpan={2}>DESA</th><th rowSpan={2}>KELURAHAN</th><th rowSpan={2}>DESA + KELURAHAN</th></tr>
              <tr><th>per km²</th></tr>
              <tr className="worksheet-column-numbers">{Array.from({ length: 10 }, (_, index) => <th key={index}>{index + 1}</th>)}</tr>
            </thead>
            <tbody>
              {worksheet.rows.map((row, index) => <tr key={row.regionId} className={row.editable ? 'worksheet-active-row' : ''}>
                <td>{index + 1}</td><th>{shortRegion(row.regionName).toUpperCase()}</th>
                {columns.map(([code, label]) => <WorksheetCell key={code} row={row} code={code} label={label} indicatorId={worksheet.indicators[code]} onChange={updateValue} onSave={(value) => queueSave(code, value)} />)}
              </tr>)}
              <WorksheetTotal worksheet={worksheet} />
            </tbody>
          </table>
          <p className="worksheet-source">Sumber: - Kantor Statistik Kabupaten/Kota<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- sumber lain…,,, (sebutkan)</p>
        </div>
      </div>
    </section>
  )
}

function WorksheetCell({ row, code, label, indicatorId, onChange, onSave }: { row: WorksheetRow; code: string; label: string; indicatorId: string; onChange: (row: WorksheetRow, code: string, value: string) => void; onSave: (value: string) => void }) {
  const value = row.values[indicatorId] || ''
  if (row.editable && baseCodes.has(code)) return <td className="worksheet-input-cell"><input aria-label={`${label} ${shortRegion(row.regionName)}`} inputMode="decimal" value={value} onChange={(event) => onChange(row, code, event.target.value)} onBlur={(event) => onSave(event.currentTarget.value)} onKeyDown={(event) => { if (event.key === 'Enter') event.currentTarget.blur() }} /></td>
  return <td className={baseCodes.has(code) ? '' : 'worksheet-formula'}>{formatNumber(value, code)}</td>
}

function WorksheetTotal({ worksheet }: { worksheet: TableOneWorksheet }) {
  const totals = Object.fromEntries(columns.map(([code]) => {
    const values = worksheet.rows.map((row) => parseNumber(row.values[worksheet.indicators[code]])).filter(Number.isFinite)
    return [code, baseCodes.has(code) || code === 'JUMLAH_DESA_KELURAHAN' ? values.reduce((sum, value) => sum + value, 0) : '']
  }))
  const population = Number(totals.JUMLAH_PENDUDUK)
  const households = Number(totals.JUMLAH_RUMAH_TANGGA)
  const area = Number(totals.LUAS_WILAYAH)
  totals.RATA_RATA_JIWA_RUMAH_TANGGA = households ? population / households : ''
  totals.KEPADATAN_PENDUDUK = area ? population / area : ''
  return <tr className="worksheet-total"><th colSpan={2}>KABUPATEN/KOTA</th>{columns.map(([code]) => <td key={code}>{formatNumber(String(totals[code]), code)}</td>)}</tr>
}

function applyPendingValues(worksheet: TableOneWorksheet, pending: Map<string, string>) {
  return { ...worksheet, rows: worksheet.rows.map((row) => row.editable ? { ...row, values: previewCalculatedValues(worksheet, { ...row.values, ...Object.fromEntries([...pending].map(([code, value]) => [worksheet.indicators[code], value])) }) } : row) }
}

function previewCalculatedValues(worksheet: TableOneWorksheet, values: Record<string, string>) {
  const number = (code: string) => {
    const value = values[worksheet.indicators[code]]
    const parsed = parseNumber(value)
    return value === '' || value === undefined || !Number.isFinite(parsed) ? null : parsed
  }
  const villages = number('JUMLAH_DESA')
  const wards = number('JUMLAH_KELURAHAN')
  const population = number('JUMLAH_PENDUDUK')
  const households = number('JUMLAH_RUMAH_TANGGA')
  const area = number('LUAS_WILAYAH')

  return {
    ...values,
    [worksheet.indicators.JUMLAH_DESA_KELURAHAN]: villages === null || wards === null ? '' : String(villages + wards),
    [worksheet.indicators.RATA_RATA_JIWA_RUMAH_TANGGA]: population === null || households === null || households === 0 ? '' : String(population / households),
    [worksheet.indicators.KEPADATAN_PENDUDUK]: population === null || area === null || area === 0 ? '' : String(population / area),
  }
}

function shortRegion(name: string) {
  return name.replace(/^(Kabupaten|Kota)\s+/i, '')
}

function formatNumber(value: string, code: string) {
  if (value === '' || value === 'undefined') return ''
  const number = parseNumber(value)
  if (!Number.isFinite(number)) return value
  const decimals = code === 'LUAS_WILAYAH' ? 1 : code === 'RATA_RATA_JIWA_RUMAH_TANGGA' || code === 'KEPADATAN_PENDUDUK' ? 1 : 0
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(number)
}

function parseNumber(value: string | undefined) {
  if (!value?.includes(',')) return Number(value)
  return Number(value.replace(/\./g, '').replace(',', '.'))
}
