<?php

/*
|--------------------------------------------------------------------------
| SIAT / Impuestos Nacionales
|--------------------------------------------------------------------------
|
| Semilla del modulo de Impuestos. La configuracion que manda en tiempo de
| ejecucion es la fila de la tabla `siat_configuraciones`, que se edita desde
| la pantalla /impuestos; esto solo la crea la primera vez y sirve de respaldo
| si alguien borrara la fila.
|
| Los valores por defecto son los de produccion de ALMACEN SOFIA. Desde este
| sistema no se factura contra el ambiente piloto.
|
*/

return [

    // Razon social del emisor, tal como esta registrada en el SIAT.
    'razon_social' => env('SIAT_RAZON_SOCIAL', 'ALMACEN SOFIA'),

    // NIT del emisor; va en el QR y en la cabecera de la factura.
    'nit' => env('SIAT_NIT', '3779602010'),

    // Las dos direcciones del SIAT, que no son la misma cosa:
    //
    //   url_siat  -> servicios (CUIS, CUFD, envio de facturas).
    //   url_siat2 -> portal publico; es la que se codifica en el QR impreso.
    'url_siat' => env('URL_SIAT', 'https://siatrest.impuestos.gob.bo/v2/'),
    'url_siat2' => env('URL_SIAT2', 'https://siat.impuestos.gob.bo/'),

    // 1 = ELECTRONICA EN LINEA, 2 = COMPUTARIZADA EN LINEA.
    'modalidad' => (int) env('SIAT_MODALIDAD', 2),

    // Codigo que el SIAT asigna al sistema de facturacion registrado.
    'codigo_sistema' => env('SIAT_CODIGO_SISTEMA', '371F545BEBB18FECD217'),

    'codigo_sucursal' => (int) env('SIAT_CODIGO_SUCURSAL', 0),
    'codigo_punto_venta' => (int) env('SIAT_CODIGO_PUNTO_VENTA', 0),

    // Token delegado (JWT) que da el SIAT. Es un secreto: va solo en el .env,
    // nunca en el repo. Desde aqui se copia una vez a la base de datos.
    'token' => env('SIAT_TOKEN', ''),

    // Cabecera que se imprime en vouchers y facturas. Estaba repetida dentro
    // del HTML de FacturaFiscalController; aca se cambia en un solo sitio.
    'emisor' => [
        'nombre'    => env('EMISOR_NOMBRE', 'ALMACEN SOFIA'),
        'sucursal'  => env('EMISOR_SUCURSAL', 'SUCURSAL 1'),
        'direccion' => env('EMISOR_DIRECCION', 'Prolongacion Campo Jordan esq Tacna Nro 28 ZONA Norte'),
        'telefono'  => env('EMISOR_TELEFONO', '5230064'),
        'ciudad'    => env('EMISOR_CIUDAD', 'ORURO'),
    ],

];
