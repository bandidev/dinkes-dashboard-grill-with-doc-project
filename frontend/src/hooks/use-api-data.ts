import { useEffect, useState } from 'react'

type AsyncState<T> = {
  data: T | null
  error: string | null
  loading: boolean
}

export function useApiData<T>(load: () => Promise<T>, dependencies: readonly unknown[], previewData?: T): AsyncState<T> {
  const [state, setState] = useState<AsyncState<T>>({ data: previewData || null, error: null, loading: !previewData })

  useEffect(() => {
    if (previewData) {
      setState({ data: previewData, error: null, loading: false })
      return
    }

    let active = true
    setState((current) => ({ ...current, error: null, loading: true }))
    load()
      .then((data) => {
        if (active) setState({ data, error: null, loading: false })
      })
      .catch((error: unknown) => {
        if (active) setState({ data: null, error: error instanceof Error ? error.message : 'Terjadi kesalahan.', loading: false })
      })

    return () => { active = false }
    // The caller controls reloads through primitive dependencies.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, dependencies)

  return state
}
