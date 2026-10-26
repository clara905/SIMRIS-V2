import { useCollection } from './collection'
import type { Asesmen } from './types'
export const useMonitoring = () => useCollection<Asesmen>('monitoring')
