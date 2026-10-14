import { useAset } from '../../api/aset'
import { MasterWorkspace } from '../master/MasterWorkspace'
export default function AsetPage() {
  const query = useAset()
  return (
    <MasterWorkspace
      title="Aset master"
      description="Kelola sumber, kategori, klasifikasi, dan status sinkronisasi aset."
      query={query}
      tabs={[
        { value: 'sumber', label: 'Sumber dan sinkronisasi', singular: 'sumber' },
        { value: 'kategori', label: 'Kategori aset', singular: 'kategori', tree: true },
        { value: 'klasifikasi', label: 'Klasifikasi', singular: 'klasifikasi' },
      ]}
    />
  )
}
