import type { ButtonHTMLAttributes } from 'react'
import { cn } from '../../lib/cn'

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md'
}

const variants = {
  primary: 'border-archive bg-archive text-paper-raised hover:bg-[#274b38]',
  outline: 'border-line bg-paper-raised text-ink hover:border-archive hover:text-archive',
  ghost: 'border-transparent bg-transparent text-ink-muted hover:bg-paper-inset hover:text-ink',
  danger: 'border-correction bg-correction text-paper-raised hover:bg-[#87372b]',
}

export function Button({ className, variant = 'primary', size = 'md', type = 'button', ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-[3px] border font-semibold transition-[color,background-color,border-color,transform] duration-150 focus-visible:outline focus-visible:outline-2 active:scale-[0.96] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100',
        size === 'sm' ? 'h-8 px-3 text-xs' : 'h-10 px-4 text-sm',
        variants[variant],
        className,
      )}
      {...props}
    />
  )
}
