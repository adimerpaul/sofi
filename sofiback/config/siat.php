<?php

/*
|--------------------------------------------------------------------------
| SIAT / Impuestos Nacionales
|--------------------------------------------------------------------------
|
| Datos del emisor y URLs del SIAT. Estaban hardcodeados en el controlador
| de facturas; aca se pueden cambiar por entorno sin tocar codigo.
|
| Los valores por defecto son los de produccion de ALMACEN SOFIA. Para
| apuntar al ambiente piloto basta con definir SIAT_URL_CONSULTA en el .env.
|
*/

return [

    // NIT del emisor; va en el QR y en la cabecera de la factura.
    'nit' => env('SIAT_NIT', '3779602010'),

    // Portal donde el cliente verifica la factura. Es lo que codifica el QR.
    // Produccion: https://siat.impuestos.gob.bo/consulta/QR
    // Piloto:     https://pilotosiat.impuestos.gob.bo/consulta/QR
    'url_consulta' => env('SIAT_URL_CONSULTA', 'https://siat.impuestos.gob.bo/consulta/QR'),

    // Servicios web del SIAT (emision, anulacion, CUFD...). Todavia no se usan
    // desde este backend; queda declarado para cuando se emita desde aca.
    // Produccion: https://siatrest.impuestos.gob.bo/
    // Piloto:     https://pilotosiatservicios.impuestos.gob.bo/v2/
    'url_servicios' => env('SIAT_URL_SERVICIOS', 'https://siatrest.impuestos.gob.bo/'),

];
