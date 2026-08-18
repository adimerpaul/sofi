import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { io } from 'socket.io-client'
import { api } from 'src/boot/axios'
import { imprimirPdfDirecto, imprimirUnaVez } from 'src/utils/impresion'

const EVENTO_IMPRESION = 'factura-imprimir'

async function imprimirDocumento (id, documento) {
  return imprimirUnaVez(documento + ':' + id, async () => {
    const respuesta = await api.get('facturacion/' + id + '/' + documento, { responseType: 'blob' })
    await imprimirPdfDirecto(respuesta.data, documento + '_' + id + '.pdf')
  })
}

export default boot(({ app, router }) => {
  const socket = io(process.env.SOCKET, {
    transports: ['websocket', 'polling'],
    reconnection: true
  })

  socket.on(EVENTO_IMPRESION, payload => {
    // Solo la pantalla principal de facturacion funciona como impresora.
    // La tablet que registra el pedido nunca imprime la respuesta del socket.
    if (!payload || router.currentRoute.value.path !== '/facturacion') return
    const documento = payload.documento === 'factura' ? 'factura' : 'voucher'
    window.dispatchEvent(new CustomEvent('sofia:facturacion-actualizada', {
      detail: { id: payload.id, documento }
    }))
    imprimirDocumento(payload.id, documento).catch(error => {
      console.error('No se pudo imprimir el documento recibido por socket', error)
    })
  })

  app.config.globalProperties.$socket = socket
  app.config.globalProperties.$solicitarImpresion = async (id, documento) => {
    if (!socket.connected) {
      throw new Error('La estacion de impresion no esta conectada')
    }

    await axios.post(process.env.SOCKET + '/notify', {
      event: EVENTO_IMPRESION,
      data: { id, documento }
    })
  }
})
