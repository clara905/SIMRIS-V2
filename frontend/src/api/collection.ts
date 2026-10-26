import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
export function useCollection<T>(name: string) {
  return useQuery({
    queryKey: [name],
    queryFn: async () => (await api.get<{ data: T[] }>(`/api/${name}`)).data.data,
  })
}
export function useSave<T extends { id: number }>(name: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: async (values: Partial<T>) =>
      (
        await (values.id
          ? api.put<{ data: T }>(`/api/${name}/${values.id}`, values)
          : api.post<{ data: T }>(`/api/${name}`, values))
      ).data.data,
    onSuccess: (record) => {
      window.dispatchEvent(new CustomEvent('simris-record-saved', { detail: record.id }))
      void client.invalidateQueries({ queryKey: [name] })
      void client.invalidateQueries({ queryKey: ['audit'] })
    },
  })
}
export function useAction(name: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action, body = {} }: { id: number; action: string; body?: unknown }) =>
      (await api.post(`/api/${name}/${id}/${action}`, body)).data,
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: [name] })
      void client.invalidateQueries({ queryKey: ['audit'] })
    },
  })
}
