import s from './AppLoader.module.css'

export function AppLoader({ message = 'Memuat ruang kerja…' }: { message?: string }) {
  return (
    <div className={s.loaderScreen} role="status" aria-live="polite">
      <div className={s.logoWrapper}>
        <div className={s.pulseWave} aria-hidden="true" />
        <div className={s.spinnerRingInner} aria-hidden="true" />
        <div className={s.spinnerRing} aria-hidden="true" />
        <img src="/logo-kemhan.png" alt="Logo Kemhan" className={s.logoImg} />
      </div>
      <div className={s.brandInfo}>
        <h1 className={s.brandName}>SIMRIS</h1>
        <p className={s.brandTagline}>Kementerian Pertahanan Republik Indonesia</p>
        <div className={s.progressTrack} aria-hidden="true">
          <div className={s.progressBar} />
        </div>
        <p className={s.statusMessage}>{message}</p>
      </div>
    </div>
  )
}
