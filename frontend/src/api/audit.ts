import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { Audit } from './types'

type AuditPage = {
  data: Audit[]
  meta?: { last_page?: number }
}

export const useAudit = () =>
  useQuery({
    queryKey: ['audit'],
    queryFn: async () => {
      const first = await api.get<AuditPage>('/api/log-audit', {
        params: { per_page: 100, sort: '-waktu' },
      })
      const rows = [...first.data.data]
      const lastPage = first.data.meta?.last_page ?? 1

      if (lastPage > 1) {
        const remaining = await Promise.all(
          Array.from({ length: lastPage - 1 }, (_, index) =>
            api.get<AuditPage>('/api/log-audit', {
              params: { per_page: 100, sort: '-waktu', page: index + 2 },
            }),
          ),
        )
        remaining.forEach((page) => rows.push(...page.data.data))
      }

      return rows
    },
  })
