import { createContext, useContext, useState, type ReactNode } from 'react'
import { api, type SessionUser, type UserRole } from './api'

type AuthState = { token: string; user: SessionUser }

type AuthContextValue = {
  session: AuthState | null
  login: (email: string, password: string) => Promise<void>
  previewLogin: (role: UserRole) => void
  logout: () => void
}

const STORAGE_KEY = 'profil-kesehatan-session'
const AuthContext = createContext<AuthContextValue | null>(null)

function readSession(): AuthState | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) as AuthState : null
  } catch {
    return null
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<AuthState | null>(readSession)

  function store(next: AuthState | null) {
    setSession(next)
    if (next) sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    else sessionStorage.removeItem(STORAGE_KEY)
  }

  async function login(email: string, password: string) {
    const next = await api.login(email, password)
    if (!next.token) throw new Error('API tidak mengembalikan token autentikasi.')
    store(next)
  }

  function previewLogin(role: UserRole) {
    store({
      token: `preview-${role}`,
      user: {
        id: `preview-${role}`,
        name: role === 'administrator' ? 'Administrator Sistem' : 'Operator Kabupaten Bangka',
        email: role === 'administrator' ? 'admin@example.com' : 'operator.bangka@example.com',
        role,
        region: role === 'operator' ? 'Kabupaten Bangka' : undefined,
      },
    })
  }

  function logout() {
    if (session && !session.token.startsWith('preview-')) void api.logout(session.token)
    store(null)
  }

  return <AuthContext.Provider value={{ session, login, previewLogin, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth harus digunakan di dalam AuthProvider.')
  return value
}
