export type UserRole = 'administrator' | 'operator'

export type SessionUser = {
  id: string
  name: string
  email: string
  role: UserRole
  region?: string
  regionId?: string
}

export type ReportingStatus = 'not_started' | 'completed' | 'verified'

export type RegionStatusCounts = {
  total: number
  verified: number
  completed: number
  notStarted: number
}

export type ReportingTable = {
  id: string
  submissionId?: string
  regionId?: string
  version: number
  number: number
  name: string
  group: string
  status: ReportingStatus
  completion: number
  regionCounts?: RegionStatusCounts
  updatedAt?: string
  updatedBy?: string
  mappingStatus: 'ready' | 'pending'
}

export type RegionProgress = {
  id: string
  name: string
  verified: number
  completed: number
  notStarted: number
  total: number
}

export type DashboardData = {
  year: number
  availableYears: number[]
  reportingTables: ReportingTable[]
  regions: RegionProgress[]
  recentTables: ReportingTable[]
}

export type IndicatorRow = {
  id: string
  code: string
  name: string
  category: string
  unit: string
  value: string
  kind: 'base' | 'derived'
  dataType: 'numeric' | 'text' | 'date'
  required: boolean
  notApplicable: boolean
  notApplicableReason: string
  metadata?: Record<string, string>
  note?: string
}

export type ReportingTableDetail = ReportingTable & {
  scope?: 'province'
  submissionId?: string
  reportingTableId: string
  regionId: string
  description: string
  year: number
  region: string
  rows: IndicatorRow[]
  headerCandidates: string[]
}

export type WorksheetRow = {
  regionId: string
  regionName: string
  submissionId?: string
  status: ReportingStatus
  version: number
  editable: boolean
  values: Record<string, string>
}

export type TableOneWorksheet = {
  reportingTableId: string
  year: number
  indicators: Record<string, string>
  rows: WorksheetRow[]
}

export type ManagedUser = SessionUser & { active: boolean }
export type CatalogIndicator = { id: string; code: string; name: string; unit: string; tableName: string }

type Region = { id: number; name: string }
type ReportingYear = { id: number; year: number; status: 'open' | 'closed' }
type Indicator = {
  id: number
  code: string
  name: string
  data_type: 'numeric' | 'text' | 'date'
  unit: string | null
  is_required: boolean
  value_kind: 'base' | 'derived'
  decimal_places: number
  categories?: Record<string, string> | null
}
type ApiTable = {
  id: number
  code: string
  name: string
  description: string | null
  position: number
  reporting_year_id: number
  indicators: Indicator[]
  mapping_status: 'ready' | 'pending'
  source_metadata?: { header_candidates?: string[] } | null
}
type ApiSubmissionListItem = {
  id: number | null
  region_id: number | null
  status: ReportingStatus
  version: number
  values_count: number
  updated_at: string | null
  region_counts?: { total: number; verified: number; completed: number; not_started: number }
  reporting_table: ApiTable
}
type ApiValue = {
  indicator_id: number
  numeric_value: string | null
  text_value: string | null
  date_value: string | null
  not_applicable: boolean
  not_applicable_reason: string | null
  indicator: Indicator
}
type ApiSubmission = {
  id: number
  status: ReportingStatus
  version: number
  updated_at: string
  region: Region
  reporting_table: ApiTable
  values: ApiValue[]
  calculated_values?: Record<string, number | null>
}
type ApiWorksheet = {
  table: ApiTable & { reporting_year: ReportingYear }
  rows: Array<{
    region: Region
    submission_id: number | null
    status: ReportingStatus
    version: number
    editable: boolean
    values: Record<string, string | number | null>
    calculated_values: Record<string, number | null>
  }>
}

type ApiProvince = {
  table: ApiTable
  values: Record<string, number>
  calculated_values: Record<string, number | null>
  complete_base_count: number
  base_count: number
  region_count: number
}

const API_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/$/, '')
const pendingGetRequests = new Map<string, Promise<unknown>>()
const cachedGetResponses = new Map<string, unknown>()

export class ApiError extends Error {
  status: number

  constructor(message: string, status = 0) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

async function request<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const cacheKey = !options.method || options.method === 'GET' ? `${token || ''}:${path}` : null
  if (cacheKey && cachedGetResponses.has(cacheKey)) return cachedGetResponses.get(cacheKey) as T
  const pending = cacheKey ? pendingGetRequests.get(cacheKey) : null
  if (pending) return pending as Promise<T>

  const execute = async () => {
    const response = await fetch(`${API_URL}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(token && !token.startsWith('preview-') ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    }).catch(() => {
      throw new ApiError('Tidak dapat terhubung ke layanan API.')
    })

    if (response.status === 204) return undefined as T
    const payload = await response.json().catch(() => null)
    if (!response.ok) {
      const validation = payload && typeof payload === 'object' && 'errors' in payload
        ? Object.values((payload as { errors: Record<string, string[]> }).errors).flat()[0]
        : null
      const message = validation || (payload && typeof payload === 'object' && 'message' in payload
        ? String(payload.message)
        : `Permintaan gagal (${response.status}).`)
      throw new ApiError(String(message), response.status)
    }
    return payload as T
  }

  const promise = execute()
  if (cacheKey) pendingGetRequests.set(cacheKey, promise)

  try {
    const payload = await promise
    if (cacheKey && (path === '/reporting-years' || path === '/regions')) cachedGetResponses.set(cacheKey, payload)
    return payload
  } finally {
    if (cacheKey) pendingGetRequests.delete(cacheKey)
  }
}

function normalizeUser(user: { id: number; name: string; email: string; role: UserRole; region_id?: number | null; region?: Region | null }): SessionUser {
  return { ...user, id: String(user.id), region: user.region?.name, regionId: user.region_id ? String(user.region_id) : undefined }
}

async function reportingContext(token: string, requestedYear: number) {
  const [years, regions] = await Promise.all([
    request<ReportingYear[]>('/reporting-years', {}, token),
    request<Region[]>('/regions', {}, token),
  ])
  const reportingYear = years.find((item) => item.year === requestedYear) || years[0]
  if (!reportingYear) throw new ApiError('Tahun Pelaporan belum tersedia.', 404)
  return { years, regions, reportingYear }
}

function tableFromSubmission(item: ApiSubmissionListItem): ReportingTable {
  const indicatorCount = item.reporting_table.indicators.filter((indicator) => indicator.value_kind === 'base' && indicator.is_required).length
  const regionCounts = item.region_counts
  return {
    id: String(item.reporting_table.id),
    submissionId: item.id ? String(item.id) : undefined,
    regionId: item.region_id ? String(item.region_id) : undefined,
    version: item.version,
    number: item.reporting_table.position,
    name: item.reporting_table.name,
    group: item.reporting_table.code,
    status: item.status,
    completion: regionCounts
      ? regionCounts.total ? Math.round((regionCounts.verified + regionCounts.completed) / regionCounts.total * 100) : 0
      : item.status === 'completed' || item.status === 'verified'
        ? 100
        : indicatorCount ? Math.min(100, Math.round(item.values_count / indicatorCount * 100)) : 0,
    regionCounts: regionCounts ? {
      total: regionCounts.total,
      verified: regionCounts.verified,
      completed: regionCounts.completed,
      notStarted: regionCounts.not_started,
    } : undefined,
    updatedAt: item.updated_at || undefined,
    mappingStatus: item.reporting_table.mapping_status,
  }
}

function valueText(value: ApiValue | undefined, indicator: Indicator) {
  if (!value || value.not_applicable) return ''
  if (indicator.data_type === 'numeric') return value.numeric_value === null ? '' : numericText(value.numeric_value, indicator.decimal_places)
  if (indicator.data_type === 'date') return value.date_value || ''
  return value.text_value || ''
}

function numericText(value: string | number, decimalPlaces: number) {
  return new Intl.NumberFormat('id-ID', {
    useGrouping: false,
    maximumFractionDigits: decimalPlaces,
  }).format(Number(value))
}

async function submissionList(token: string, yearId: number, regionId?: number) {
  const query = new URLSearchParams({ reporting_year_id: String(yearId) })
  if (regionId) query.set('region_id', String(regionId))
  return request<ApiSubmissionListItem[]>(`/submissions?${query}`, {}, token)
}

export const api = {
  async login(email: string, password: string) {
    const payload = await request<{ token: string; user: { id: number; name: string; email: string; role: UserRole; region_id?: number | null; region?: Region | null } }>('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password, device_name: 'siprokkes-web' }),
    })
    return { token: payload.token, user: normalizeUser(payload.user) }
  },
  logout: (token: string) => request<void>('/logout', { method: 'POST' }, token),
  regions: (token: string) => request<Region[]>('/regions', {}, token),
  async dashboard(token: string, year: number): Promise<DashboardData> {
    const years = await request<ReportingYear[]>('/reporting-years', {}, token)
    const reportingYear = years.find((item) => item.year === year) || years[0]
    if (!reportingYear) throw new ApiError('Tahun Pelaporan belum tersedia.', 404)
    const payload = await request<{
      reporting_year: ReportingYear
      regions: Array<{ region: Region; total_tables: number; not_started: number; completed: number; verified: number }>
      reporting_tables: Array<{ id: number; code: string; name: string; position: number; status: ReportingStatus; completion: number }>
      recent_submissions: Array<{ id: number; region_id: number; name: string; position: number; status: ReportingStatus; region: string; updated_at: string }>
    }>(`/dashboard?reporting_year_id=${reportingYear.id}`, {}, token)
    return {
      year: payload.reporting_year.year,
      availableYears: years.map((item) => item.year),
      reportingTables: payload.reporting_tables.map((table) => ({
        id: String(table.id), version: 0, number: table.position, name: table.name, group: table.code,
        status: table.status, completion: table.completion, mappingStatus: 'ready',
      })),
      regions: payload.regions.map((item) => ({
        id: String(item.region.id), name: item.region.name, verified: item.verified,
        completed: item.completed, notStarted: item.not_started, total: item.total_tables,
      })),
      recentTables: payload.recent_submissions.map((table) => ({
        id: String(table.id), version: 0, number: table.position, name: table.name, group: table.region,
        regionId: String(table.region_id), status: table.status, completion: table.status === 'not_started' ? 0 : 100,
        updatedAt: table.updated_at, updatedBy: table.region, mappingStatus: 'ready',
      })),
    }
  },
  async reportingTables(token: string, year: number) {
    const years = await request<ReportingYear[]>('/reporting-years', {}, token)
    const reportingYear = years.find((item) => item.year === year) || years[0]
    if (!reportingYear) throw new ApiError('Tahun Pelaporan belum tersedia.', 404)
    return (await submissionList(token, reportingYear.id)).map(tableFromSubmission)
  },
  async reportingTable(token: string, id: string, year: number, regionId?: number): Promise<ReportingTableDetail> {
    const { reportingYear, regions } = await reportingContext(token, year)
    const item = (await submissionList(token, reportingYear.id, regionId))
      .find((candidate) => String(candidate.reporting_table.id) === id)
    if (!item) throw new ApiError('Tabel Pelaporan tidak ditemukan.', 404)
    if (item.region_id === null) throw new ApiError('Pilih Kabupaten/Kota untuk membuka rincian Tabel Pelaporan.', 422)
    const submission = item.id ? await request<ApiSubmission>(`/submissions/${item.id}`, {}, token) : null
    const table = submission?.reporting_table || item.reporting_table
    const values = new Map((submission?.values || []).map((value) => [value.indicator_id, value]))
    return {
      ...tableFromSubmission(item),
      submissionId: submission ? String(submission.id) : undefined,
      version: submission?.version ?? item.version,
      reportingTableId: String(table.id),
      regionId: String(item.region_id),
      description: table.description || 'Tabel Pelaporan Profil Kesehatan.',
      year: reportingYear.year,
      region: submission?.region.name || regions.find((region) => region.id === item.region_id)?.name || 'Kabupaten/Kota',
      rows: table.indicators.map((indicator) => {
        const value = values.get(indicator.id)
        const category = indicator.categories ? Object.values(indicator.categories).join(' • ') : (indicator.is_required ? 'Wajib' : 'Opsional')
        return {
          id: String(indicator.id), code: indicator.code, name: indicator.name,
          category, unit: indicator.unit || '—',
          metadata: indicator.categories || undefined,
          value: indicator.value_kind === 'derived'
            ? String(submission?.calculated_values?.[String(indicator.id)] ?? '')
            : valueText(value, indicator),
          kind: indicator.value_kind, dataType: indicator.data_type,
          required: indicator.is_required, notApplicable: value?.not_applicable || false,
          notApplicableReason: value?.not_applicable_reason || '',
        }
      }),
      headerCandidates: table.source_metadata?.header_candidates || [],
    }
  },
  async provinceTable(token: string, id: string, year: number): Promise<ReportingTableDetail | null> {
    const { reportingYear } = await reportingContext(token, year)
    const item = (await submissionList(token, reportingYear.id)).find((candidate) => String(candidate.reporting_table.id) === id)
    if (!item || !['T02', 'T03', 'T04'].includes(item.reporting_table.code)) return null
    const table = item.reporting_table
    const province = table.mapping_status === 'ready'
      ? await request<ApiProvince>(`/reporting-tables/${id}/province`, {}, token)
      : null
    return {
      ...tableFromSubmission(item),
      scope: 'province',
      regionId: '',
      reportingTableId: id,
      description: table.description || 'Rekap Provinsi Kepulauan Bangka Belitung.',
      year: reportingYear.year,
      region: 'Provinsi Kepulauan Bangka Belitung',
      completion: province?.base_count ? Math.round(province.complete_base_count / province.base_count * 100) : 0,
      rows: (province?.table.indicators || []).map((indicator) => ({
        id: String(indicator.id), code: indicator.code, name: indicator.name,
        category: indicator.categories ? Object.values(indicator.categories).join(' • ') : '',
        metadata: indicator.categories || undefined,
        unit: indicator.unit || '—',
        value: String((indicator.value_kind === 'base' ? province?.values[String(indicator.id)] : province?.calculated_values[String(indicator.id)]) ?? ''),
        kind: indicator.value_kind, dataType: indicator.data_type, required: indicator.is_required,
        notApplicable: false, notApplicableReason: '',
      })),
      headerCandidates: [],
    }
  },
  async tableOneWorksheet(token: string, reportingTableId: string): Promise<TableOneWorksheet> {
    const payload = await request<ApiWorksheet>(`/reporting-tables/${reportingTableId}/worksheet`, {}, token)
    const indicators = Object.fromEntries(payload.table.indicators.map((indicator) => [indicator.code, String(indicator.id)]))
    const indicatorsById = new Map(payload.table.indicators.map((indicator) => [String(indicator.id), indicator]))
    return {
      reportingTableId,
      year: payload.table.reporting_year.year,
      indicators,
      rows: payload.rows.map((row) => ({
        regionId: String(row.region.id),
        regionName: row.region.name,
        submissionId: row.submission_id ? String(row.submission_id) : undefined,
        status: row.status,
        version: row.version,
        editable: row.editable,
        values: Object.fromEntries([
          ...Object.entries(row.values),
          ...Object.entries(row.calculated_values),
        ].map(([id, value]) => {
          const indicator = indicatorsById.get(id)
          return [id, value === null ? '' : indicator?.data_type === 'numeric' ? numericText(value, indicator.decimal_places) : String(value)]
        })),
      })),
    }
  },
  async saveWorksheetCell(token: string, worksheet: TableOneWorksheet, row: WorksheetRow, indicatorId: string, value: string) {
    const submission = await request<ApiSubmission>('/submissions/draft', {
      method: 'POST',
      body: JSON.stringify({
        reporting_table_id: Number(worksheet.reportingTableId),
        version: row.version,
        values: [{
          indicator_id: Number(indicatorId),
          value: value || null,
          not_applicable: false,
          not_applicable_reason: null,
        }],
      }),
    }, token)
    return {
      ...worksheet,
      rows: worksheet.rows.map((item) => item.regionId === row.regionId ? {
        ...item,
        submissionId: String(submission.id),
        status: submission.status,
        version: submission.version,
        values: {
          ...item.values,
          ...Object.fromEntries(submission.values.map((saved) => [
            String(saved.indicator_id),
            valueText(saved, saved.indicator),
          ])),
          ...Object.fromEntries(Object.entries(submission.calculated_values || {}).map(([id, calculated]) => [
            id,
            calculated === null ? '' : String(calculated),
          ])),
        },
      } : item),
    }
  },
  async saveTable(token: string, detail: ReportingTableDetail) {
    const submission = await request<ApiSubmission>('/submissions/draft', {
      method: 'POST',
      body: JSON.stringify({
        reporting_table_id: Number(detail.reportingTableId),
        version: detail.version,
        values: detail.rows.filter((row) => row.kind === 'base').map((row) => ({
          indicator_id: Number(row.id), value: ['T01', 'T02', 'T03', 'T04'].includes(detail.group) && row.notApplicable && !row.value.trim() ? 0 : row.value || null,
          not_applicable: ['T01', 'T02', 'T03', 'T04'].includes(detail.group) ? false : row.notApplicable,
          not_applicable_reason: ['T01', 'T02', 'T03', 'T04'].includes(detail.group) ? null : row.notApplicable ? row.notApplicableReason : null,
        })),
      }),
    }, token)
    const values = new Map(submission.values.map((value) => [String(value.indicator_id), value]))
    return {
      ...detail,
      submissionId: String(submission.id),
      status: submission.status,
      version: submission.version,
      rows: detail.rows.map((row) => {
        if (row.kind === 'derived') return { ...row, value: String(submission.calculated_values?.[row.id] ?? '') }
        const value = values.get(row.id)
        return value ? {
          ...row,
          value: valueText(value, value.indicator),
          notApplicable: value.not_applicable,
          notApplicableReason: value.not_applicable_reason || '',
        } : row
      }),
    }
  },
  async setTableStatus(token: string, detail: ReportingTableDetail, action: 'complete' | 'reopen' | 'verify' | 'unverify', reason?: string) {
    if (!detail.submissionId) throw new ApiError('Simpan draft sebelum mengubah status.', 422)
    await request(`/submissions/${detail.submissionId}/${action}`, {
      method: 'POST', body: JSON.stringify({ version: detail.version, ...(reason ? { reason } : {}) }),
    }, token)
    return api.reportingTable(token, detail.reportingTableId, detail.year, Number(detail.regionId))
  },
  async users(token: string): Promise<ManagedUser[]> {
    const users = await request<Array<{ id: number; name: string; email: string; role: UserRole; region?: Region | null }>>('/users', {}, token)
    return users.map((user) => ({ ...normalizeUser(user), active: true }))
  },
  async catalog(token: string, year: number): Promise<CatalogIndicator[]> {
    const { reportingYear } = await reportingContext(token, year)
    const tables = await request<ApiTable[]>(`/reporting-years/${reportingYear.id}/tables`, {}, token)
    return tables.flatMap((table) => table.indicators.map((indicator) => ({
      id: String(indicator.id), code: indicator.code, name: indicator.name,
      unit: indicator.unit || '—', tableName: `${table.code} — ${table.name}`,
    })))
  },
  async mapIndicators(token: string, detail: ReportingTableDetail, selected: string[]) {
    await request(`/reporting-tables/${detail.reportingTableId}/indicator-mapping`, {
      method: 'PUT',
      body: JSON.stringify({
        indicators: selected.map((name, index) => ({
          code: `${detail.group}_${String(index + 1).padStart(2, '0')}`.replace(/[^A-Z0-9_]/gi, '_').toUpperCase(),
          name,
          data_type: 'numeric',
          unit: null,
          is_required: true,
        })),
      }),
    }, token)
    return api.reportingTable(token, detail.reportingTableId, detail.year, Number(detail.regionId))
  },
  async catalogImport(token: string, year: number) {
    const { reportingYear } = await reportingContext(token, year)
    try {
      return await request<{
        filename: string
        imported_at: string
        report: {
          workbook_sheets: number
          reporting_tables: number
          mapping_ready: number
          mapping_pending: number
          warnings: string[]
        }
      }>(`/reporting-years/${reportingYear.id}/catalog-import`, {}, token)
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) return null
      throw error
    }
  },
}
