import type { CatalogIndicator, DashboardData, ManagedUser, ReportingStatus, ReportingTable, ReportingTableDetail } from './api'

const tableNames = [
  'Luas Wilayah dan Kependudukan',
  'Penduduk Menurut Jenis Kelamin',
  'Penduduk Menurut Kelompok Umur',
  'Sarana Kesehatan',
  'Tenaga Kesehatan',
  'Puskesmas dan Jaringannya',
  'Rumah Sakit',
  'Kelahiran dan Kematian Bayi',
  'Kesehatan Ibu',
  'Kesehatan Anak',
  'Imunisasi Dasar Lengkap',
  'Gizi Masyarakat',
  'Penyakit Menular',
  'Penyakit Tidak Menular',
  'Kesehatan Lingkungan',
  'Pembiayaan Kesehatan',
  'Pelayanan Kefarmasian',
  'Jaminan Kesehatan',
]

function statusFor(index: number): ReportingStatus {
  if (index <= 46) return 'verified'
  if (index <= 70) return 'completed'
  return 'not_started'
}

export function demoTables(): ReportingTable[] {
  return Array.from({ length: 87 }, (_, index) => {
    const number = index + 1
    const status = statusFor(number)
    const completion = status === 'verified' ? 100 : status === 'completed' ? 100 : number % 4 === 0 ? 35 : 0
    return {
      id: String(number),
      version: 1,
      number,
      name: tableNames[index % tableNames.length] + (index >= tableNames.length ? ` ${Math.floor(index / tableNames.length) + 1}` : ''),
      group: number <= 18 ? 'Gambaran Umum' : number <= 44 ? 'Sumber Daya Kesehatan' : number <= 70 ? 'Upaya Kesehatan' : 'Capaian Program',
      status,
      completion,
      updatedAt: status === 'not_started' && completion === 0 ? undefined : `2026-09-${String(24 - (number % 12)).padStart(2, '0')}T08:30:00Z`,
      updatedBy: status === 'verified' ? 'Administrator Sistem' : 'Nadia Rahmawati',
      mappingStatus: 'ready',
    }
  })
}

export function demoDashboard(year: number): DashboardData {
  const reportingTables = demoTables()
  return {
    year,
    availableYears: [2026, 2025, 2024],
    reportingTables,
    regions: [
      { id: '1', name: 'Kabupaten Bangka', verified: 46, completed: 24, notStarted: 17, total: 87 },
      { id: '2', name: 'Kabupaten Belitung', verified: 62, completed: 17, notStarted: 8, total: 87 },
      { id: '3', name: 'Kabupaten Bangka Barat', verified: 39, completed: 31, notStarted: 17, total: 87 },
      { id: '4', name: 'Kabupaten Bangka Tengah', verified: 52, completed: 25, notStarted: 10, total: 87 },
      { id: '5', name: 'Kabupaten Bangka Selatan', verified: 68, completed: 12, notStarted: 7, total: 87 },
      { id: '6', name: 'Kabupaten Belitung Timur', verified: 31, completed: 29, notStarted: 27, total: 87 },
      { id: '7', name: 'Kota Pangkalpinang', verified: 57, completed: 20, notStarted: 10, total: 87 },
    ],
    recentTables: reportingTables.filter((table) => table.updatedAt).slice(42, 48).reverse(),
  }
}

export function demoTableDetail(id: string, year: number): ReportingTableDetail {
  const table = demoTables().find((item) => item.id === id) || demoTables()[0]
  const rows = Array.from({ length: 12 }, (_, index) => ({
    id: `${table.id}-${index + 1}`,
    code: `${String(table.number).padStart(2, '0')}.${String(index + 1).padStart(2, '0')}`,
    name: ['Jumlah sasaran', 'Jumlah pelayanan', 'Laki-laki', 'Perempuan'][index % 4] + ` indikator ${index + 1}`,
    category: ['Total', 'Puskesmas', 'Rumah Sakit'][index % 3],
    unit: index % 4 === 0 ? 'orang' : index % 4 === 1 ? 'kunjungan' : index % 4 === 2 ? 'kasus' : '%',
    value: index % 5 === 0 ? '' : String(120 + table.number * 3 + index * 17),
    kind: index % 4 === 3 ? 'derived' as const : 'base' as const,
    dataType: 'numeric' as const,
    required: true,
    notApplicable: false,
    notApplicableReason: '',
    note: index % 6 === 0 ? 'Perlu konfirmasi sumber data fasilitas.' : undefined,
  }))
  return {
    ...table,
    group: table.number === 1 ? 'T01' : table.group,
    reportingTableId: table.id,
    regionId: '1',
    submissionId: table.id,
    description: 'Rekapitulasi Nilai Indikator menurut kategori dan fasilitas pada Kabupaten/Kota untuk Tahun Pelaporan terpilih.',
    year,
    region: 'Kabupaten Bangka',
    rows,
    headerCandidates: [],
  }
}

export const demoUsers: ManagedUser[] = [
  { id: '1', name: 'Rina Handayani', email: 'rina@dinkes.go.id', role: 'administrator', active: true },
  { id: '2', name: 'Nadia Rahmawati', email: 'nadia@banjarbaru.go.id', role: 'operator', region: 'Kota Banjarbaru', active: true },
  { id: '3', name: 'Ahmad Fauzi', email: 'ahmad@kotabaru.go.id', role: 'operator', region: 'Kabupaten Kotabaru', active: false },
]

export const demoCatalog: CatalogIndicator[] = Array.from({ length: 18 }, (_, index) => ({
  id: String(index + 1),
  code: `IK-${String(index + 1).padStart(3, '0')}`,
  name: ['Jumlah penduduk', 'Jumlah fasilitas aktif', 'Cakupan pelayanan', 'Rasio tenaga kesehatan'][index % 4] + ` ${Math.floor(index / 4) + 1}`,
  unit: ['orang', 'unit', '%', 'per 100.000 penduduk'][index % 4],
  tableName: `Tabel ${String((index % 8) + 1).padStart(2, '0')} — ${tableNames[index % tableNames.length]}`,
}))
