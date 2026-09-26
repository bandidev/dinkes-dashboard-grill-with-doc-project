import type { IndicatorRow } from '../api'
import { Input } from './ui/field'

const categories = [
  ['UMUM', 'Rumah Sakit Umum'],
  ['KHUSUS', 'Rumah Sakit Khusus'],
] as const

export function TableSixEmergencyGrid({ rows, editable, dirtyRows, province, regionName, onChange }: {
  rows: IndicatorRow[]
  editable: boolean
  dirtyRows: string[]
  province: boolean
  regionName: string
  onChange: (row: IndicatorRow) => void
}) {
  const byCode = new Map(rows.map((row) => [row.code, row]))

  function display(row: IndicatorRow | undefined, denominator?: IndicatorRow, numerator?: IndicatorRow) {
    if (!row) return '—'
    if (!row.value.trim()) {
      if (row.kind === 'base') return province ? 'Belum lengkap' : '—'
      if (province && (!denominator?.value.trim() || !numerator?.value.trim())) return 'Belum lengkap'
      if (!province && (!denominator?.value.trim() || !numerator?.value.trim())) return '—'
      if (denominator?.value === '0') return '—'
      return province ? 'Belum lengkap' : 'Tidak Dapat Dihitung'
    }
    const value = Number(row.value.replace(',', '.'))
    if (!Number.isFinite(value)) return row.value
    const formatted = new Intl.NumberFormat('id-ID', {
      minimumFractionDigits: row.unit === '%' ? 2 : 0,
      maximumFractionDigits: row.unit === '%' ? 2 : 0,
    }).format(value)
    return row.unit === '%' ? `${formatted}%` : formatted
  }

  function cell(row: IndicatorRow | undefined, label: string, denominator?: IndicatorRow) {
    return <td key={label} className="border-l border-line px-2 py-2 text-right">
      {row?.kind === 'base' && editable ? <Input
        aria-label={`${label}, ${row.name}`}
        type="text"
        inputMode="numeric"
        value={row.value}
        onChange={(event) => onChange({ ...row, value: event.target.value, notApplicable: false, notApplicableReason: '' })}
        className={`min-w-24 px-2 text-right font-mono tabular ${dirtyRows.includes(row.id) ? 'border-pending bg-pending-soft/35' : ''}`}
      /> : <span className={`block px-1.5 py-2 font-mono tabular ${row?.kind === 'derived' ? 'font-semibold' : ''}`}>{display(row, denominator, row?.code.endsWith('_PERSENTASE_GADAR_LEVEL_I') ? byCode.get(row.code.replace('PERSENTASE_GADAR_LEVEL_I', 'MAMPU_GADAR_LEVEL_I')) : undefined)}</span>}
    </td>
  }

  return <div>
    <p className="border-b border-line-soft px-4 py-2 text-[11px] text-ink-muted">{province ? 'Rekap sementara ini memakai nilai awal dari Sheet 6, sehingga angka tersedia sebelum semua Operator menyatakan Selesai Input. Gunakan progres di atas untuk melihat jumlah Kabupaten/Kota yang sudah selesai. Persentase dihitung dari total, bukan rata-rata.' : 'Isi empat Nilai Dasar. Jumlah yang mampu Gadar Level I tidak boleh melebihi jumlah rumah sakit; ketik 0 bila hasilnya sudah dipastikan nol.'}</p>
    <div className="overflow-x-auto scrollbar-thin">
      <table className="w-full min-w-[760px] border-collapse text-left text-xs">
        <thead className="text-center text-[10px] font-semibold uppercase text-ink">
          <tr><th colSpan={5} className="border-b border-line px-3 py-2 text-left font-bold">{province ? regionName : regionName.replace(/^(kabupaten|kota)\s+/i, '')}</th></tr>
          <tr className="border-y border-line bg-paper-inset"><th rowSpan={2} className="w-12 px-2 py-2">No.</th><th rowSpan={2} className="w-64 border-l border-line px-3 py-2 text-left">Jenis Rumah Sakit</th><th colSpan={2} className="border-l border-line px-2 py-2">Nilai Dasar</th><th rowSpan={2} className="w-36 border-l border-line px-2 py-2">Persentase Mampu Gadar Level I</th></tr>
          <tr className="border-b border-line bg-paper-inset"><th className="w-36 border-l border-line px-2 py-2">Jumlah</th><th className="w-44 border-l border-line px-2 py-2">Mampu Gadar Level I</th></tr>
        </thead>
        <tbody>
          {categories.map(([key, label], index) => {
            const count = byCode.get(`RS_${key}_JUMLAH`)
            const capable = byCode.get(`RS_${key}_MAMPU_GADAR_LEVEL_I`)
            const percent = byCode.get(`RS_${key}_PERSENTASE_GADAR_LEVEL_I`)
            return <tr key={key} className="border-b border-line-soft align-middle hover:bg-paper-inset/60">
              <td className="px-2 py-2 text-center font-mono">{index + 1}</td>
              <th scope="row" className="border-l border-line px-3 py-2 text-left font-normal">{label}</th>
              {cell(count, label)}
              {cell(capable, label)}
              {cell(percent, `${label}, persentase`, count)}
            </tr>
          })}
        </tbody>
        <tbody><tr className="border-t-2 border-line bg-paper-inset font-bold">
          <td className="px-2 py-2" /><th scope="row" className="border-l border-line px-3 py-2 text-left">Jumlah Seluruh Rumah Sakit</th>
          {cell(byCode.get('RS_TOTAL_JUMLAH'), 'Jumlah seluruh rumah sakit')}
          {cell(byCode.get('RS_TOTAL_MAMPU_GADAR_LEVEL_I'), 'Jumlah mampu Gadar Level I')}
          {cell(byCode.get('RS_TOTAL_PERSENTASE_GADAR_LEVEL_I'), 'Persentase seluruh rumah sakit', byCode.get('RS_TOTAL_JUMLAH'))}
        </tr></tbody>
      </table>
    </div>
  </div>
}
