import type { IndicatorRow } from '../api'
import { Input } from './ui/field'

const tableRows = [
  { key: 'PENDUDUK_15_PLUS', label: 'Penduduk berumur 15 tahun ke atas', number: '1', section: 'population' },
  { key: 'MELEK_HURUF', label: 'Penduduk berumur 15 tahun ke atas yang melek huruf', number: '2', section: 'literacy' },
  { key: 'TIDAK_MEMILIKI_IJAZAH_SD', label: 'a, Tidak memiliki ijazah SD', section: 'education' },
  { key: 'SD_MI', label: 'b, SD/MI', section: 'education' },
  { key: 'SMP_MTS', label: 'c, SMP/MTs', section: 'education' },
  { key: 'SMA_MA', label: 'd, SMA/MA', section: 'education' },
  { key: 'SEKOLAH_MENENGAH_KEJURUAN', label: 'e, Sekolah menengah kejuruan', section: 'education' },
  { key: 'DIPLOMA_I_II', label: 'f, Diploma I/Diploma II', section: 'education' },
  { key: 'AKADEMI_DIPLOMA_III', label: 'g, Akademi/Diploma III', section: 'education' },
  { key: 'S1_DIPLOMA_IV', label: 'h, S1/Diploma IV', section: 'education' },
  { key: 'S2_S3', label: 'i, S2/S3 (Master/Doktor)', section: 'education' },
] as const

export function TableThreeEducationGrid({ rows, editable, dirtyRows, province, regionName, onChange }: {
  rows: IndicatorRow[]
  editable: boolean
  dirtyRows: string[]
  province: boolean
  regionName: string
  onChange: (row: IndicatorRow) => void
}) {
  const byCode = new Map(rows.map((row) => [row.code, row]))

  function cell(code: string, label: string, decimals: number, unavailableSources: string[], shaded = false) {
    const row = byCode.get(code)
    if (shaded) return <td className="border-l border-line bg-[#858585] px-2 py-2" aria-label="Tidak berlaku" />
    if (!row) return <td className="border-l border-line px-2 py-2 text-ink-faint">—</td>
    const value = row.value.trim()
    if (row.kind === 'base' && editable) {
      return <td className="border-l border-line px-1.5 py-1">{input(row, label)}</td>
    }
    if (value) {
      return <td className="border-l border-line px-2 py-2 text-right"><span className="font-mono tabular">{formatNumber(value, decimals)}</span></td>
    }
    const incomplete = province && (row.kind === 'base' || unavailableSources.some((source) => !byCode.get(source)?.value.trim()))
    return <td className="border-l border-line px-2 py-2 text-center text-[10px] text-ink-muted">{province ? incomplete ? 'Belum lengkap' : 'Tidak Dapat Dihitung' : row.kind === 'derived' ? 'Tidak Dapat Dihitung' : row.notApplicable ? 'Tidak Berlaku' : '—'}</td>
  }

  function input(row: IndicatorRow, label: string) {
    return <Input
      aria-label={`${label}, ${row.code.endsWith('_L') ? 'Laki-laki' : 'Perempuan'}`}
      type="text"
      inputMode="numeric"
      value={row.value}
      onChange={(event) => onChange({ ...row, value: event.target.value, notApplicable: false, notApplicableReason: '' })}
      className={`min-w-20 px-2 text-right font-mono tabular ${dirtyRows.includes(row.id) ? 'border-pending bg-pending-soft/35' : ''}`}
    />
  }

  return <div>
    <p className="border-b border-line-soft px-4 py-2 text-[11px] text-ink-muted">{province ? 'Rekap otomatis dari seluruh Kabupaten/Kota. Jumlah dan persentase baru dihitung jika data semua wilayah terkait lengkap.' : 'Input jumlah penduduk laki-laki dan perempuan. Jumlah gabungan dan persentase dihitung otomatis saat draft disimpan.'}</p>
    <div className="overflow-x-auto scrollbar-thin">
      <table className="w-full min-w-[1120px] border-collapse text-left text-xs">
        <thead className="bg-[#6697e7] text-center text-[11px] font-semibold uppercase text-ink">
          <tr><th colSpan={8} className="border-b border-black/70 px-2 py-2 text-left">{province ? regionName : regionName.replace(/^(kabupaten|kota)\s+/i, '')}</th></tr>
          <tr className="border-y border-black/70"><th rowSpan={2} className="w-16 border-r border-black/70 px-3 py-3">No.</th><th rowSpan={2} className="w-[340px] border-r border-black/70 px-3 py-3">Variabel</th><th colSpan={3} className="border-r border-black/70 px-3 py-2">Jumlah</th><th colSpan={3} className="px-3 py-2">Persentase</th></tr>
          <tr className="border-b border-black/70"><th className="w-36 border-l border-black/70 px-2 py-2">Laki-laki</th><th className="w-36 border-l border-black/70 px-2 py-2">Perempuan</th><th className="w-36 border-l border-black/70 px-2 py-2">Laki-laki+Perempuan</th><th className="w-32 border-l border-black/70 px-2 py-2">Laki-laki</th><th className="w-32 border-l border-black/70 px-2 py-2">Perempuan</th><th className="w-36 border-l border-black/70 px-2 py-2">Laki-laki+Perempuan</th></tr>
          <tr className="border-b border-line bg-paper-inset italic"><th className="px-3 py-1.5">1</th><th className="px-3 py-1.5">2</th>{[3, 4, 5, 6, 7, 8].map((number) => <th key={number} className="px-2 py-1.5">{number}</th>)}</tr>
        </thead>
        <tbody>
          <tr className="border-b border-line-soft align-middle">
            <th scope="row" className="px-3 py-2 text-center font-mono">1</th><td className="border-l border-line px-3 py-2">{tableRows[0].label}</td>
            {cell('PENDUDUK_15_PLUS_L', tableRows[0].label, 0, [], false)}
            {cell('PENDUDUK_15_PLUS_P', tableRows[0].label, 0, [], false)}
            {cell('PENDUDUK_15_PLUS_TOTAL', tableRows[0].label, 0, ['PENDUDUK_15_PLUS_L', 'PENDUDUK_15_PLUS_P'])}
            {cell('', '', 0, [], true)}{cell('', '', 0, [], true)}{cell('', '', 0, [], true)}
          </tr>
          <tr className="border-b border-line-soft align-middle">
            <th scope="row" className="px-3 py-2 text-center font-mono">2</th><td className="border-l border-line px-3 py-2">{tableRows[1].label}</td>
            {cell('MELEK_HURUF_L', tableRows[1].label, 0, [])}
            {cell('MELEK_HURUF_P', tableRows[1].label, 0, [])}
            {cell('MELEK_HURUF_TOTAL', tableRows[1].label, 0, ['MELEK_HURUF_L', 'MELEK_HURUF_P'])}
            {cell('MELEK_HURUF_PERCENT_L', tableRows[1].label, 2, ['MELEK_HURUF_L', 'PENDUDUK_15_PLUS_L'])}
            {cell('MELEK_HURUF_PERCENT_P', tableRows[1].label, 2, ['MELEK_HURUF_P', 'PENDUDUK_15_PLUS_P'])}
            {cell('MELEK_HURUF_PERCENT_TOTAL', tableRows[1].label, 2, ['MELEK_HURUF_L', 'MELEK_HURUF_P', 'PENDUDUK_15_PLUS_L', 'PENDUDUK_15_PLUS_P'])}
          </tr>
          <tr className="border-b border-line-soft bg-paper-inset"><th scope="row" className="px-3 py-2 text-center font-mono">3</th><th className="border-l border-line px-3 py-2 text-left font-semibold">Persentase pendidikan tertinggi yang ditamatkan:</th><td colSpan={6} className="border-l border-line" /></tr>
          {tableRows.slice(2).map((item) => {
            const totalCode = `${item.key}_TOTAL`
            const percentTotal = `${item.key}_PERCENT_TOTAL`
            return <tr key={item.key} className="border-b border-line-soft align-middle hover:bg-paper-inset/60">
              <td className="px-3 py-2" /><th scope="row" className="border-l border-line px-3 py-2 text-left font-normal">{item.label}</th>
              {cell(`${item.key}_L`, item.label, 0, [])}
              {cell(`${item.key}_P`, item.label, 0, [])}
              {cell(totalCode, item.label, 0, [`${item.key}_L`, `${item.key}_P`])}
              {cell(`${item.key}_PERCENT_L`, item.label, 1, [`${item.key}_L`, 'PENDUDUK_15_PLUS_L'])}
              {cell(`${item.key}_PERCENT_P`, item.label, 1, [`${item.key}_P`, 'PENDUDUK_15_PLUS_P'])}
              {cell(percentTotal, item.label, 1, [`${item.key}_L`, `${item.key}_P`, 'PENDUDUK_15_PLUS_L', 'PENDUDUK_15_PLUS_P'])}
            </tr>
          })}
        </tbody>
      </table>
    </div>
  </div>
}

function formatNumber(value: string, decimals: number) {
  const number = Number(value)
  return Number.isFinite(number) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: decimals, minimumFractionDigits: decimals }).format(number) : value
}
