import { useCollection, useSave, useAction } from './collection'
import type { Metode } from './types'
export const useMetode = () => useCollection<Metode>('metode')
export const useSaveMetode = () => useSave<Metode>('metode')
export const useMetodeAction = () => useAction('metode')
