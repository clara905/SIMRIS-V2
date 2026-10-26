import { usePengguna } from './pengguna'
import { usePegawai } from './pegawai'
import { useUnit } from './unit'
import { usePeriode } from './periode'
import { useMonitoring } from './monitoring'
import { useAudit } from './audit'
export function useBeranda() {
  const users = usePengguna()
  const employees = usePegawai()
  const units = useUnit()
  const periods = usePeriode()
  const monitoring = useMonitoring()
  const audit = useAudit()
  return { users, employees, units, periods, monitoring, audit }
}
