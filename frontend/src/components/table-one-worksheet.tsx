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

export function TableOneWorksheetView({ token, reportingTableId }: { token: string; reportingTableId: string }) {
  const [worksheet, setWorksheet] = useState<TableOneWorksheet | null>(null)
  const [message, setMessage] = useState('')
  const worksheetRef = useRef<TableOneWorksheet | null>(null)
  const savedValuesRef = useRef<Record<string, string>>({})
  const savingRef = useRef(false)
  const queuedSave = useRef(false)
  const saveRef = useRef<() => void>(() => undefined)

  useEffect(() => {
    void api.tableOneWorksheet(token, reportingTableId).then((data) => {
      setWorksheet(data)
      worksheetRef.current = data
      savedValuesRef.current = editableValues(data)
    }).catch((error) => setMessage(error instanceof Error ? error.message : 'Worksheet tidak dapat dimuat.'))
  }, [token, reportingTableId])

  if (!worksheet) return <div className="worksheet-loading" role="status">{message || 'Memuat worksheet…'}</div>

  const editableRow = worksheet.rows.find((row) => row.editable)
  const updateValue = (row: WorksheetRow, code: string, value: string) => {
    const indicatorId = worksheet.indicators[code]
    const next = { ...worksheet, rows: worksheet.rows.map((item) => item.regionId === row.regionId ? { ...item, values: { ...item.values, [indicatorId]: value } } : item) }
    worksheetRef.current = next
    setWorksheet(next)
    setMessage('Belum disimpan')
  }
  const save = async () => {
    const current = worksheetRef.current
    const row = current?.rows.find((item) => item.editable)
    if (!current || !row || JSON.stringify(editableValues(current)) === JSON.stringify(savedValuesRef.current)) return
    if (savingRef.current) {
      queuedSave.current = true
      return
    }
    const sentValues = editableValues(current)
    savingRef.current = true
    setMessage('Menyimpan…')
    try {
      const server = await api.saveWorksheetRow(token, current, row)
      const latest = worksheetRef.current || current
      const changedWhileSaving = JSON.stringify(editableValues(latest)) !== JSON.stringify(sentValues)
      const next = changedWhileSaving ? mergeEditableValues(server, latest) : server
      worksheetRef.current = next
      savedValuesRef.current = editableValues(server)
      setWorksheet(next)
      setMessage(changedWhileSaving ? 'Menyimpan perubahan berikutnya…' : 'Tersimpan')
      queuedSave.current ||= changedWhileSaving
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Gagal menyimpan.')
    } finally {
      savingRef.current = false
      if (queuedSave.current) {
        queuedSave.current = false
        queueMicrotask(() => saveRef.current())
      }
    }
  }
  saveRef.current = save

  return (
    <section className="worksheet-shell" aria-label="Worksheet Tabel 1">
      <div className="worksheet-toolbar">
        <span>{editableRow ? `Baris aktif: ${shortRegion(editableRow.regionName)}` : 'Mode baca'}</span>
        <span className={message === 'Tersimpan' ? 'worksheet-saved' : ''} role="status">{message || 'Klik sel putih untuk mengubah nilai'}</span>
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
                {columns.map(([code, label]) => <WorksheetCell key={code} row={row} code={code} label={label} indicatorId={worksheet.indicators[code]} onChange={updateValue} onSave={save} />)}
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

function WorksheetCell({ row, code, label, indicatorId, onChange, onSave }: { row: WorksheetRow; code: string; label: string; indicatorId: string; onChange: (row: WorksheetRow, code: string, value: string) => void; onSave: () => void }) {
  const value = row.values[indicatorId] || ''
  if (row.editable && baseCodes.has(code)) return <td className="worksheet-input-cell"><input aria-label={`${label} ${shortRegion(row.regionName)}`} inputMode="decimal" value={value} onChange={(event) => onChange(row, code, event.target.value)} onBlur={onSave} onKeyDown={(event) => { if (event.key === 'Enter') event.currentTarget.blur() }} /></td>
  return <td className={baseCodes.has(code) ? '' : 'worksheet-formula'}>{formatNumber(value, code)}</td>
}

function WorksheetTotal({ worksheet }: { worksheet: TableOneWorksheet }) {
  const totals = Object.fromEntries(columns.map(([code]) => {
    const values = worksheet.rows.map((row) => Number(row.values[worksheet.indicators[code]])).filter(Number.isFinite)
    return [code, baseCodes.has(code) || code === 'JUMLAH_DESA_KELURAHAN' ? values.reduce((sum, value) => sum + value, 0) : '']
  }))
  const population = Number(totals.JUMLAH_PENDUDUK)
  const households = Number(totals.JUMLAH_RUMAH_TANGGA)
  const area = Number(totals.LUAS_WILAYAH)
  totals.RATA_RATA_JIWA_RUMAH_TANGGA = households ? population / households : ''
  totals.KEPADATAN_PENDUDUK = area ? population / area : ''
  return <tr className="worksheet-total"><th colSpan={2}>KABUPATEN/KOTA</th>{columns.map(([code]) => <td key={code}>{formatNumber(String(totals[code]), code)}</td>)}</tr>
}

function editableValues(worksheet: TableOneWorksheet) {
  return worksheet.rows.find((row) => row.editable)?.values || {}
}

function mergeEditableValues(server: TableOneWorksheet, local: TableOneWorksheet) {
  const localRow = local.rows.find((row) => row.editable)
  if (!localRow) return server
  return { ...server, rows: server.rows.map((row) => row.regionId === localRow.regionId ? { ...row, values: localRow.values } : row) }
}

function shortRegion(name: string) {
  return name.replace(/^(Kabupaten|Kota)\s+/i, '')
}

function formatNumber(value: string, code: string) {
  if (value === '' || value === 'undefined') return ''
  const number = Number(value)
  if (!Number.isFinite(number)) return value
  const decimals = code === 'LUAS_WILAYAH' ? 1 : code === 'RATA_RATA_JIWA_RUMAH_TANGGA' || code === 'KEPADATAN_PENDUDUK' ? 1 : 0
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(number)
}
