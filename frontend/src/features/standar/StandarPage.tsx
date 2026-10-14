import { useStandar } from '../../api/standar'
import { MasterWorkspace } from '../master/MasterWorkspace'
export default function StandarPage() {
  const query = useStandar()
  return (
    <MasterWorkspace
      title="Standar dan kontrol"
      description="Pelihara standar acuan, hierarki kontrol, dan register regulasi."
      query={query}
      csv
      tabs={[
        { value: 'standar', label: 'Standar', singular: 'standar' },
        { value: 'kontrol', label: 'Pohon kontrol', singular: 'kontrol', tree: true },
        { value: 'regulasi', label: 'Regulasi', singular: 'regulasi' },
      ]}
    />
  )
}
