import type { IndicatorRow } from '../api'
import { Input } from './ui/field'

const ageGroups = [
  ['0_4', '0 - 4'], ['5_9', '5 - 9'], ['10_14', '10 - 14'], ['15_19', '15 - 19'],
  ['20_24', '20 - 24'], ['25_29', '25 - 29'], ['30_34', '30 - 34'], ['35_39', '35 - 39'],
  ['40_44', '40 - 44'], ['45_49', '45 - 49'], ['50_54', '50 - 54'], ['55_59', '55 - 59'],
  ['60_64', '60 - 64'], ['65_69', '65 - 69'], ['70_74', '70 - 74'], ['75_PLUS', '75+'],
] as const

export function TableTwoAgeGrid({ rows, editable, dirtyRows, onChange }: {
  rows: IndicatorRow[]
  editable: boolean
  dirtyRows: string[]
  onChange: (row: IndicatorRow) => void
}) {
  const byCode = new Map(rows.map((row) => [row.code, row]))

  function cell(code: string, label: string, age: string) {
    const row = byCode.get(code)
    if (!row) return <span className="text-ink-faint">—</span>
    if (row.kind === 'derived') return <span className="font-mono tabular text-ink">{row.value ? formatNumber(row.value, code === 'ANGKA_BEBAN_TANGGUNGAN' ? 2 : code.endsWith('RASIO') ? 1 : 0) : 'Tidak Dapat Dihitung'}</span>

    if (!editable) return <span className="font-mono tabular text-ink">{row.notApplicable ? 'Tidak Berlaku' : row.value ? formatNumber(row.value, 0) : '—'}</span>

    return <div className="flex min-w-32 flex-col gap-1.5">
      <Input aria-label={`${label}, ${age === '75+' ? 'usia 75 tahun ke atas' : `usia ${age} tahun`}`} type="text" inputMode="numeric" value={row.value} disabled={row.notApplicable} onChange={(event) => onChange({ ...row, value: event.target.value })} className={`font-mono tabular ${dirtyRows.includes(row.id) ? 'border-pending bg-pending-soft/35' : ''}`} />
      <label className="flex items-center gap-1.5 text-[10px] text-ink-muted"><input type="checkbox" checked={row.notApplicable} onChange={(event) => onChange({ ...row, notApplicable: event.target.checked, value: event.target.checked ? '' : row.value })} />Tidak Berlaku</label>
      {row.notApplicable ? <Input aria-label={`Alasan ${label} kelompok umur ${age} tidak berlaku`} placeholder="Alasan wajib" value={row.notApplicableReason} onChange={(event) => onChange({ ...row, notApplicableReason: event.target.value })} /> : null}
    </div>
  }

  return <div><p className="border-b border-line-soft px-4 py-2 text-[11px] text-ink-muted">Jumlah penduduk menurut kelompok umur. Total dan rasio dihitung dari Laki-laki dan Perempuan setelah Simpan draft.</p><div className="overflow-x-auto scrollbar-thin">
    <table className="w-full min-w-[790px] border-collapse text-left text-xs">
      <thead className="bg-paper-inset text-[10px] uppercase tracking-[0.06em] text-ink-muted"><tr><th scope="col" className="w-16 px-4 py-3">No.</th><th scope="col" className="px-3 py-3">Kelompok Umur (Tahun)</th><th scope="col" className="w-44 px-3 py-3">Laki-laki</th><th scope="col" className="w-44 px-3 py-3">Perempuan</th><th scope="col" className="w-36 px-3 py-3">Laki-laki+Perempuan</th><th scope="col" className="w-40 px-3 py-3">Rasio Jenis Kelamin</th></tr></thead>
      <tbody>{ageGroups.map(([key, age], index) => <tr key={key} className="border-t border-line-soft align-top"><td className="px-4 py-3 font-mono text-ink-muted">{index + 1}</td><th scope="row" className="px-3 py-3 font-semibold">{age}</th><td className="px-3 py-2">{cell(`PENDUDUK_${key}_L`, 'Laki-laki', age)}</td><td className="px-3 py-2">{cell(`PENDUDUK_${key}_P`, 'Perempuan', age)}</td><td className="px-3 py-3">{cell(`PENDUDUK_${key}_TOTAL`, 'Total', age)}</td><td className="px-3 py-3">{cell(`PENDUDUK_${key}_RASIO`, 'Rasio jenis kelamin', age)}</td></tr>)}</tbody>
      <tfoot className="border-t border-line bg-paper-inset font-semibold"><tr><th colSpan={2} scope="row" className="px-4 py-3 text-left">Kabupaten/Kota</th><td className="px-3 py-3">{cell('PENDUDUK_TOTAL_L', 'Laki-laki', 'semua')}</td><td className="px-3 py-3">{cell('PENDUDUK_TOTAL_P', 'Perempuan', 'semua')}</td><td className="px-3 py-3">{cell('PENDUDUK_TOTAL', 'Total', 'semua')}</td><td className="px-3 py-3">{cell('PENDUDUK_TOTAL_RASIO', 'Rasio jenis kelamin', 'semua')}</td></tr><tr className="border-t border-line-soft"><th colSpan={5} scope="row" className="px-4 py-3 text-left">Angka Beban Tanggungan</th><td className="px-3 py-3">{cell('ANGKA_BEBAN_TANGGUNGAN', 'Beban tanggungan', 'semua')}</td></tr></tfoot>
    </table>
  </div></div>
}

function formatNumber(value: string, decimals: number) {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: decimals, minimumFractionDigits: decimals }).format(numeric) : value
}
