import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts'
import { useAuthStore } from '@/stores/authStore'
import { dashboardApi, type DashboardSummary } from '@/api/dashboardApi'
import { downloadInvoicesExcel } from '@/api/reportApi'
import Spinner from '@/components/ui/Spinner'

// ─── Quick Links ──────────────────────────────────────────────────────────────

interface QuickLink {
  title: string
  desc: string
  to: string
  icon: string
  accent: string
}

const quickLinks: QuickLink[] = [
  { title: 'Invoice',       desc: 'Generate & monitor invoice dengan kalkulasi pajak DPP Nilai Lain', to: '/invoice',            icon: '🧾', accent: '#2d5a3d' },
  { title: 'Item Tagihan',  desc: 'Kelola tagihan IPL, Air, Listrik & Lainnya sebelum invoicing',     to: '/billing',            icon: '📋', accent: '#1d4ed8' },
  { title: 'Kepemilikan',   desc: 'Data kepemilikan kavling & customer aktif',                        to: '/ownership',          icon: '🏠', accent: '#7c3aed' },
  { title: 'Meteran Air',   desc: 'Catat angka meter & upload foto verifikasi',                       to: '/meter',              icon: '💧', accent: '#0369a1' },
  { title: 'Tarif IPL',     desc: 'Tarif IPL per m² per cluster',                                    to: '/master/ipl-rates',   icon: '💰', accent: '#b45309' },
  { title: 'Tarif Air',     desc: 'Tarif air bertingkat (progresif) + abonemen',                     to: '/master/water-rates', icon: '📊', accent: '#0f766e' },
]

// ─── Helpers ──────────────────────────────────────────────────────────────────

const formatRupiah = (val: number): string =>
  'Rp ' + Math.round(val).toLocaleString('id-ID')

const formatCompact = (val: number): string => {
  if (val >= 1_000_000_000) return (val / 1_000_000_000).toFixed(1) + 'M'
  if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + 'jt'
  if (val >= 1_000) return (val / 1_000).toFixed(0) + 'rb'
  return String(val)
}

// ─── Sub-components ───────────────────────────────────────────────────────────

interface StatCardProps {
  label: string
  value: string | number
  sub?: string
  accent?: string
  icon?: string
  loading?: boolean
}

function StatCard({ label, value, sub, accent = '#2d5a3d', icon, loading }: StatCardProps) {
  return (
    <div style={s.statCard}>
      <div style={s.statHeader}>
        <span style={s.statLabel}>{label}</span>
        {icon && <span style={s.statIcon}>{icon}</span>}
      </div>
      {loading ? (
        <div style={s.statLoading}><Spinner /></div>
      ) : (
        <div style={{ ...s.statValue, color: accent }}>{value}</div>
      )}
      {sub && <div style={s.statSub}>{sub}</div>}
      <div style={{ ...s.statAccentBar, backgroundColor: accent }} />
    </div>
  )
}

// Custom Recharts Tooltip
function CustomChartTooltip({ active, payload, label }: any) {
  if (active && payload && payload.length) {
    return (
      <div style={s.chartTooltip}>
        <div style={s.chartTooltipHeader}>{label}</div>
        {payload.map((p: any, i: number) => (
          <div key={i} style={s.chartTooltipRow}>
            <span style={{ ...s.chartTooltipDot, backgroundColor: p.color }} />
            <span style={s.chartTooltipLabel}>{p.name}:</span>
            <span style={s.chartTooltipValue}>{formatRupiah(p.value)}</span>
          </div>
        ))}
      </div>
    )
  }
  return null
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user)
  const [exporting, setExporting] = useState(false)

  const { data: summaryRes, isLoading } = useQuery<DashboardSummary>({
    queryKey: ['dashboard-summary'],
    queryFn: async () => {
      const res = await dashboardApi.getSummary()
      return res.data.data
    },
    refetchInterval: 60_000, // auto-refresh every minute
  })

  const inv    = summaryRes?.invoices
  const bi     = summaryRes?.billing_items
  const wa     = summaryRes?.whatsapp
  const m      = summaryRes?.master
  const trends = summaryRes?.monthly_trends ?? []

  const handleExportExcel = async () => {
    setExporting(true)
    try {
      await downloadInvoicesExcel()
    } catch {
      alert('Gagal mengunduh file Excel.')
    } finally {
      setExporting(false)
    }
  }

  return (
    <div style={s.container}>

      {/* ── Welcome Hero ─────────────────────────────────────────────── */}
      <div style={s.hero}>
        <div style={s.heroLeft}>
          <h1 style={s.heroTitle}>
            Selamat datang, {user?.name ?? 'Administrator'} 👋
          </h1>
          <p style={s.heroSub}>
            Ringkasan operasional sistem IPU Billing &amp; Invoice Management
          </p>
        </div>
        <div style={s.heroRight}>
          <div style={s.heroBadgeRow}>
            {user && (
              <span style={s.roleBadge}>
                <span style={s.roleDot} />
                {user.role}
              </span>
            )}
            <span style={s.systemBadge}>Demo-Ready MVP</span>
          </div>
          <button
            id="btn-export-excel-dashboard"
            style={s.exportBtn}
            onClick={handleExportExcel}
            disabled={exporting}
            title="Download Rekapitulasi Tagihan Excel"
          >
            {exporting ? '⏳ Mengunduh...' : '📊 Export ke Excel'}
          </button>
        </div>
      </div>

      {/* ── Section 1: Analytics Chart (Grafik Pendapatan) ─────────────── */}
      <section style={s.section}>
        <div style={s.sectionHeader}>
          <div>
            <h2 style={s.sectionTitle}>📈 Grafik Tren Pendapatan &amp; Tagihan</h2>
            <p style={s.sectionSub}>Perbandingan nominal invoice Lunas vs Outstanding per periode</p>
          </div>
          {summaryRes && (
            <span style={s.refreshNote}>
              Diperbarui: {new Date(summaryRes.generated_at).toLocaleTimeString('id-ID')}
            </span>
          )}
        </div>

        <div style={s.chartCard}>
          {isLoading ? (
            <div style={{ height: 280, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Spinner />
            </div>
          ) : trends.length > 0 ? (
            <div style={{ width: '100%', height: 280 }}>
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={trends} margin={{ top: 10, right: 20, left: 10, bottom: 0 }}>
                  <defs>
                    <linearGradient id="colorPaid" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#059669" stopOpacity={0.8} />
                      <stop offset="95%" stopColor="#059669" stopOpacity={0.05} />
                    </linearGradient>
                    <linearGradient id="colorOutstanding" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#d97706" stopOpacity={0.8} />
                      <stop offset="95%" stopColor="#d97706" stopOpacity={0.05} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" vertical={false} />
                  <XAxis
                    dataKey="label"
                    stroke="#9ca3af"
                    fontSize={12}
                    tickLine={false}
                    axisLine={{ stroke: '#e5e7eb' }}
                  />
                  <YAxis
                    stroke="#9ca3af"
                    fontSize={12}
                    tickFormatter={formatCompact}
                    tickLine={false}
                    axisLine={false}
                  />
                  <Tooltip content={<CustomChartTooltip />} />
                  <Legend
                    verticalAlign="top"
                    align="right"
                    iconType="circle"
                    wrapperStyle={{ paddingBottom: 15, fontSize: '0.8rem' }}
                  />
                  <Area
                    type="monotone"
                    dataKey="paid_revenue"
                    name="Pendapatan Lunas"
                    stroke="#059669"
                    strokeWidth={2.5}
                    fillOpacity={1}
                    fill="url(#colorPaid)"
                  />
                  <Area
                    type="monotone"
                    dataKey="outstanding_revenue"
                    name="Outstanding (Belum Lunas)"
                    stroke="#d97706"
                    strokeWidth={2.5}
                    fillOpacity={1}
                    fill="url(#colorOutstanding)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          ) : (
            <div style={s.emptyChart}>Belum ada data historis invoice untuk ditampilkan.</div>
          )}
        </div>
      </section>

      {/* ── Section 2: Invoice Metrics ───────────────────────────────── */}
      <section style={s.section}>
        <div style={s.sectionHeader}>
          <h2 style={s.sectionTitle}>📑 Ringkasan Invoice</h2>
        </div>
        <div style={s.statGrid4}>
          <StatCard
            label="Total Invoice Diterbitkan"
            value={inv ? inv.total : '—'}
            sub="Semua status aktif"
            accent="#2d5a3d"
            icon="🧾"
            loading={isLoading}
          />
          <StatCard
            label="Outstanding (Belum Lunas)"
            value={inv ? formatRupiah(inv.outstanding_amount) : '—'}
            sub={inv ? `${inv.draft + inv.sent + inv.overdue} invoice belum dibayar` : undefined}
            accent="#d97706"
            icon="⏳"
            loading={isLoading}
          />
          <StatCard
            label="Total Sudah Lunas"
            value={inv ? formatRupiah(inv.total_paid) : '—'}
            sub={inv ? `${inv.paid} invoice lunas` : undefined}
            accent="#059669"
            icon="✅"
            loading={isLoading}
          />
          <StatCard
            label="Jatuh Tempo / Overdue"
            value={inv ? inv.overdue : '—'}
            sub="Invoice melewati due date"
            accent={inv && inv.overdue > 0 ? '#dc2626' : '#6b7280'}
            icon="🔴"
            loading={isLoading}
          />
        </div>

        {/* Invoice Status Breakdown */}
        {!isLoading && inv && inv.total > 0 && (
          <div style={s.statusBar}>
            {[
              { key: 'draft',   label: 'Draft',    count: inv.draft,   color: '#93c5fd' },
              { key: 'sent',    label: 'Terkirim', count: inv.sent,    color: '#a78bfa' },
              { key: 'paid',    label: 'Lunas',    count: inv.paid,    color: '#6ee7b7' },
              { key: 'overdue', label: 'Overdue',  count: inv.overdue, color: '#fca5a5' },
            ].map(({ key, label, count, color }) => (
              <div key={key} style={s.statusPill}>
                <span style={{ ...s.statusDot, backgroundColor: color }} />
                <span style={s.statusLabel}>{label}</span>
                <span style={s.statusCount}>{count}</span>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* ── Section 3: Billing & Operations ─────────────────────────── */}
      <section style={s.section}>
        <h2 style={s.sectionTitle}>📋 Item Tagihan &amp; Operasional</h2>
        <div style={s.statGrid3}>
          <StatCard
            label="Item Tagihan Menunggu Invoice"
            value={bi ? bi.draft_count : '—'}
            sub={bi ? `Estimasi: ${formatRupiah(bi.draft_subtotal)}` : undefined}
            accent="#d97706"
            icon="📝"
            loading={isLoading}
          />
          <StatCard
            label="Item Sudah Diinvoice"
            value={bi ? bi.invoiced_count : '—'}
            sub="Status berhasil diproses"
            accent="#059669"
            icon="📦"
            loading={isLoading}
          />
          <StatCard
            label="Pesan WhatsApp Terkirim"
            value={wa ? wa.total_sent : '—'}
            sub="Total notifikasi (simulasi)"
            accent="#16a34a"
            icon="💬"
            loading={isLoading}
          />
        </div>
      </section>

      {/* ── Section 4: Master Data Snapshot ─────────────────────────── */}
      <section style={s.section}>
        <h2 style={s.sectionTitle}>🗂 Data Master</h2>
        <div style={s.statGrid3}>
          <StatCard
            label="Customer Aktif"
            value={m ? m.customers : '—'}
            sub="Pemilik kavling terdaftar"
            accent="#6d28d9"
            icon="👥"
            loading={isLoading}
          />
          <StatCard
            label="Kepemilikan (Unit)"
            value={m ? m.ownerships : '—'}
            sub="Residential & komersial"
            accent="#0369a1"
            icon="🏠"
            loading={isLoading}
          />
          <StatCard
            label="Proyek"
            value={m ? m.projects : '—'}
            sub="Proyek aktif terdaftar"
            accent="#0f766e"
            icon="🏗"
            loading={isLoading}
          />
        </div>
      </section>

      {/* ── Section 5: Quick Access ──────────────────────────────────── */}
      <section style={s.section}>
        <h2 style={s.sectionTitle}>⚡ Akses Cepat Modul</h2>
        <div style={s.quickGrid}>
          {quickLinks.map((item) => (
            <Link key={item.to} to={item.to} style={s.quickCard}>
              <div style={{ ...s.quickIconWrap, backgroundColor: item.accent + '15', border: `1px solid ${item.accent}30` }}>
                <span style={s.quickIcon}>{item.icon}</span>
              </div>
              <div style={s.quickBody}>
                <div style={{ ...s.quickTitle, color: item.accent }}>{item.title}</div>
                <div style={s.quickDesc}>{item.desc}</div>
              </div>
              <span style={s.quickArrow}>→</span>
            </Link>
          ))}
        </div>
      </section>

    </div>
  )
}

// ─── Styles ───────────────────────────────────────────────────────────────────

const s: Record<string, React.CSSProperties> = {
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.75rem',
    padding: '1.5rem 2rem',
    maxWidth: '1280px',
  },
  hero: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    background: 'linear-gradient(135deg, #1b4332 0%, #2d5a3d 60%, #3d7a52 100%)',
    borderRadius: '12px',
    padding: '1.5rem 2rem',
    color: '#ffffff',
    boxShadow: '0 4px 16px rgba(45, 90, 61, 0.2)',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  heroLeft: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  heroRight: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: '0.625rem',
  },
  heroTitle: {
    fontSize: '1.5rem',
    fontWeight: 700,
    margin: 0,
    letterSpacing: '-0.02em',
  },
  heroSub: {
    fontSize: '0.875rem',
    color: '#a7f3d0',
    margin: 0,
  },
  heroBadgeRow: {
    display: 'flex',
    gap: '0.5rem',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  exportBtn: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    background: '#ffffff',
    color: '#1b4332',
    border: 'none',
    borderRadius: '6px',
    padding: '0.5rem 0.875rem',
    fontSize: '0.8125rem',
    fontWeight: 700,
    cursor: 'pointer',
    boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
    transition: 'transform 0.1s, background 0.15s',
  },
  roleBadge: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    background: 'rgba(255, 255, 255, 0.15)',
    border: '1px solid rgba(255, 255, 255, 0.25)',
    borderRadius: '9999px',
    padding: '0.25rem 0.75rem',
    fontSize: '0.75rem',
    fontWeight: 600,
    textTransform: 'capitalize',
  },
  roleDot: {
    width: '6px',
    height: '6px',
    borderRadius: '50%',
    backgroundColor: '#34d399',
  },
  systemBadge: {
    background: 'rgba(255, 255, 255, 0.1)',
    borderRadius: '9999px',
    padding: '0.25rem 0.625rem',
    fontSize: '0.75rem',
    color: '#d1fae5',
  },
  section: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.875rem',
  },
  sectionHeader: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    flexWrap: 'wrap',
    gap: '0.5rem',
  },
  sectionTitle: {
    fontSize: '1.0625rem',
    fontWeight: 700,
    color: '#111827',
    margin: 0,
    letterSpacing: '-0.01em',
  },
  sectionSub: {
    fontSize: '0.8125rem',
    color: '#6b7280',
    margin: '0.125rem 0 0',
  },
  refreshNote: {
    fontSize: '0.75rem',
    color: '#9ca3af',
  },
  chartCard: {
    background: '#ffffff',
    borderRadius: '10px',
    border: '1px solid #e5e7eb',
    padding: '1.25rem 1rem',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.05)',
  },
  chartTooltip: {
    background: '#1f2937',
    color: '#ffffff',
    borderRadius: '6px',
    padding: '0.625rem 0.875rem',
    fontSize: '0.75rem',
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.2)',
  },
  chartTooltipHeader: {
    fontWeight: 700,
    marginBottom: '0.375rem',
    borderBottom: '1px solid #374151',
    paddingBottom: '0.25rem',
  },
  chartTooltipRow: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    marginTop: '0.25rem',
  },
  chartTooltipDot: {
    width: '8px',
    height: '8px',
    borderRadius: '50%',
  },
  chartTooltipLabel: {
    color: '#9ca3af',
  },
  chartTooltipValue: {
    fontWeight: 600,
    marginLeft: 'auto',
  },
  emptyChart: {
    height: '240px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    color: '#9ca3af',
    fontSize: '0.875rem',
    fontStyle: 'italic',
  },
  statGrid4: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))',
    gap: '1rem',
  },
  statGrid3: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '1rem',
  },
  statCard: {
    position: 'relative',
    background: '#ffffff',
    borderRadius: '10px',
    border: '1px solid #e5e7eb',
    padding: '1.125rem 1.25rem 1.25rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.375rem',
    overflow: 'hidden',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.05)',
  },
  statHeader: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  statLabel: {
    fontSize: '0.8125rem',
    color: '#6b7280',
    fontWeight: 500,
  },
  statIcon: {
    fontSize: '1.125rem',
  },
  statValue: {
    fontSize: '1.625rem',
    fontWeight: 700,
    lineHeight: 1.15,
    letterSpacing: '-0.02em',
  },
  statSub: {
    fontSize: '0.75rem',
    color: '#9ca3af',
    marginTop: '0.125rem',
  },
  statAccentBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    height: '3px',
  },
  statLoading: {
    padding: '0.5rem 0',
  },
  statusBar: {
    display: 'flex',
    gap: '0.75rem',
    flexWrap: 'wrap',
    background: '#f9fafb',
    borderRadius: '8px',
    padding: '0.75rem 1rem',
    border: '1px solid #f3f4f6',
  },
  statusPill: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    fontSize: '0.8125rem',
    color: '#374151',
  },
  statusDot: {
    width: '8px',
    height: '8px',
    borderRadius: '50%',
  },
  statusLabel: {
    fontWeight: 500,
  },
  statusCount: {
    fontWeight: 700,
    color: '#111827',
    background: '#e5e7eb',
    borderRadius: '9999px',
    padding: '0.0625rem 0.4375rem',
    fontSize: '0.75rem',
  },
  quickGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '0.875rem',
  },
  quickCard: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    background: '#ffffff',
    border: '1px solid #e5e7eb',
    borderRadius: '10px',
    padding: '1rem 1.125rem',
    textDecoration: 'none',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.04)',
    transition: 'border-color 0.15s, box-shadow 0.15s',
  },
  quickIconWrap: {
    width: '42px',
    height: '42px',
    borderRadius: '8px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  quickIcon: {
    fontSize: '1.25rem',
  },
  quickBody: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.125rem',
    flex: 1,
    minWidth: 0,
  },
  quickTitle: {
    fontSize: '0.9375rem',
    fontWeight: 600,
  },
  quickDesc: {
    fontSize: '0.75rem',
    color: '#6b7280',
    lineHeight: 1.35,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
  quickArrow: {
    color: '#9ca3af',
    fontSize: '1rem',
    flexShrink: 0,
  },
}
