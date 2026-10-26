import { useAction, useCollection, useSave } from './collection'
import type { Master } from './types'
export const useMaster = () => useCollection<Master>('master')
export const useSaveMaster = () => useSave<Master>('master')
export const useMasterAction = () => useAction('master')
