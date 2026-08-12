# AGENTS.md

## Modulo
`sofifront` es el frontend del sistema Sofia. Esta construido con Quasar y es la capa visual para ventas, pedidos, cobranzas, rutas, despacho y reportes.

## VAC
### Vision
La interfaz debe facilitar la operacion diaria del negocio y priorizar velocidad de uso en procesos de venta, consulta y reporte.

### Alcance
Aqui viven:
- paginas y layouts;
- router del frontend;
- consumo de la API del backend;
- vistas de ventas, reportes, rutas, clientes, despacho y cobranzas.

### Contexto
Este frontend depende del backend Laravel del repositorio. Cualquier cambio visual o funcional debe respetar los endpoints y estructuras devueltas por la API.

## Stack
- Framework principal: Quasar
- Base del front: Vue 3 con estructura Quasar del proyecto actual
- Router: `src/router/routes.js`
- Configuracion general: `quasar.conf.js`

## Prioridades Funcionales
- Lo principal del sistema es la venta.
- Lo segundo mas critico son los reportes.
- Tambien son importantes rutas, despacho, entregas, clientes y cobranzas.

## Rutas Del Frontend
Revisar primero `src/router/routes.js` antes de mover paginas o renombrar vistas.

Rutas funcionales clave:
- `/mispedidos`
- `/mispedidostotales`
- `/reporte`
- `/genreporte`
- `/ruta`
- `/entrega`
- `/despacho`
- `/clientes`
- `/cobranza`
- `/pedidos`
- `/productos`

## Instrucciones Para Agentes
- Preservar la navegacion existente y validar cualquier cambio en `src/router/routes.js`.
- Si una pantalla consume datos de ventas o reportes, validar el contrato con el backend antes de editar componentes.
- No cambiar el despliegue por defecto sin instruccion explicita.
- Este frontend no debe desplegarse automaticamente; el despliegue debe hacerse solo cuando el usuario lo solicite.
- Si se agrega una nueva pantalla, documentar su ruta y el endpoint backend que consume.
