import React from 'react'
interface CardProps { children: React.ReactNode; style?: React.CSSProperties }
export default function Card({ children, style }: CardProps) {
  return (
    <div style={{ backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: '1.25rem', ...style }}>
      {children}
    </div>
  )
}
