import { useCollection } from './collection'
import type { Audit } from './types'
export const useAudit = () => useCollection<Audit>('audit')
