// Aviso de pedido mandado despues de la hora limite de su tipo (Facturacion >
// Horarios de envio). El backend registra el pedido igual, lo deja oculto para
// facturacion y responde { aviso, fuera_horario }.
export function avisarFueraHorario ($q, data) {
  if (!data || !data.aviso) return false
  $q.dialog({
    title: 'Pedido fuera de horario',
    message: data.aviso,
    ok: { label: 'Entendido', color: 'deep-orange-8', unelevated: true },
    persistent: true
  })
  return true
}
