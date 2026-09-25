import type { IndicatorRow } from '../api'
import { Input } from './ui/field'

const ownerOrder = ['KEMENKES', 'PEM_PROV', 'PEM_KAB_KOTA', 'TNI_POLRI', 'BUMN', 'SWASTA', 'ORGANISASI_KEMASYARAKATAN']

export function TableFourFacilitiesGrid({ rows, editable, dirtyRows, province, regionName, onChange }: {
  rows: IndicatorRow[]
  editable: boolean
  dirtyRows: string[]
  province: boolean
  regionName: string
  onChange: (row: IndicatorRow) => void
}) {
  const indicators = rows.filter((row) => row.metadata?.row_key)
  const facilities = new Map<string, IndicatorRow[]>()
  for (const row of indicators) {
    const key = row.metadata!.row_key
    facilities.set(key, [...(facilities.get(key) || []), row])
  }
  const sections = new Map<string, Map<string, IndicatorRow[]>>()
  for (const [key, facilityRows] of facilities) {
    const section = facilityRows[0].metadata?.section || ''
    if (!sections.has(section)) sections.set(section, new Map())
    sections.get(section)!.set(key, facilityRows)
  }
  const owners = ownerOrder.map((owner) => {
    const row = indicators.find((indicator) => indicator.metadata?.owner === owner)
    return { code: owner, label: row?.metadata?.owner_label || owner }
  })

  function cell(row: IndicatorRow | undefined, siblings: IndicatorRow[], label: string, cellKey: string) {
    if (!row) return <td key={cellKey} className="border-l border-line px-2 py-2 text-center text-ink-faint">—</td>
    if (row.kind === 'base' && editable) {
      return <td key={cellKey} className="border-l border-line px-1 py-1"><Input
        aria-label={`${label}, ${row.metadata?.owner_label || row.metadata?.owner || ''}`}
        type="text"
        inputMode="numeric"
        value={row.value}
        onChange={(event) => onChange({ ...row, value: event.target.value, notApplicable: false, notApplicableReason: '' })}
        className={`min-w-16 px-1.5 text-right font-mono tabular ${dirtyRows.includes(row.id) ? 'border-pending bg-pending-soft/35' : ''}`}
      /></td>
    }
    if (row.value.trim()) return <td key={cellKey} className="border-l border-line px-2 py-2 text-right font-mono tabular">{formatNumber(row.value)}</td>
    const incomplete = province && (row.kind === 'base' || siblings.some((item) => item.kind === 'base' && !item.value.trim()))
    return <td key={cellKey} className="border-l border-line px-1.5 py-2 text-center text-[10px] text-ink-muted">{province && incomplete ? 'Belum lengkap' : '—'}</td>
  }

  return <div>
    <p className="border-b border-line-soft px-4 py-2 text-[11px] text-ink-muted">{province ? 'Rekap otomatis dari tujuh Kabupaten/Kota; jumlah per fasilitas hanya tersedia setelah seluruh wilayah melengkapi kolom terkait.' : 'Isi seluruh kolom pemilikan/pengelola dengan jumlah fasilitas; masukkan 0 jika nilainya belum diketahui. Jumlah dihitung otomatis.'}</p>
    <div className="overflow-x-auto scrollbar-thin">
      <table className="w-full min-w-[1420px] border-collapse text-left text-xs">
        <thead className="text-center text-[11px] font-semibold uppercase text-ink">
          <tr><th colSpan={10} className="border-b border-line px-2 py-2 text-left font-bold tracking-wide">{province ? regionName : regionName.replace(/^(kabupaten|kota)\s+/i, '')}</th></tr>
          <tr className="border-y border-line bg-paper-inset"><th rowSpan={2} className="w-12 border-r border-line px-2 py-2">No.</th><th rowSpan={2} className="w-[360px] border-r border-line px-3 py-2">Fasilitas Kesehatan</th><th colSpan={7} className="border-r border-line px-3 py-2">Pemilikan/Pengelola</th><th rowSpan={2} className="w-24 border-l border-line px-2 py-2">Jumlah</th></tr>
          <tr className="border-b border-line bg-paper-inset">{owners.map((owner) => <th key={owner.code} className="w-32 border-l border-line px-2 py-2">{owner.label}</th>)}</tr>
          <tr className="border-b border-line bg-paper-inset italic"><th className="px-2 py-1.5">1</th><th className="px-2 py-1.5">2</th>{[3, 4, 5, 6, 7, 8, 9, 10].map((number) => <th key={number} className="px-2 py-1.5">{number}</th>)}</tr>
        </thead>
        {[...sections.entries()].map(([section, sectionRows]) => <tbody key={section}>
          <tr className="border-b border-line bg-paper-inset"><th colSpan={10} className="px-3 py-2 text-left font-bold">{section}</th></tr>
          {[...sectionRows.values()].map((facilityRows) => {
            const first = facilityRows[0]
            const key = first.metadata?.row_key || ''
            const label = first.metadata?.facility || first.name
            const bases = new Map(facilityRows.filter((row) => row.kind === 'base').map((row) => [row.metadata?.owner || '', row]))
            const total = facilityRows.find((row) => row.kind === 'derived')
            return <tr key={key} className="border-b border-line-soft align-middle hover:bg-paper-inset/60">
              <td className="px-2 py-2 text-center font-mono">{first.metadata?.row_number}</td>
              <th scope="row" className="border-l border-line px-3 py-2 text-left font-normal">{key === 'PUSKESMAS_TEMPAT_TIDUR' ? '– ' : ''}{label}</th>
              {owners.map((owner) => cell(bases.get(owner.code), facilityRows, label, owner.code))}
              {cell(total, facilityRows, label, total?.id || `${key}-total`)}
            </tr>
          })}
        </tbody>)}
      </table>
    </div>
  </div>
}

function formatNumber(value: string) {
  const number = Number(value)
  return Number.isFinite(number) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number) : value
}
