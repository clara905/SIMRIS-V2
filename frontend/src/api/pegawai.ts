import { useCollection, useSave } from './collection'
import type { Pegawai } from './types'
export const usePegawai = () => useCollection<Pegawai>('pegawai')
export const useSavePegawai = () => useSave<Pegawai>('pegawai')
