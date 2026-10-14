import { usePeriode } from '../../api/periode'
import { MasterWorkspace } from '../master/MasterWorkspace'
export default function PeriodePage() {
  const query = usePeriode()
  return (
    <MasterWorkspace
      title="Periode penilaian"
      description="Atur rentang penilaian dan rencana kerja pengelolaan risiko."
      query={query}
      tabs={[
        { value: 'periode', label: 'Periode penilaian', singular: 'periode', dates: true },
        { value: 'rencana', label: 'Rencana kerja', singular: 'rencana kerja', dates: true },
      ]}
    />
  )
}
