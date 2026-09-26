import type { IndicatorRow } from '../api'
import { Input } from './ui/field'

const measures = [
  ['RAWAT_JALAN_L', 'Rawat Jalan L', 'Laki-laki'], ['RAWAT_JALAN_P', 'Rawat Jalan P', 'Perempuan'], ['RAWAT_JALAN_LP', 'Rawat Jalan L+P', 'L+P'],
  ['RAWAT_INAP_L', 'Rawat Inap L', 'Laki-laki'], ['RAWAT_INAP_P', 'Rawat Inap P', 'Perempuan'], ['RAWAT_INAP_LP', 'Rawat Inap L+P', 'L+P'],
  ['GANGGUAN_JIWA_L', 'Gangguan Jiwa L', 'Laki-laki'], ['GANGGUAN_JIWA_P', 'Gangguan Jiwa P', 'Perempuan'], ['GANGGUAN_JIWA_LP', 'Gangguan Jiwa L+P', 'L+P'],
] as const

const summaryRows = [
  ['SUBTOTAL_I', 'Subjumlah I'],
  ['SUBTOTAL_II', 'Subjumlah II'],
  ['TOTAL_UTAMA', 'Jumlah Utama'],
] as const

export function TableFiveVisitsGrid({ rows, editable, dirtyRows, province, regionName, onChange, denominators, coverageValues, denominatorsDirty = false, onDenominatorChange }: {
  rows: IndicatorRow[]
  editable: boolean
  dirtyRows: string[]
  province: boolean
  regionName: string
  onChange: (row: IndicatorRow) => void
   denominators?: { outpatient_l: string; outpatient_p: string; inpatient_l: string; inpatient_p: string; outpatient_total?: string; inpatient_total?: string }
  coverageValues?: Record<string, number | null>
  denominatorsDirty?: boolean
  onDenominatorChange?: (key: 'outpatient_l' | 'outpatient_p' | 'inpatient_l' | 'inpatient_p', value: string) => void
}) {
  const rowByCode = new Map(rows.map((row) => [row.code, row]))
  const facilities = new Map<string, IndicatorRow[]>()
  for (const row of rows) {
    if (!row.metadata?.row_key || ['SUBTOTAL_I', 'SUBTOTAL_II', 'TOTAL_UTAMA'].includes(row.metadata.row_key)) continue
    const key = row.metadata.row_key
    facilities.set(key, [...(facilities.get(key) || []), row])
  }
  const sections = new Map<string, Array<[string, IndicatorRow[]]>>()
  for (const [key, facilityRows] of facilities) {
    const section = facilityRows[0].metadata?.section || ''
    sections.set(section, [...(sections.get(section) || []), [key, facilityRows]])
  }

  function indicator(key: string, metric: string) {
    return rowByCode.get(`KUNJUNGAN_${key}_${metric}`)
  }

  function display(row: IndicatorRow | undefined) {
    if (!row) return <span className="text-ink-faint">—</span>
    if (!row.value.trim()) return <span className="text-ink-faint">{province ? 'Belum lengkap' : row.kind === 'derived' ? 'Tidak Dapat Dihitung' : '—'}</span>
    const value = Number(row.value)
    return Number.isFinite(value) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value) : row.value
  }

  function valueCell(row: IndicatorRow | undefined, label: string) {
    if (!row) return <td key={label} className="border-l border-line px-2 py-2 text-center">—</td>
    return <td key={label} className="border-l border-line px-1 py-1 text-right">
      {row.kind === 'base' && editable ? <Input
        aria-label={`${label}, ${row.name}`}
        type="text"
        inputMode="numeric"
        value={row.value}
        onChange={(event) => onChange({ ...row, value: event.target.value, notApplicable: false, notApplicableReason: '' })}
        className={`min-w-20 px-1.5 text-right font-mono tabular ${dirtyRows.includes(row.id) ? 'border-pending bg-pending-soft/35' : ''}`}
      /> : <span className="block px-1.5 py-2 font-mono tabular">{display(row)}</span>}
    </td>
  }

  return <div>
    {province && denominators ? <div className="border-b border-line-soft p-4">
      <div className="mb-2 flex flex-wrap items-center justify-between gap-2"><h2 className="text-xs font-bold">Nilai Dasar penyebut penduduk Provinsi</h2>{denominatorsDirty ? <span className="text-[10px] font-semibold text-pending">Perubahan belum disimpan</span> : null}</div>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {([
          ['outpatient_l', 'Rawat jalan — Laki-laki'], ['outpatient_p', 'Rawat jalan — Perempuan'],
          ['inpatient_l', 'Rawat inap — Laki-laki'], ['inpatient_p', 'Rawat inap — Perempuan'],
        ] as const).map(([key, label]) => <label key={key} className="flex flex-col gap-1 text-[10px] font-semibold text-ink-muted">{label}<Input
          aria-label={label}
          type="text"
          inputMode="numeric"
          value={denominators[key]}
          disabled={!onDenominatorChange}
          onChange={(event) => onDenominatorChange?.(key, event.target.value)}
          className={`font-mono tabular ${denominatorsDirty ? 'border-pending bg-pending-soft/35' : ''}`}
        /></label>)}
      </div>
      <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-ink-muted">
        <span>Nilai Turunan penyebut rawat jalan: <strong className="font-mono tabular text-ink">{sum(denominators.outpatient_l, denominators.outpatient_p)}</strong></span>
        <span>Nilai Turunan penyebut rawat inap: <strong className="font-mono tabular text-ink">{sum(denominators.inpatient_l, denominators.inpatient_p)}</strong></span>
      </div>
    </div> : null}
    <p className="border-b border-line-soft px-4 py-2 text-[11px] text-ink-muted">{province ? 'Rekap hanya tersedia setelah seluruh tujuh Kabupaten/Kota mengisi Nilai Dasar terkait.' : 'Isi enam Nilai Dasar untuk setiap jenis fasilitas. Nilai Turunan L+P dihitung otomatis; masukkan 0 jika nilainya telah dipastikan nol.'}</p>
    <div className="overflow-x-auto scrollbar-thin">
      <table className="w-full min-w-[1400px] border-collapse text-left text-xs">
        <thead className="text-center text-[10px] font-semibold uppercase text-ink">
          <tr><th colSpan={11} className="border-b border-line px-3 py-2 text-left font-bold">{province ? regionName : regionName.replace(/^(kabupaten|kota)\s+/i, '')}</th></tr>
          <tr className="border-y border-line bg-paper-inset"><th rowSpan={2} className="w-12 px-2 py-2">No.</th><th rowSpan={2} className="w-72 border-l border-line px-3 py-2 text-left">Fasilitas Pelayanan Kesehatan</th><th colSpan={3} className="border-l border-line px-2 py-2">Rawat Jalan</th><th colSpan={3} className="border-l border-line px-2 py-2">Rawat Inap</th><th colSpan={3} className="border-l border-line px-2 py-2">Kunjungan Gangguan Jiwa</th></tr>
          <tr className="border-b border-line bg-paper-inset">{measures.map(([key, , label]) => <th key={key} className="w-32 border-l border-line px-2 py-2">{label}</th>)}</tr>
        </thead>
        {[...sections.entries()].map(([section, entries]) => <tbody key={section}>
          <tr className="border-b border-line bg-paper-inset"><th colSpan={11} className="px-3 py-2 text-left font-bold">{section}</th></tr>
          {entries.map(([key, facilityRows]) => {
            const label = facilityRows[0].metadata?.facility || facilityRows[0].name
            return <tr key={key} className="border-b border-line-soft align-middle hover:bg-paper-inset/60">
              <td className="px-2 py-2 text-center font-mono">{facilityRows[0].metadata?.row_number}</td>
              <th scope="row" className="border-l border-line px-3 py-2 text-left font-normal">{label}</th>
              {measures.map(([metric, columnLabel]) => valueCell(indicator(key, metric), `${label}, ${columnLabel}`))}
            </tr>
          })}
          {summaryRows.filter(([key]) => key === (section.endsWith('Tingkat Pertama') ? 'SUBTOTAL_I' : 'SUBTOTAL_II')).map(([key, label]) => <tr key={key} className="border-t border-line bg-paper-raised font-bold">
            <td className="px-2 py-2" /><th scope="row" className="border-l border-line px-3 py-2 text-left">{label}</th>
            {measures.map(([metric, columnLabel]) => valueCell(indicator(key, metric), `${label}, ${columnLabel}`))}
          </tr>)}
        </tbody>)}
        <tbody><tr className="border-t-2 border-line bg-paper-inset font-bold">
          <td className="px-2 py-2" /><th scope="row" className="border-l border-line px-3 py-2 text-left">Jumlah Utama</th>
          {measures.map(([metric, columnLabel]) => valueCell(indicator('TOTAL_UTAMA', metric), `Jumlah Utama, ${columnLabel}`))}
        </tr></tbody>
        {province ? <tbody><tr className="border-t border-line bg-paper-raised font-bold"><td className="px-2 py-2" /><th scope="row" className="border-l border-line px-3 py-2 text-left">Cakupan Kunjungan (%)</th>
          {(['outpatient_l', 'outpatient_p', 'outpatient_total', 'inpatient_l', 'inpatient_p', 'inpatient_total'] as const).map((key) => {
            const value = coverageValues?.[key]
            return <td key={key} className="border-l border-line px-2 py-2 text-right font-mono tabular">{value === null || value === undefined ? 'Tidak Dapat Dihitung' : `${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)}%`}</td>
          })}
          {measures.slice(6).map(([key]) => <td key={key} className="border-l border-line px-2 py-2 text-center text-[10px] text-ink-muted">Tidak dihitung</td>)}
        </tr></tbody> : null}
      </table>
    </div>
  </div>
}

function sum(left: string, right: string) {
  if (!left.trim() || !right.trim()) return 'Tidak Dapat Dihitung'
  const values = [Number(left), Number(right)]
  if (values.some((value) => !Number.isInteger(value) || value < 0)) return 'Tidak Dapat Dihitung'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(values[0] + values[1])
}
