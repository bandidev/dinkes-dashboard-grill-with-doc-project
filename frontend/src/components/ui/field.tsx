import type { InputHTMLAttributes, SelectHTMLAttributes } from 'react'
import { cn } from '../../lib/cn'

export const controlClass = 'h-9 w-full rounded-[3px] border border-line bg-paper-raised px-3 text-sm text-ink transition-colors placeholder:text-ink-faint hover:border-ink-faint focus:border-archive focus:outline focus:outline-2 disabled:cursor-not-allowed disabled:bg-paper-inset disabled:text-ink-faint'

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(controlClass, className)} {...props} />
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={cn(controlClass, 'pr-8', className)} {...props} />
}
