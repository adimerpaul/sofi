import xlsx from 'json-as-xlsx'
import { Printd } from 'printd'

const col = (label, value, dinero = false) => ({ label, value, ...(dinero ? { format: '#,##0.00' } : {}) })
const suma = (filas, campo) => Math.round(filas.reduce((total, fila) => total + Number(fila[campo] || 0), 0) * 100) / 100
const clientesColumnas = [col('Código', 'id'), col('Cliente', 'nombre'), col('NIT', 'nit'), col('Teléfono', 'telefono'), col('Dirección', 'direccion'), col('Zona', 'zona'), col('Preventista', 'vendedor'), col('Deudas pendientes', 'deudas'), col('Desde', 'desde'), col('Días', 'dias'), col('Saldo Bs', 'saldo', true)]
const deudasColumnas = [col('Origen', 'origen'), col('Número', 'id'), col('Fecha', 'fecha'), col('Concepto', 'concepto'), col('Estado', 'estado'), col('Monto Bs', 'monto', true), col('Abonado Bs', 'pagado', true), col('Saldo Bs', 'saldo', true)]
const hojaDeudas = filas => ({ sheet: 'Deudas', columns: deudasColumnas, content: [...filas, { concepto: 'TOTAL', monto: suma(filas, 'monto'), pagado: suma(filas, 'pagado'), saldo: suma(filas, 'saldo') }] })

export function reporteGeneral (clientes, alcance) {
  return { titulo: 'Créditos de clientes', alcance, hojas: [{ sheet: 'Clientes', columns: clientesColumnas, content: [...clientes, { nombre: 'TOTAL', deudas: suma(clientes, 'deudas'), saldo: suma(clientes, 'saldo') }] }] }
}

export function reporteCliente (detalle, deuda, abonos = []) {
  const c = detalle.cliente
  const hojas = [{ sheet: 'Cliente', columns: clientesColumnas, content: [{ ...c, deudas: detalle.totales.deudas, saldo: detalle.totales.saldo }] }, hojaDeudas(deuda ? [deuda] : detalle.deudas)]
  const ventas = deuda ? detalle.ventas.filter(v => deuda.origen === 'factura' && String(v.id) === String(deuda.id)) : detalle.ventas
  if (ventas.length) {
    hojas.push({ sheet: 'Ventas a crédito', columns: [col('Venta', 'id'), col('Fecha', 'fecha'), col('Estado', 'estado'), col('Pedido', 'pedido'), col('Total Bs', 'total', true), col('Abonado Bs', 'pagado', true), col('Saldo Bs', 'saldo', true)], content: [...ventas, { estado: 'TOTAL SIN ANULADAS', total: suma(ventas.filter(v => v.estado !== 'ANULADA'), 'total'), pagado: suma(ventas, 'pagado'), saldo: suma(ventas, 'saldo') }] })
    hojas.push({ sheet: 'Productos', columns: [col('Venta', 'venta'), col('Estado venta', 'estado'), col('Código', 'cod_prod'), col('Producto', 'nombre'), col('Unidad', 'unidad'), col('Cantidad', 'cantidad'), col('Peso kg', 'peso'), col('Precio Bs', 'precio', true), col('Subtotal Bs', 'subtotal', true)], content: ventas.flatMap(v => v.productos.map(p => ({ ...p, venta: v.id, estado: v.estado }))) })
  }
  if (deuda) hojas.push({ sheet: 'Abonos', columns: [col('Número', 'id'), col('Fecha', 'created_at'), col('Forma de pago', 'forma_pago'), col('Referencia', 'referencia'), col('Cobrador', 'cobrador'), col('Monto Bs', 'monto', true)], content: [...abonos, { cobrador: 'TOTAL', monto: suma(abonos, 'monto') }] })
  return { titulo: deuda ? 'Detalle de deuda ' + deuda.origen + ' #' + deuda.id : 'Estado de cuenta del cliente', alcance: c.nombre + ' · NIT ' + (c.nit || '—') + (deuda ? ' · ' + deuda.concepto : ' · Todas las deudas y ventas a crédito'), hojas }
}

const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export function htmlReporte (reporte) {
  return `<h1>Sofia · ${escapar(reporte.titulo)}</h1><p>${escapar(reporte.alcance)}</p><p>Emitido: ${escapar(new Date().toLocaleString('es-BO'))} · Importes en bolivianos (Bs)</p>` + reporte.hojas.map(h => `<h2>${escapar(h.sheet)}</h2><table><thead><tr>${h.columns.map(c => `<th>${escapar(c.label)}</th>`).join('')}</tr></thead><tbody>${h.content.map(f => `<tr>${h.columns.map(c => `<td${c.format ? ' class="numero"' : ''}>${escapar(c.format ? Number(f[c.value] || 0).toFixed(2) : f[c.value])}</td>`).join('')}</tr>`).join('')}</tbody></table>`).join('')
}

let impresora
export function emitirReporte (reporte, formato) {
  if (formato === 'excel') {
    const metadata = { sheet: 'Reporte', columns: [col('Título', 'titulo'), col('Alcance', 'alcance'), col('Emitido', 'fecha'), col('Moneda', 'moneda')], content: [{ titulo: reporte.titulo, alcance: reporte.alcance, fecha: new Date().toLocaleString('es-BO'), moneda: 'BOB (Bs)' }] }
    xlsx([metadata, ...reporte.hojas], { fileName: (reporte.titulo + '_' + reporte.alcance).replace(/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ_-]+/g, '_').slice(0, 130) + '_' + Date.now(), extraLength: 3 })
  } else {
    const contenido = document.createElement('div')
    contenido.innerHTML = htmlReporte(reporte)
    if (!impresora) impresora = new Printd()
    impresora.print(contenido, ['@page { size: A4 landscape; margin: 12mm; } body { font: 11px Arial; color: #111; } h1 { font-size: 19px; } h2 { font-size: 14px; margin-top: 20px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 5px; text-align: left; overflow-wrap: anywhere; } th { background: #eee; } .numero { text-align: right; white-space: nowrap; } thead { display: table-header-group; } tr { break-inside: avoid; }'])
  }
}
