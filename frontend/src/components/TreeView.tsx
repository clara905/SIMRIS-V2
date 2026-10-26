import { useState } from 'react'
import { ChevronDown, ChevronRight, FolderClosed } from 'lucide-react'
import s from './TreeView.module.css'
export interface TreeNode {
  id: number
  label: string
  children?: TreeNode[]
}
export function TreeView({
  nodes,
  selected,
  onSelect,
  label = 'Arsip bertingkat',
}: {
  nodes: TreeNode[]
  selected?: number
  onSelect: (id: number) => void
  label?: string
}) {
  const [closed, setClosed] = useState<number[]>([])
  const render = (items: TreeNode[]) => (
    <ul className={s.tree}>
      {items.map((node) => (
        <li key={node.id}>
          <div className={s.row}>
            {node.children?.length ? (
              <button
                className="icon-button"
                aria-label={`${closed.includes(node.id) ? 'Bentangkan' : 'Ciutkan'} ${node.label}`}
                aria-expanded={!closed.includes(node.id)}
                onClick={() =>
                  setClosed((list) =>
                    list.includes(node.id)
                      ? list.filter((id) => id !== node.id)
                      : [...list, node.id],
                  )
                }
              >
                {closed.includes(node.id) ? <ChevronRight size={16} /> : <ChevronDown size={16} />}
              </button>
            ) : (
              <span className={s.leaf} />
            )}
            <button aria-current={selected === node.id} onClick={() => onSelect(node.id)}>
              <FolderClosed size={16} aria-hidden="true" />
              {node.label}
            </button>
          </div>
          {!!node.children?.length && !closed.includes(node.id) && render(node.children)}
        </li>
      ))}
    </ul>
  )
  return <nav aria-label={label}>{render(nodes)}</nav>
}
