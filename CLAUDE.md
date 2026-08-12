# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

Sofia es un sistema de pedidos y distribución de pollo, embutidos y carnes. El núcleo del negocio es: ventas/pedidos, despacho, entregas, rutas, cobranzas y reportes (Excel/PDF). Hay AGENTS.md en la raíz, en `sofiback/` y en `sofifront/` con contexto de negocio adicional — leerlos antes de cambios grandes.

Monorepo con dos módulos:
- `sofiback/` — API backend en Laravel 8 (PHP 7.3/8), MySQL, auth con Sanctum.
- `sofifront/` — SPA en Quasar 2 / Vue 3 (Quasar CLI v3, webpack), con modos PWA, Capacitor y Cordova.

Advertencia: la raíz del repo contiene dumps SQL enormes (60–95 MB cada uno). No hacer búsquedas de texto sobre toda la raíz sin excluir `*.sql`, `sofiback/vendor/` y `sofifront/node_modules/`.

## Comandos

### Backend (`sofiback/`)
```bash
php artisan serve          # dev server en http://localhost:8000 (el front en dev apunta aquí)
php artisan test           # tests (PHPUnit)
vendor/bin/phpunit --filter NombreDelTest   # un solo test
php artisan tinker         # REPL
```
Config de BD en `.env` (`DB_DATABASE=sofia`); existe una segunda conexión configurada en `.env` — cuidado con cambios de datos.

### Frontend (`sofifront/`)
```bash
quasar dev                 # dev server (usa API http://localhost:8000/api/)
quasar build               # build SPA de producción
quasar build -m pwa        # build PWA
npm run lint               # ESLint sobre .js y .vue
```
No hay tests en el frontend. La URL de la API está hardcodeada en `quasar.conf.js` (`env.API`): dev → `http://localhost:8000/api/`, prod → `https://bsofia.tuprogam.com/api/`. El router usa modo `hash`. No desplegar el frontend automáticamente; solo cuando el usuario lo pida.

## Arquitectura

### Backend
- Toda la lógica de negocio vive en controladores "gordos" en `sofiback/app/Http/Controllers/` — casi no hay services ni lógica en modelos. Muchos controladores usan SQL crudo/Query Builder contra tablas legadas (ej. `tbpedidos`).
- `routes/api.php` es el mapa completo de la API: `/login` es público; el resto va dentro de un grupo `auth:sanctum`. Mezcla `Route::resource` con muchos endpoints POST de acción puntual (`/enviarpedidos`, `/insertcobro`, `/clonarpedido`, etc.).
- Controladores clave: `PedidoController` (ventas/pedidos/despacho, el más importante), `ExcelController` (reportes y exportaciones con PhpSpreadsheet), `RutaController`, `EntregaController`, `CobrarController` (cuentas por cobrar), `ClienteController`.
- PDFs con `barryvdh/laravel-dompdf`; montos en letras con `luecano/numero-a-letras`.

### Frontend
- Páginas en `sofifront/src/pages/`, una por flujo de negocio (Mispedidos, Despacho, Ruta, Entregas, Cobranza, Clientes, Reporte…). Rutas en `src/router/routes.js`.
- `src/boot/axios.js` crea la instancia `api` con `process.env.API` y la expone como `this.$api` (también `this.$url`); el token de Sanctum se valida contra `/me` al arrancar. Estado en Vuex (`src/store`).
- Reportes también se generan del lado cliente con `pdfmake`/`jspdf`/`json-as-xlsx`; mapas con Leaflet; notificaciones con Firebase (`src/boot/firebase.js`).

### Contrato front-back
El frontend depende de la forma exacta de los JSON que devuelven los controladores. Si se modifica una respuesta en el backend, buscar en `sofifront/src/pages/` qué vistas consumen ese endpoint antes de cambiarla. Al agregar un endpoint, seguir el patrón existente de `routes/api.php`.
