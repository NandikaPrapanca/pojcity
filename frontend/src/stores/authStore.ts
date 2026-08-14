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

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: localStorage.getItem('token'),
  isAuthenticated: !!localStorage.getItem('token'),
  isLoading: false,

  login: async (email: string, password: string) => {
    set({ isLoading: true })
    try {
      const response = await api.post('/auth/login', { email, password })
      const { token, user } = response.data.data
      localStorage.setItem('token', token)
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
      set({ user: null, token: null, isAuthenticated: false })
    }
  },

  fetchMe: async () => {
    const token = get().token ?? localStorage.getItem('token')
    if (!token) {
      set({ isAuthenticated: false })
      return
    }
    try {
      const response = await api.get('/auth/me')
      const user = response.data.data.user
      set({ user, isAuthenticated: true })
    } catch {
      localStorage.removeItem('token')
      set({ user: null, token: null, isAuthenticated: false })
    }
  },
}))
