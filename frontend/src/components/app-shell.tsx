import { BookOpenText, ChevronDown, ClipboardList, LayoutDashboard, LogOut, Menu, Users, X } from 'lucide-react'
import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth'
import { cn } from '../lib/cn'
import { Button } from './ui/button'

const navigation = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/reporting-tables', label: 'Tabel Pelaporan', icon: ClipboardList },
  { to: '/catalog', label: 'Katalog Indikator', icon: BookOpenText },
  { to: '/users', label: 'Pengguna', icon: Users, administratorOnly: true },
]

const pageTitles: Record<string, string> = {
  '/dashboard': 'Dashboard Internal',
  '/reporting-tables': 'Tabel Pelaporan',
  '/catalog': 'Katalog Indikator',
  '/users': 'Pengguna',
}

export function AppShell() {
  const { session, logout } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)
  const location = useLocation()
  const pageTitle = location.pathname.startsWith('/reporting-tables/') ? 'Rincian Tabel Pelaporan' : pageTitles[location.pathname] || 'Profil Kesehatan'
  const navItems = navigation.filter((item) => !item.administratorOnly || session?.user.role === 'administrator')

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[232px_minmax(0,1fr)]">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-paper-raised focus:px-3 focus:py-2">Lewati ke konten</a>
      {menuOpen ? <button className="fixed inset-0 z-30 bg-ink/25 lg:hidden" aria-label="Tutup navigasi" onClick={() => setMenuOpen(false)} /> : null}
      <aside className={cn('fixed inset-y-0 left-0 z-40 flex w-[232px] -translate-x-full flex-col border-r border-line bg-paper transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0', menuOpen && 'translate-x-0')}>
        <div className="flex h-16 items-center justify-between border-b border-line px-4">
          <div className="flex items-center gap-3">
            <div className="grid size-8 place-items-center rounded-[3px] border border-archive bg-archive text-xs font-black tracking-tight text-paper-raised">PK</div>
            <div className="leading-tight">
              <p className="text-xs font-bold uppercase tracking-[0.1em]">Dinas Kesehatan</p>
              <p className="text-[11px] text-ink-muted">Profil Kesehatan</p>
            </div>
          </div>
          <button className="p-1 text-ink-muted lg:hidden" onClick={() => setMenuOpen(false)} aria-label="Tutup navigasi"><X className="size-5" /></button>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3" aria-label="Navigasi utama">
          <p className="px-2 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-ink-faint">Operasional</p>
          {navItems.map(({ to, label, icon: Icon }) => (
            <NavLink key={to} to={to} onClick={() => setMenuOpen(false)} className={({ isActive }) => cn('flex h-9 items-center gap-3 rounded-[3px] border border-transparent px-2 text-sm font-medium text-ink-muted hover:bg-paper-inset hover:text-ink', isActive && 'border-line bg-paper-raised text-archive')}>
              <Icon className="size-4" aria-hidden="true" />
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="border-t border-line p-3">
          <div className="mb-3 px-2">
            <p className="truncate text-xs font-semibold">{session?.user.name}</p>
            <p className="truncate text-[11px] text-ink-muted">{session?.user.region || 'Tingkat Provinsi'}</p>
          </div>
          <Button variant="ghost" size="sm" className="w-full justify-start" onClick={logout}><LogOut data-icon="inline-start" />Keluar</Button>
        </div>
      </aside>
      <div className="min-w-0">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-line bg-paper/95 px-4 backdrop-blur-sm sm:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <button className="p-1 text-ink-muted lg:hidden" onClick={() => setMenuOpen(true)} aria-label="Buka navigasi"><Menu className="size-5" /></button>
            <div className="min-w-0">
              <p className="truncate text-sm font-bold">{pageTitle}</p>
              <p className="hidden text-[11px] text-ink-muted sm:block">Sistem Pengelolaan Profil Kesehatan Provinsi</p>
            </div>
          </div>
          <button className="flex min-w-0 items-center gap-2 rounded-[3px] border border-line bg-paper-raised px-3 py-2 text-left">
            <span className="grid size-6 shrink-0 place-items-center rounded-[2px] bg-archive-soft text-[10px] font-bold text-archive">{session?.user.name.split(' ').map((part) => part[0]).slice(0, 2).join('')}</span>
            <span className="hidden max-w-40 truncate text-xs font-semibold sm:block">{session?.user.name}</span>
            <ChevronDown className="size-3.5 text-ink-faint" aria-hidden="true" />
          </button>
        </header>
        <main id="main-content" className="mx-auto w-full max-w-[1480px] p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
