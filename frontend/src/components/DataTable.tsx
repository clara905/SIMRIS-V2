import { useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import {
  useLegacyTable,
  getCoreRowModel,
  getSortedRowModel,
  getPaginationRowModel,
} from '@tanstack/react-table/legacy'
import { flexRender } from '@tanstack/react-table'
import type { LegacyColumnDef } from '@tanstack/react-table/legacy'
import { ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react'
import { EmptyState } from './States'
import s from './DataTable.module.css'
export interface DataColumn<T> {
  key: keyof T & string
  label: string
  render?: (row: T) => ReactNode
  numeric?: boolean
}
export function DataTable<T extends { id: number }>({
  data,
  columns,
  caption,
  onSelect,
  userId = 'anonymous',
  emptyAction,
}: {
  data: T[]
  columns: DataColumn<T>[]
  caption: string
  onSelect?: (row: T) => void
  userId?: string | number
  emptyAction?: ReactNode
}) {
  const [density, setDensity] = useState(
    () => localStorage.getItem(`simris-density-${userId}`) || 'normal',
  )
  const [savedId, setSavedId] = useState<number | null>(null)
  useEffect(() => {
    let timeout: ReturnType<typeof setTimeout>
    const handler = (event: Event) => {
      setSavedId((event as CustomEvent<number>).detail)
      clearTimeout(timeout)
      timeout = setTimeout(() => setSavedId(null), 2400)
    }
    window.addEventListener('simris-record-saved', handler)
    return () => {
      clearTimeout(timeout)
      window.removeEventListener('simris-record-saved', handler)
    }
  }, [])
  const defs = useMemo<LegacyColumnDef<T>[]>(
    () =>
      columns.map((column, i) => ({
        accessorKey: column.key,
        header: column.label,
        cell: (context) => {
          const content = column.render
            ? column.render(context.row.original)
            : String(context.getValue() ?? '—')
          return i === 0 && onSelect ? (
            <button className="link-button" onClick={() => onSelect(context.row.original)}>
              {content}
            </button>
          ) : (
            content
          )
        },
      })),
    [columns, onSelect],
  )
  const table = useLegacyTable({
    data,
    columns: defs,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    initialState: { pagination: { pageIndex: 0, pageSize: 10 } },
  })
  if (!data.length)
    return (
      <EmptyState
        title="Tidak ada data yang ditampilkan"
        description="Ubah pencarian atau tambahkan data untuk memulai register ini."
        action={emptyAction}
      />
    )
  return (
    <div className={s.wrap}>
      <div className={s.scroll} tabIndex={0} role="region" aria-label={caption}>
        <table className={`${s.table} ${density === 'rapat' ? s.compact : ''}`}>
          <caption className="sr-only">{caption}</caption>
          <thead>
            {table.getHeaderGroups().map((group) => (
              <tr key={group.id}>
                {group.headers.map((header, i) => (
                  <th
                    key={header.id}
                    scope="col"
                    className={columns[i].numeric ? s.numeric : ''}
                    aria-sort={
                      header.column.getIsSorted() === 'asc'
                        ? 'ascending'
                        : header.column.getIsSorted() === 'desc'
                          ? 'descending'
                          : 'none'
                    }
                  >
                    <button className={s.sort} onClick={header.column.getToggleSortingHandler()}>
                      {flexRender(header.column.columnDef.header, header.getContext())}
                      <ArrowUpDown size={12} aria-hidden="true" />
                    </button>
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {table.getRowModel().rows.map((row) => (
              <tr key={row.id} className={row.original.id === savedId ? s.saved : undefined}>
                {row.getVisibleCells().map((cell, i) => (
                  <td key={cell.id} className={columns[i].numeric ? s.numeric : ''}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className={s.pagination}>
        <span>
          {data.length} data · Halaman {table.getState().pagination.pageIndex + 1} dari{' '}
          {Math.max(1, table.getPageCount())}
        </span>
        <div className={s.controls}>
          <select
            aria-label="Kepadatan tabel"
            value={density}
            onChange={(e) => {
              setDensity(e.target.value)
              localStorage.setItem(`simris-density-${userId}`, e.target.value)
            }}
          >
            <option value="normal">Normal</option>
            <option value="rapat">Rapat</option>
          </select>
          <button
            aria-label="Halaman sebelumnya"
            disabled={!table.getCanPreviousPage()}
            onClick={() => table.previousPage()}
          >
            <ChevronLeft size={16} />
          </button>
          <button
            aria-label="Halaman berikutnya"
            disabled={!table.getCanNextPage()}
            onClick={() => table.nextPage()}
          >
            <ChevronRight size={16} />
          </button>
        </div>
      </div>
    </div>
  )
}
