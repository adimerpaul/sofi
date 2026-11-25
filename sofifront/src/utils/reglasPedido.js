/**
 * Reglas del pedido compartidas entre la visita (crear, Visita.vue) y Mis
 * pedidos (modificar, Mispedidos.vue). Si se cambia una regla aca, cambia en
 * las dos pantallas.
 */

// Grupo de productos FRIAL (tbgrupos.Cod_grup).
export const GRUPO_FRIAL = '31'

// Grupo de productos TROZADOS (tbgrupos.Cod_grup): pierna muslo, alas,
// pechuga... a granel. Se piden por unidad o por kilo, no por caja.
export const GRUPO_TROZADOS = '32'

// Solo el grupo FRIAL lleva rango de peso (tbpedidos.rango_kg).
export function esFrial (p) {
  return String((p && p.cod_grup) || '').trim() == GRUPO_FRIAL
}

export function esTrozado (p) {
  return String((p && p.cod_grup) || '').trim() == GRUPO_TROZADOS
}

/**
 * Unidades en que se puede pedir un producto que se vende por caja (se
 * guarda en tbpedidos.caja): el FRIAL solo por unidad, el cerdo por unidad
 * y lo demas tambien por caja o kilo. Los que no son por caja no eligen.
 */
export function unidadesCaja (p) {
  if (String(p.codUnid || '').trim() != 'CAJA') return []
  if (esFrial(p)) return ['U']
  // El cerdo ya solo se pide por unidades; KG solo se deja en los pedidos
  // viejos que ya lo tenian, para no cambiarles la unidad al editarlos.
  if (p.tipo == 'CERDO') return p.caja == 'KG' ? ['U', 'KG'] : ['U']
  // Los trozados ya no se piden por caja; CAJA solo se deja en los pedidos
  // viejos que ya la tenian, para no cambiarles la unidad al editarlos.
  if (esTrozado(p)) return p.caja == 'CAJA' ? ['U', 'CAJA', 'KG'] : ['U', 'KG']
  return ['U', 'CAJA', 'KG']
}

// Por defecto se pide en kilos donde se puede; si no, por unidad.
export function unidadPorDefecto (p) {
  const unidades = unidadesCaja(p)
  if (!unidades.length) return null
  return unidades.includes('KG') ? 'KG' : 'U'
}

/**
 * Subtotal aproximado de una linea del pedido.
 *
 * - precioAprox > 0: monto fijo por unidad (cajas, cerdo entero), como antes.
 * - factorVenta > 0 (Productos > Factor venta): lo que vale 1 unidad pedida en
 *   la unidad del precio, ej. 1 tocino de 150 g = 0.150 kg, 1 frial = 2.51 kg.
 *   El subtotal es cantidad x factorVenta x precio. No se aplica si la linea
 *   ya se pide en kilos.
 * - Si no, cantidad x precio.
 */
export function subtotalLinea (fila) {
  const cantidad = parseFloat(fila.cantidad) || 0
  const aprox = parseFloat(fila.precioAprox) || 0
  if (aprox > 0) return (cantidad * aprox).toFixed(2)
  const factor = parseFloat(fila.factorVenta) || 0
  const multiplica = factor > 0 && fila.caja !== 'KG' ? factor : 1
  return (cantidad * multiplica * (parseFloat(fila.precio) || 0)).toFixed(2)
}

// Cuentas "BAJAS POR ..." (bonificaciones, calidad, merma, vencimiento,
// degustacion): se reconocen por el nombre, como en CreditoController.
export function esBaja (cliente) {
  if (!cliente) return false
  return String(cliente.Nombres || '').trim().toUpperCase().startsWith('BAJAS POR ') ||
    cliente.Id == '61839000' || cliente.Id == '0023456'
}

// Clientes > "Acepta crédito". Las bajas siempre van a credito.
export function aceptaCredito (cliente) {
  if (!cliente) return false
  return Number(cliente.acepta_credito) === 1 || esBaja(cliente)
}

/**
 * Tipos de pago que puede elegir el cliente. Ya no hay PAGO QR; las bajas
 * van si o si a credito y el credito solo para quien lo acepta.
 */
export function tiposPago (cliente) {
  if (esBaja(cliente)) return [{ value: 'CREDITO', label: 'Credito' }]
  const tipos = [{ value: 'CONTADO', label: 'Contado' }]
  if (aceptaCredito(cliente)) tipos.push({ value: 'CREDITO', label: 'Credito' })
  tipos.push({ value: 'BOLETA ANTERIOR', label: 'Boleta anterior' })
  return tipos
}

// Pago ya marcado al abrir el pedido: credito si lo acepta, si no contado.
export function pagoPorDefecto (cliente) {
  return aceptaCredito(cliente) ? 'CREDITO' : 'CONTADO'
}
