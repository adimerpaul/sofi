/**
 * Reglas del pedido compartidas entre la visita (crear, Visita.vue) y Mis
 * pedidos (modificar, Mispedidos.vue). Si se cambia una regla aca, cambia en
 * las dos pantallas.
 */

// Grupo de productos FRIAL (tbgrupos.Cod_grup).
export const GRUPO_FRIAL = '31'

// Solo el grupo FRIAL lleva rango de peso (tbpedidos.rango_kg).
export function esFrial (p) {
  return String((p && p.cod_grup) || '').trim() == GRUPO_FRIAL
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
  return ['U', 'CAJA', 'KG']
}

export function unidadPorDefecto (p) {
  const unidades = unidadesCaja(p)
  if (!unidades.length) return null
  return unidades.includes('CAJA') ? 'KG' : 'U'
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
