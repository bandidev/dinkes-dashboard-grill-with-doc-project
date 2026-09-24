import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
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

const router = createBrowserRouter([
  { path: '/login', element: <LoginRoute /> },
  {
    element: <ProtectedApp />,
    children: [
      { path: '/dashboard', element: <DashboardPage /> },
      { path: '/reporting-tables', element: <ReportingTablesPage /> },
      { path: '/reporting-tables/:id', element: <ReportingDetailPage /> },
      { path: '/catalog', element: <CatalogPage /> },
      { path: '/users', element: <UsersPage /> },
    ],
  },
  { path: '*', element: <FallbackRoute /> },
])

function LoginRoute() {
  const { session } = useAuth()
  return session ? <Navigate to="/dashboard" replace /> : <LoginPage />
}

function FallbackRoute() {
  const { session } = useAuth()
  return <Navigate to={session ? '/dashboard' : '/login'} replace />
}

export function App() {
  return <RouterProvider router={router} />
}
