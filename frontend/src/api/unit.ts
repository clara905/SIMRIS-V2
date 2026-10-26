import { useCollection, useSave } from './collection'
import type { Unit } from './types'
export const useUnit = () => useCollection<Unit>('unit')
export const useSaveUnit = () => useSave<Unit>('unit')
