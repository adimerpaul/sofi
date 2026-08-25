/**
 * Manda un PDF a la impresora sin abrir una pestana: se carga en un iframe
 * oculto y se dispara su dialogo de impresion.
 *
 * Antes vivia aca tambien un candado para que la impresion automatica no se
 * repitiera entre pestanas; ya no hace falta porque nada imprime solo: el
 * comprobante se imprime cuando el cajero lo pide.
 */
export function imprimirPdfDirecto (blob, nombre) {
  return new Promise((resolve, reject) => {
    const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
    const marco = document.createElement('iframe')
    marco.style.position = 'fixed'
    marco.style.width = '0'
    marco.style.height = '0'
    marco.style.border = '0'
    marco.setAttribute('aria-hidden', 'true')

    const limpiar = () => {
      if (marco.parentNode) marco.parentNode.removeChild(marco)
      window.URL.revokeObjectURL(url)
    }

    marco.onload = () => {
      try {
        marco.contentWindow.focus()
        marco.contentWindow.print()
        resolve()
      } catch (error) {
        const link = document.createElement('a')
        link.href = url
        link.download = nombre
        link.click()
        reject(error)
      } finally {
        setTimeout(limpiar, 60000)
      }
    }
    marco.onerror = () => {
      limpiar()
      reject(new Error('No se pudo cargar el PDF para imprimir'))
    }
    marco.src = url
    document.body.appendChild(marco)
  })
}
