<template>
  <Visita semana/>
</template>

<script>
import Visita from 'pages/Visita.vue'

// Clientes del Dia: la misma pantalla de Visita, con sus reglas del pedido
// (FRIAL solo por unidad, switch de rango, tipos de pago segun el cliente,
// precios del cliente), pero eligiendo el dia de la semana para adelantar
// pedidos. Cualquier cambio en Visita se ve tambien aca.
export default {
  name: 'Clientevisita',
  components: {Visita}
}
</script>
