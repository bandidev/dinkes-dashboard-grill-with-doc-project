import { Search } from 'lucide-react'
import { useDeferredValue, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'
import { PageHeading } from '../components/page-heading'
import { EmptyState, ErrorState, LoadingState } from '../components/states'
import { Badge } from '../components/ui/badge'
import { Input } from '../components/ui/field'
import { Panel } from '../components/ui/panel'
import { demoUsers } from '../demo-data'
import { useApiData } from '../hooks/use-api-data'

export function UsersPage() {
  const { session } = useAuth()
  const [query, setQuery] = useState('')
  const deferredQuery = useDeferredValue(query.toLowerCase())
  const preview = session?.token.startsWith('preview-') ? demoUsers : undefined
  const { data, error, loading } = useApiData(() => api.users(session!.token), [session?.token], preview)

  if (session?.user.role !== 'administrator') return <Navigate to="/dashboard" replace />
  if (loading) return <LoadingState label="Memuat pengguna…" />
  if (error) return <ErrorState message={error} />
  if (!data?.length) return <><PageHeading eyebrow="Administrasi" title="Pengguna" description="Kelola akses Administrator Sistem dan Operator Kabupaten/Kota." /><EmptyState title="Belum ada pengguna" description="Endpoint pengguna tersedia, tetapi belum mengembalikan data." /></>

  const filtered = data.filter((user) => `${user.name} ${user.email} ${user.region || ''}`.toLowerCase().includes(deferredQuery))
  return (
    <><PageHeading eyebrow="Administrasi" title="Pengguna" description="Daftar akses Administrator Sistem dan Operator Kabupaten/Kota dari layanan API." /><Panel className="overflow-hidden"><div className="border-b border-line p-4"><label className="relative block max-w-md"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" /><Input className="pl-9" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari nama, email, atau Kabupaten/Kota…" aria-label="Cari pengguna" /></label></div><div className="overflow-x-auto"><table className="w-full min-w-[680px] text-left text-xs"><thead className="bg-paper-inset text-[10px] uppercase tracking-[0.08em] text-ink-muted"><tr><th className="px-4 py-2.5">Nama</th><th className="px-3 py-2.5">Peran</th><th className="px-3 py-2.5">Kabupaten/Kota</th><th className="px-4 py-2.5">Status</th></tr></thead><tbody>{filtered.map((user) => <tr key={user.id} className="border-t border-line-soft"><td className="px-4 py-3"><p className="font-semibold">{user.name}</p><p className="text-[11px] text-ink-muted">{user.email}</p></td><td className="px-3 py-3">{user.role === 'administrator' ? 'Administrator Sistem' : 'Operator Kabupaten/Kota'}</td><td className="px-3 py-3 text-ink-muted">{user.region || 'Tingkat Provinsi'}</td><td className="px-4 py-3"><Badge tone={user.active ? 'verified' : 'neutral'}>{user.active ? 'Aktif' : 'Nonaktif'}</Badge></td></tr>)}</tbody></table></div></Panel></>
  )
}
