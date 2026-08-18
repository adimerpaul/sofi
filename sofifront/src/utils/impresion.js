const VIGENCIA_IMPRESION = 5 * 60 * 1000

function claveGuardada (clave) {
  return 'sofia:impresion:' + clave
}

function yaFueImpresa (clave) {
  const valor = Number(window.localStorage.getItem(claveGuardada(clave)) || 0)
  return valor > 0 && Date.now() - valor < VIGENCIA_IMPRESION
}

/** Ejecuta una impresion automatica una sola vez entre todas las pestanas. */
export async function imprimirUnaVez (clave, trabajo) {
  const ejecutar = async () => {
    if (yaFueImpresa(clave)) return false
    await trabajo()
    window.localStorage.setItem(claveGuardada(clave), String(Date.now()))
    return true
  }

  if (window.navigator.locks) {
    return window.navigator.locks.request('sofia:impresion:' + clave, ejecutar)
  }
  return ejecutar()
}

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
