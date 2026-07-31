import { useEffect, useState } from 'react'

/**
 * `isLoading` real (não hardcoded `false`) pra listas de plataforma/admin
 * que não são tenant-scoped e por isso não têm um gate natural equivalente
 * a `!activeTenantUuid` (usado nas telas de Cliente/Produto). `ServerDataGrid`
 * só chama `fetchPage` depois de montado, e `CrudListPage` só monta os
 * `children` quando `isLoading` é `false` — não dá pra derivar esse booleano
 * do próprio `fetchPage` do grid sem um deadlock (o grid nunca monta pra
 * disparar o fetch que destravaria o `isLoading`). Este hook resolve com uma
 * chamada leve e independente (`probe`, ex.: `per_page: 1`) só pra saber
 * quando a API já respondeu uma vez — o grid ainda faz sua própria busca
 * real ao montar, então existe uma requisição extra pequena por visita a
 * essas 5 telas; aceitável dado o volume baixo de acesso a telas admin.
 */
export function useInitialLoadGate(probe: () => Promise<unknown>): boolean {
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    probe().finally(() => {
      if (!cancelled) setIsLoading(false)
    })

    return () => {
      cancelled = true
    }
    // `probe` é uma função de service (module-level) construída inline no
    // call site — rodar só uma vez por montagem é o comportamento certo.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return isLoading
}
