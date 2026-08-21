import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import LoginPage from '@/pages/LoginPage'
import DashboardPage from '@/pages/DashboardPage'
import ProtectedRoute from '@/components/ProtectedRoute'
import AppLayout from '@/components/AppLayout'
import CompanyPage from '@/pages/master/CompanyPage'
import CustomerPage from '@/pages/master/CustomerPage'
import ProjectPage from '@/pages/master/ProjectPage'
import ClusterPage from '@/pages/master/ClusterPage'
import BlockPage from '@/pages/master/BlockPage'
import LotPage from '@/pages/master/LotPage'
import IplRatePage from '@/pages/master/IplRatePage'
import WaterRateGroupPage from '@/pages/master/WaterRateGroupPage'
import TaxConfigurationPage from '@/pages/master/TaxConfigurationPage'
import SignaturePage from '@/pages/master/SignaturePage'
import OwnershipPage from '@/pages/ownership/OwnershipPage'
import MeterReadingPage from '@/pages/meter/MeterReadingPage'
import BillingPage from '@/pages/billing/BillingPage'
import InvoicePage from '@/pages/invoice/InvoicePage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
    },
  },
})

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          {/* Base route redirect */}
          <Route path="/" element={<Navigate to="/dashboard" replace />} />

          {/* Public */}
          <Route path="/login" element={<LoginPage />} />

          {/* Protected */}
          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/dashboard" element={<DashboardPage />} />

              {/* Phase 2 — Master Data */}
              <Route path="/master/company"     element={<CompanyPage />} />
              <Route path="/master/customers"   element={<CustomerPage />} />
              <Route path="/master/projects"    element={<ProjectPage />} />
              <Route path="/master/clusters"    element={<ClusterPage />} />
              <Route path="/master/blocks"      element={<BlockPage />} />
              <Route path="/master/lots"        element={<LotPage />} />
              <Route path="/master/ipl-rates"   element={<IplRatePage />} />
              <Route path="/master/water-rates" element={<WaterRateGroupPage />} />
              <Route path="/master/tax"         element={<TaxConfigurationPage />} />
              <Route path="/master/signatures"  element={<SignaturePage />} />

              {/* Phase 3 — Ownership */}
              <Route path="/ownership" element={<OwnershipPage />} />

              {/* Phase 4 — Meter Reading */}
              <Route path="/meter" element={<MeterReadingPage />} />

              {/* Phase 5A — Billing Foundation */}
              <Route path="/billing" element={<BillingPage />} />

              {/* Phase 6/7 — Invoice Generation */}
              <Route path="/invoice" element={<InvoicePage />} />
            </Route>
          </Route>

          {/* Default redirect */}
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
