import { MessageSquareText, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Button } from './button'

export function ReasonDialog({ open, title, description, confirmLabel, busy, onClose, onConfirm }: { open: boolean; title: string; description: string; confirmLabel: string; busy: boolean; onClose: () => void; onConfirm: (reason: string) => void }) {
  const dialogRef = useRef<HTMLDialogElement>(null)
  const [reason, setReason] = useState('')

  useEffect(() => {
    const dialog = dialogRef.current
    if (!dialog) return
    if (open && !dialog.open) { setReason(''); dialog.showModal() }
    if (!open && dialog.open) dialog.close()
  }, [open])

  return (
    <dialog ref={dialogRef} className="reason-dialog m-auto w-[min(92vw,500px)] rounded-[6px] border border-line bg-paper-raised p-0 text-ink shadow-[0_24px_80px_rgba(32,36,31,0.25)] backdrop:bg-ink/40 backdrop:backdrop-blur-[2px]" onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}>
      <form method="dialog" onSubmit={(event) => { event.preventDefault(); if (reason.trim()) onConfirm(reason.trim()) }}>
        <div className="flex items-start gap-3 border-b border-line-soft p-5">
          <span className="grid size-10 shrink-0 place-items-center rounded-[4px] bg-correction-soft text-correction"><MessageSquareText className="size-5" aria-hidden="true" /></span>
          <div className="min-w-0 flex-1"><h2 className="text-base font-bold tracking-[-0.015em]">{title}</h2><p className="mt-1 text-xs leading-5 text-ink-muted">{description}</p></div>
          <button type="button" className="grid size-10 place-items-center rounded-[3px] text-ink-muted hover:bg-paper-inset hover:text-ink disabled:opacity-50" aria-label="Tutup" disabled={busy} onClick={onClose}><X className="size-4" /></button>
        </div>
        <div className="p-5">
          <label className="text-xs font-bold" htmlFor="status-reason">Alasan perubahan status</label>
          <textarea id="status-reason" autoFocus maxLength={1000} rows={4} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Contoh: Data sumber perlu diperbaiki berdasarkan dokumen terbaru." className="mt-2 w-full resize-y rounded-[4px] border border-line bg-paper-inset px-3 py-2.5 text-sm leading-6 text-ink outline-offset-1 placeholder:text-ink-faint focus:border-correction focus:outline focus:outline-1 focus:outline-correction/45" />
          <div className="mt-1.5 flex items-center justify-between text-[10px] text-ink-faint"><span>Wajib diisi agar Riwayat Revisi mudah ditelusuri.</span><span className="font-mono tabular">{reason.length}/1000</span></div>
        </div>
        <div className="flex justify-end gap-2 border-t border-line-soft bg-paper-inset/60 px-5 py-4"><Button variant="ghost" onClick={onClose} disabled={busy}>Batal</Button><Button variant="danger" type="submit" disabled={!reason.trim() || busy}>{busy ? 'Memproses…' : confirmLabel}</Button></div>
      </form>
    </dialog>
  )
}
