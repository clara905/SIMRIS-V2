import { useKatalog } from '../../api/katalog'
import { MasterWorkspace } from '../master/MasterWorkspace'
export default function KatalogPage() {
  const query = useKatalog()
  return (
    <MasterWorkspace
      title="Katalog"
      description="Referensi ancaman dan kerentanan untuk penilaian yang konsisten."
      query={query}
      csv
      tabs={[
        { value: 'ancaman', label: 'Ancaman', singular: 'ancaman' },
        { value: 'kerentanan', label: 'Kerentanan', singular: 'kerentanan' },
      ]}
    />
  )
}
