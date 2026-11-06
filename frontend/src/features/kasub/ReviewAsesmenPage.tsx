import { ArrowRight, ClipboardCheck, ClipboardList, Info, ShieldCheck } from 'lucide-react'
import { statusAsesmenKasub } from './hooks'
import './ReviewAsesmenPage.css'

/** Kerangka daftar review asesmen Kasub; data memakai B1 saat tersedia. */
export function ReviewAsesmenPage() {
  return <div className="kasub-review-page">
    <header className="kasub-review-hero">
      <div>
        <span className="ws-eyebrow">Kewenangan Kasub</span>
        <h1>Review asesmen</h1>
        <p>Telaah asesmen risiko pada sub bidang yang Anda kepalai sebelum diteruskan ke Kabid.</p>
      </div>
      <aside className="kasub-review-scope" aria-label="Batas kewenangan Kasub">
        <ShieldCheck size={21} aria-hidden="true" />
        <div><strong>Lingkup terlindungi</strong><span>Hanya sub bidang dalam role aktif Anda.</span></div>
      </aside>
    </header>

    <section className="kasub-review-workflow" aria-label="Alur review Kasub">
      <div className="kasub-review-flow-step"><span>01</span><div><strong>Review awal</strong><p>Periksa kelengkapan asesmen.</p></div></div>
      <ArrowRight aria-hidden="true" />
      <div className="kasub-review-flow-step"><span>02</span><div><strong>Keputusan Kasub</strong><p>Setujui atau beri catatan.</p></div></div>
      <ArrowRight aria-hidden="true" />
      <div className="kasub-review-flow-step"><span>03</span><div><strong>Ke Kabid</strong><p>Asesmen diteruskan.</p></div></div>
    </section>

    <section className="ws-panel kasub-review-queue" aria-label="Antrean review asesmen">
        <header><div><span className="ws-eyebrow">Antrean saya</span><h2>Asesmen yang memerlukan review</h2></div><ClipboardCheck size={23} aria-hidden="true" /></header>
        <div className="kasub-review-unavailable" role="status">
          <ClipboardList size={31} aria-hidden="true" />
          <strong>Antrean belum tersedia</strong>
          <p>Daftar akan tampil setelah backend menyediakan filter antrean khusus Kasub.</p>
          <small><Info size={14} aria-hidden="true" /> Menunggu <code>filter[menunggu_role]</code>.</small>
        </div>
        <footer className="kasub-review-status" aria-label="Kategori status asesmen Kasub">
          <span>Status asesmen</span>
          <div>
        {statusAsesmenKasub.map((status) => <span className="ws-badge neutral" key={status.kode}><span />{status.label}</span>)}
          </div>
        </footer>
    </section>
  </div>
}
