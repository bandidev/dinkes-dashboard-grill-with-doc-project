import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth'
import { AppShell } from './components/app-shell'
import { CatalogPage } from './pages/catalog'
import { DashboardPage } from './pages/dashboard'
import { LoginPage } from './pages/login'
import { ReportingDetailPage } from './pages/reporting-detail'
import { ReportingTablesPage } from './pages/reporting-tables'
import { UsersPage } from './pages/users'

function ProtectedApp() {
  const { session } = useAuth()
  return session ? <AppShell /> : <Navigate to="/login" replace />
}

export function App() {
  const { session } = useAuth()
  return (
    <Routes>
      <Route path="/login" element={session ? <Navigate to="/dashboard" replace /> : <LoginPage />} />
      <Route element={<ProtectedApp />}>
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/reporting-tables" element={<ReportingTablesPage />} />
        <Route path="/reporting-tables/:id" element={<ReportingDetailPage />} />
        <Route path="/catalog" element={<CatalogPage />} />
        <Route path="/users" element={<UsersPage />} />
      </Route>
      <Route path="*" element={<Navigate to={session ? '/dashboard' : '/login'} replace />} />
    </Routes>
  )
}
