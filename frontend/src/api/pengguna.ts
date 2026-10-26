import { useAction, useCollection, useSave } from './collection'
import type { Pengguna } from './types'
export const usePengguna = () => useCollection<Pengguna>('pengguna')
export const useSavePengguna = () => useSave<Pengguna>('pengguna')
export const usePenggunaAction = () => useAction('pengguna')
