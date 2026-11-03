export {}

declare global {
  interface Window {
    grecaptcha?: {
      ready(callback: () => void): void
      render(element: string | HTMLElement, options: {
        sitekey: string
        callback: (token: string) => void
        'expired-callback': () => void
        'error-callback': () => void
      }): number
      reset(widgetId?: number): void
    }
  }
}
