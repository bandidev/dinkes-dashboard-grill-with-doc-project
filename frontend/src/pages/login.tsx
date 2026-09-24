import { Archive, ArrowRight, LockKeyhole } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useAuth } from '../auth'
import { Button } from '../components/ui/button'
import { Input } from '../components/ui/field'

export function LoginPage() {
  const { login, previewLogin } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      await login(email, password)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Gagal masuk.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="grid min-h-screen lg:grid-cols-[minmax(0,1.1fr)_minmax(420px,0.9fr)]">
      <section className="relative hidden min-h-screen border-r border-line bg-archive text-paper-raised lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div className="flex items-center gap-3">
          <div className="grid size-9 place-items-center rounded-[3px] border border-paper-raised/50 text-xs font-black">PK</div>
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.14em]">Dinas Kesehatan Provinsi</p>
            <p className="text-xs text-paper-raised/70">Pengelolaan Profil Kesehatan</p>
          </div>
        </div>
        <div className="max-w-xl">
          <Archive className="mb-6 size-8 text-pending" aria-hidden="true" />
          <p className="mb-3 font-mono text-xs uppercase tracking-[0.16em] text-paper-raised/65">Meja Operasional • Tahun Pelaporan</p>
          <h1 className="max-w-lg text-4xl font-semibold leading-[1.12] tracking-[-0.035em]">Satu sumber resmi untuk seluruh Tabel Pelaporan kesehatan daerah.</h1>
          <p className="mt-5 max-w-lg text-base leading-7 text-paper-raised/75">Pantau kelengkapan Kabupaten/Kota, periksa Nilai Indikator, dan jaga Riwayat Revisi dalam satu alur kerja provinsi.</p>
        </div>
        <p className="font-mono text-[11px] uppercase tracking-[0.1em] text-paper-raised/55">Akses internal • Data resmi pemerintah daerah</p>
      </section>
      <section className="flex min-h-screen items-center justify-center p-5 sm:p-8">
        <div className="w-full max-w-[420px]">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <div className="grid size-9 place-items-center rounded-[3px] bg-archive text-xs font-black text-paper-raised">PK</div>
            <div><p className="text-xs font-bold uppercase tracking-[0.12em]">Dinas Kesehatan</p><p className="text-[11px] text-ink-muted">Profil Kesehatan</p></div>
          </div>
          <p className="font-mono text-[11px] uppercase tracking-[0.14em] text-archive">Portal Terautentikasi</p>
          <h2 className="mt-2 text-2xl font-bold tracking-[-0.025em]">Masuk ke meja operasional</h2>
          <p className="mt-2 text-sm text-ink-muted">Gunakan akun Administrator Sistem atau Operator Kabupaten/Kota.</p>
          <form className="mt-7 flex flex-col gap-4" onSubmit={handleSubmit}>
            <label className="flex flex-col gap-1.5 text-xs font-semibold" htmlFor="email">Alamat email<Input id="email" type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} required placeholder="nama@dinkes.go.id" /></label>
            <label className="flex flex-col gap-1.5 text-xs font-semibold" htmlFor="password">Kata sandi<Input id="password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required placeholder="Masukkan kata sandi" /></label>
            {error ? <p className="rounded-[3px] border border-correction/40 bg-correction-soft px-3 py-2 text-xs text-correction" role="alert">{error}</p> : null}
            <Button type="submit" className="mt-1 w-full" disabled={submitting}>{submitting ? 'Memeriksa…' : 'Masuk'}<ArrowRight data-icon="inline-end" /></Button>
          </form>
          <div className="my-6 flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.12em] text-ink-faint"><span className="h-px flex-1 bg-line" />Pratinjau MVP<span className="h-px flex-1 bg-line" /></div>
          <div className="grid gap-2 sm:grid-cols-2">
            <Button variant="outline" size="sm" onClick={() => previewLogin('administrator')}>Administrator</Button>
            <Button variant="outline" size="sm" onClick={() => previewLogin('operator')}>Operator Kab/Kota</Button>
          </div>
          <p className="mt-5 flex items-start gap-2 text-[11px] leading-5 text-ink-faint"><LockKeyhole className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />Token sesi hanya disimpan di sessionStorage dan dihapus saat tab ditutup.</p>
        </div>
      </section>
    </main>
  )
}
