import { create } from 'zustand'
import api from '@/lib/axios'

export interface AuthUser {
  id: string | number
  name: string
  email: string
  role: string
}

interface AuthState {
  user: AuthUser | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  fetchMe: () => Promise<void>
}

const rawToken = localStorage.getItem('token')
const isValidToken = Boolean(
  rawToken && rawToken !== 'undefined' && rawToken !== 'null' && rawToken.trim() !== ''
)

if (isValidToken && rawToken) {
  api.defaults.headers.common['Authorization'] = `Bearer ${rawToken}`
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: isValidToken ? rawToken : null,
  isAuthenticated: isValidToken,
  isLoading: false,

  login: async (email: string, password: string) => {
    set({ isLoading: true })
    try {
      const response = await api.post('/auth/login', { email, password })
      const { token, user } = response.data.data
      localStorage.setItem('token', token)
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
      set({ user, token, isAuthenticated: true, isLoading: false })
    } catch (error) {
      set({ isLoading: false })
      throw error
    }
  },

  logout: async () => {
    try {
      const token = get().token
      if (token) {
        await api.post('/auth/logout')
      }
    } catch {
      // Ignore logout API errors — always clear local state
    } finally {
      localStorage.removeItem('token')
      delete api.defaults.headers.common['Authorization']
      set({ user: null, token: null, isAuthenticated: false })
    }
  },

  fetchMe: async () => {
    const token = get().token ?? localStorage.getItem('token')
    if (!token) {
      delete api.defaults.headers.common['Authorization']
      set({ isAuthenticated: false })
      return
    }
    try {
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
      const response = await api.get('/auth/me')
      const user = response.data.data.user
      set({ user, isAuthenticated: true })
    } catch {
      localStorage.removeItem('token')
      delete api.defaults.headers.common['Authorization']
      set({ user: null, token: null, isAuthenticated: false })
    }
  },
}))

