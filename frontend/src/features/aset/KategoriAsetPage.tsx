import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, Edit3, Folder, FolderOpen, Plus, Tag, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { api, errorMessage } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import type { FormField } from '../../components/RecordForm'
import { getPage, type Row } from '../satker/api'
import './KategoriAsetPage.css'

const fields: FormField[] = [
  { name: 'kode', label: 'Kode kategori', hint: 'Kode harus unik.' },
  { name: 'nama', label: 'Nama kategori' },
  { name: 'parent_id', label: 'Kategori induk', type: 'select', required: false, options: [] },
]

type Node = { row: Row; children: Node[] }

function buildTree(rows: Row[]): Node[] {
  const byParent = new Map<number | null, Row[]>()
  rows.forEach((row) => {
    const parent = row.parent_id == null ? null : Number(row.parent_id)
    byParent.set(parent, [...(byParent.get(parent) ?? []), row])
  })
  const walk = (parent: number | null, seen: Set<number>): Node[] =>
    (byParent.get(parent) ?? [])
      .filter((row) => !seen.has(row.id))
      .sort((a, b) => String(a.nama).localeCompare(String(b.nama), 'id'))
      .map((row) => ({ row, children: walk(row.id, new Set([...seen, row.id])) }))
  return walk(null, new Set())
}

type BranchProps = {
  nodes: Node[]
  level: number
  selectedId: number | null
  expanded: Set<number>
  onToggle: (id: number) => void
  onSelect: (node: Node) => void
}

function Branch({ nodes, level, selectedId, expanded, onToggle, onSelect }: BranchProps) {
  return (
    <ul role={level === 0 ? 'tree' : 'group'} className={level === 0 ? 'kat-tree' : undefined}>
      {nodes.map((node) => {
        const { row, children } = node
        const hasChildren = children.length > 0
        const open = expanded.has(row.id)
        const isSelected = row.id === selectedId
        return (
          <li
            key={row.id}
            role="treeitem"
            aria-selected={isSelected}
            aria-expanded={hasChildren ? open : undefined}
          >
            <div className={'kat-row' + (isSelected ? ' is-selected' : '')} style={{ paddingLeft: 4 + level * 22 }}>
              {hasChildren ? (
                <button
                  type="button"
                  className="kat-chevron"
                  aria-label={(open ? 'Tutup ' : 'Buka ') + String(row.nama)}
                  onClick={() => onToggle(row.id)}
                >
                  <ChevronRight size={16} className={open ? 'is-open' : undefined} />
                </button>
              ) : (
                <span className="kat-chevron-spacer" />
              )}
              <button type="button" className="kat-label" onClick={() => onSelect(node)}>
                {hasChildren ? (open ? <FolderOpen size={16} /> : <Folder size={16} />) : <Tag size={15} />}
                <span className="kat-name">{String(row.nama)}</span>
                <code className="kat-code">{String(row.kode)}</code>
              </button>
              {hasChildren ? <span className="kat-count">{children.length}</span> : null}
            </div>
            {hasChildren && open ? (
              <Branch
                nodes={children}
                level={level + 1}
                selectedId={selectedId}
                expanded={expanded}
                onToggle={onToggle}
                onSelect={onSelect}
              />
            ) : null}
          </li>
        )
      })}
    </ul>
  )
}

export default function KategoriAsetPage() {
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [expanded, setExpanded] = useState<Set<number>>(new Set())
  const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Row | null>(null)
  const client = useQueryClient()
  const query = useQuery({
    queryKey: ['workspace', 'kategori-aset'],
    queryFn: async ({ signal }) => {
      const firstPage = await getPage('/api/kategori-aset', { per_page: 100 }, signal)
      const rows = [...firstPage.data]
      for (let page = 2; page <= firstPage.meta.last_page; page++) {
        const nextPage = await getPage('/api/kategori-aset', { page, per_page: 100 }, signal)
        rows.push(...nextPage.data)
      }
      return rows
    },
  })
  const rows = useMemo(() => query.data ?? [], [query.data])
  const selected = rows.find((row) => row.id === selectedId) ?? null
  const tree = useMemo(() => buildTree(rows), [rows])
  const parentIds = useMemo(
    () => new Set(rows.filter((row) => row.parent_id != null).map((row) => Number(row.parent_id))),
    [rows],
  )
  const selectedParent = selected && selected.parent_id != null
    ? rows.find((row) => row.id === Number(selected.parent_id)) ?? null
    : null
  const selectedChildren = selected
    ? rows.filter((row) => Number(row.parent_id) === selected.id)
    : []

  const toggle = (id: number) =>
    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  const selectNode = (node: Node) => {
    setSelectedId(node.row.id)
    if (node.children.length) toggle(node.row.id)
  }

  const parentOptions = useMemo(() => {
    if (!selected || formMode !== 'edit') return rows
    const descendants = new Set<number>()
    const addDescendants = (parentId: number) => {
      rows.filter((row) => Number(row.parent_id) === parentId).forEach((row) => {
        if (!descendants.has(row.id)) {
          descendants.add(row.id)
          addDescendants(row.id)
        }
      })
    }
    addDescendants(selected.id)
    return rows.filter((row) => row.id !== selected.id && !descendants.has(row.id))
  }, [formMode, rows, selected])
  const categoryFields = useMemo(
    () => fields.map((field) => field.name === 'parent_id'
      ? {
          ...field,
          options: parentOptions.map((row) => ({
            value: String(row.id),
            label: `${String(row.nama)} (${String(row.kode)})`,
          })),
        }
      : field),
    [parentOptions],
  )

  const save = useMutation({
    mutationFn: async (values: Record<string, string>) => {
      const body = {
        kode: values.kode.trim(),
        nama: values.nama.trim(),
        parent_id: values.parent_id ? Number(values.parent_id) : null,
      }
      if (formMode === 'edit' && selected) {
        await api.patch('/api/kategori-aset/' + selected.id, body)
      } else {
        await api.post('/api/kategori-aset', body)
      }
    },
    onSuccess: async () => {
      setFormMode(null)
      await client.invalidateQueries({ queryKey: ['workspace', 'kategori-aset'] })
      await client.invalidateQueries({ queryKey: ['kasub', 'aset', 'pilihan'] })
      await client.invalidateQueries({ queryKey: ['satker', 'opt', 'kategori'] })
      await client.invalidateQueries({ queryKey: ['satker', 'aset'] })
      toast.success('Kategori aset berhasil disimpan.')
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete('/api/kategori-aset/' + id),
    onSuccess: async () => {
      setDeleteTarget(null)
      setSelectedId(null)
      await client.invalidateQueries({ queryKey: ['workspace', 'kategori-aset'] })
      await client.invalidateQueries({ queryKey: ['kasub', 'aset', 'pilihan'] })
      await client.invalidateQueries({ queryKey: ['satker', 'opt', 'kategori'] })
      await client.invalidateQueries({ queryKey: ['satker', 'aset'] })
      toast.success('Kategori aset dihapus.')
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Referensi · Super Admin</span>
          <h1>Kategori aset</h1>
          <p>Kelola kategori dan subkategori yang tersedia saat pencatatan inventaris aset.</p>
        </div>
        <button type="button" className="primary" onClick={() => setFormMode('create')}>
          <Plus size={16} /> Tambah kategori
        </button>
      </div>

      {query.isPending ? (
        <p role="status">Memuat kategori aset…</p>
      ) : query.isError ? (
        <div role="alert">
          <p>{errorMessage(query.error)}</p>
          <button type="button" onClick={() => void query.refetch()}>Coba lagi</button>
        </div>
      ) : (
        <div className="kat-layout">
          <section className="kat-card kat-tree-card" aria-label="Pohon kategori aset">
            <div className="kat-toolbar">
              <strong>
                Pohon kategori <span>· {rows.length} kategori</span>
              </strong>
              <div className="kat-toolbar-actions">
                <button type="button" onClick={() => setExpanded(new Set(parentIds))}>Buka semua</button>
                <button type="button" onClick={() => setExpanded(new Set())}>Tutup semua</button>
              </div>
            </div>
            {tree.length ? (
              <Branch
                nodes={tree}
                level={0}
                selectedId={selectedId}
                expanded={expanded}
                onToggle={toggle}
                onSelect={selectNode}
              />
            ) : (
              <p className="kat-empty">Belum ada kategori aset. Tambahkan kategori untuk mulai membuat hierarki.</p>
            )}
          </section>

          <section className="kat-card kat-detail" aria-live="polite">
            {selected ? (
              <>
                <h2>{String(selected.nama)}</h2>
                <p className="kat-sub">{selectedParent ? 'Subkategori' : 'Kategori utama'}</p>
                <dl className="kat-fields">
                  <div><dt>Kode</dt><dd><code>{String(selected.kode)}</code></dd></div>
                  <div>
                    <dt>Kategori induk</dt>
                    <dd>{selectedParent ? String(selectedParent.nama) : 'Kategori utama'}</dd>
                  </div>
                  <div>
                    <dt>Subkategori</dt>
                    <dd>
                      {selectedChildren.length ? (
                        <div className="kat-chips">
                          {selectedChildren.map((child) => <span key={child.id}>{String(child.nama)}</span>)}
                        </div>
                      ) : '—'}
                    </dd>
                  </div>
                </dl>
                <div className="kat-actions">
                  <button type="button" onClick={() => setFormMode('edit')}><Edit3 size={16} /> Ubah</button>
                  <button type="button" onClick={() => setDeleteTarget(selected)}><Trash2 size={16} /> Hapus</button>
                </div>
              </>
            ) : (
              <p className="kat-empty">Pilih kategori di sebelah kiri untuk melihat detailnya.</p>
            )}
          </section>
        </div>
      )}

      <Drawer
        open={formMode !== null}
        onClose={() => setFormMode(null)}
        title={formMode === 'edit' ? 'Ubah kategori aset' : 'Tambah kategori aset'}
        description="Kategori dapat disusun bertingkat dengan memilih kategori induk."
      >
        {formMode && (
          <RecordForm
            key={`${formMode}-${selected?.id ?? 'new'}`}
            fields={categoryFields}
            values={formMode === 'edit' && selected
              ? {
                  kode: String(selected.kode),
                  nama: String(selected.nama),
                  parent_id: selected.parent_id == null ? '' : String(selected.parent_id),
                }
              : { parent_id: '' }}
            onCancel={() => setFormMode(null)}
            onSave={(values) => save.mutateAsync(values)}
            submitLabel={formMode === 'edit' ? 'Simpan perubahan' : 'Tambah kategori'}
          />
        )}
      </Drawer>
      <ConfirmDialog
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title="Hapus kategori aset"
        description={[
          `Kategori “${String(deleteTarget?.nama ?? '')}” akan dihapus.`,
          'Kategori yang memiliki subkategori atau sudah dipakai aset tidak dapat dihapus.',
        ]}
        confirmLabel="Hapus kategori"
        pending={remove.isPending}
        onConfirm={() => { if (deleteTarget) remove.mutate(deleteTarget.id) }}
      />
    </>
  )
}
