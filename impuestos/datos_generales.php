<?php

declare(strict_types=1);

date_default_timezone_set('America/La_Paz');

/**
 * Configuracion SIAT centralizada.
 *
 * MODALIDAD DEL SISTEMA: 2 = COMPUTARIZADA EN LINEA (codigoEmision 1).
 * Los XML deben usar la raiz <facturaComputarizadaCompraVenta> y validarse
 * contra facturaComputarizadaCompraVenta.xsd. En esta modalidad el SIAT no
 * exige firma digital XMLDSig: por eso firmar() esta desactivado en los flujos.
 *
 * Empresa activa por defecto: 'sofia' (ALMACEN SOFIA, Oruro) en AMBIENTE 1 = PRODUCCION.
 *
 * ATENCION: los scripts *.php tienen las URL del ambiente PILOTO hardcodeadas
 * (pilotosiatservicios.impuestos.gob.bo). Para operar en produccion hay que
 * reemplazarlas por obtenerUrlSiat($servicio) o por los endpoints de siatrest.
 */

const SIAT_EMPRESA_ACTIVA = 'sofia';

/**
 * Endpoints por ambiente (equivale a la tabla `tburls` del sistema Aron-9).
 * codigoAmbiente 1 = produccion, 2 = piloto/pruebas.
 */
function obtenerUrlSiat(string $servicio, ?int $codigoAmbiente = null): string
{
    if ($codigoAmbiente === null) {
        $codigoAmbiente = obtenerDatosSiat(0)['codigoAmbiente'];
    }

    $base = $codigoAmbiente === 1
        ? 'https://siatrest.impuestos.gob.bo/v2/'
        : 'https://pilotosiatservicios.impuestos.gob.bo/v2/';

    return $base . $servicio . '?WSDL';
}

/**
 * @param int         $codigoPuntoVenta Punto de venta configurado para la empresa.
 * @param string|null $empresa          Clave de empresa; null usa SIAT_EMPRESA_ACTIVA.
 */
function obtenerDatosSiat(int $codigoPuntoVenta, ?string $empresa = null): array
{
    $empresa = $empresa ?? SIAT_EMPRESA_ACTIVA;

    $empresas = [

        /* ------------------------------------------------------------------
         * ALMACEN SOFIA - NIT 3779602010 - Oruro - PRODUCCION
         * Distribuidor autorizado de SOFIA en Oruro.
         * Domicilio legal: calle Campo Jordan y Av. Tacna Nro. 28, Zona Norte.
         * Segundo local: calle Juan de Somoza Nro. 3854, Zona Magisterio UV 40 MZA 60.
         * Token JWT: emitido 2026-04-06, expira 2027-04-06 (nitDelegado 3102229014).
         * Certificado asociado: ../SIAT/electronic/CARLOS_TANTACHUCO_QUEVEDO.pfx
         * ------------------------------------------------------------------ */
        'sofia' => [
            'razonSocial' => 'ALMACEN SOFIA',
            'nit' => '3779602010',
            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJoZWxlbmVkaXRodG9ycmV6cHJpYWxldEBnbWFpbC5jb20iLCJjb2RpZ29TaXN0ZW1hIjoiMzcxRjU0NUJFQkIxOEZFQ0QyMTciLCJuaXQiOiJINHNJQUFBQUFBQUFBRE0yTjdjME16QXlNRFFBQUNINEluVUtBQUFBIiwiaWQiOjUyNzI0MzAsImV4cCI6MTgwNzAwNzAxNywiaWF0IjoxNzc1NDg1Mzg3LCJuaXREZWxlZ2FkbyI6MzEwMjIyOTAxNCwic3Vic2lzdGVtYSI6IlNGRSJ9.Ddfbk63oh-oSXC0_ftwPEStpKO3TnCJQqKU7mg0FEMjjN2Bof1Yy7fAVkR8SxvtilAlOW4zsS8lntUSAmJZMYg',
            'codigoAmbiente' => 1,   // 1 = PRODUCCION
            'codigoSistema' => '371F545BEBB18FECD217',
            'codigoSucursal' => 0,
            'codigoModalidad' => 2,  // 2 = COMPUTARIZADA EN LINEA
            'puntosVenta' => [
                // CUIS B76DDAAB: vigente hasta 2027-04-07 segun SIAT/CUIS.xml.
                // CUFD y codigoControl CADUCAN CADA 24 H: los valores de abajo son
                // el ultimo estado conocido y YA ESTAN VENCIDOS. Regenerar con
                // GenerateCuf.php antes de emitir.
                0 => [
                    'cuis' => 'C5893FCC',
                    'cufd' => '',                // pendiente: regenerar
                    'codigoControl' => '',       // pendiente: regenerar
                ],
                1 => [
                    'cuis' => 'C5893FCC',
                    'cufd' => '',                // pendiente: regenerar
                    'codigoControl' => '',       // pendiente: regenerar
                ],
                2 => [
                    'cuis' => 'B76DDAAB',
                    // Ultimo CUFD observado (SIAT/CUFD.xml, vigencia 2026-06-21): VENCIDO
                    'cufd' => 'FBQUFCQ34qREE=IxOEZFQ0QyMTc=Q1VpWXhKVkdhVUMzcxRjU0NUJFQk',
                    // codigoControl usado en SIAT/CUF.bat el 2026-06-30: VENCIDO
                    'codigoControl' => '6AEEBD0B8FEAF74',
                ],
            ],
        ],

        /* ------------------------------------------------------------------
         * Configuracion anterior de este archivo. NIT distinto, ambiente PILOTO.
         * Se conserva para no perderla (este directorio no tiene control de versiones).
         * ------------------------------------------------------------------ */
        'piloto_anterior' => [
            'razonSocial' => 'PRUEBAS PILOTO',
            'nit' => '5062436018',
            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJjaGljaGFuYXR5QGdtYWlsLmNvbSIsImNvZGlnb1Npc3RlbWEiOiIzNzFGOTk2OTJEQTBERDRENDFERSIsIm5pdCI6Ikg0c0lBQUFBQUFBQUFETTFNRE15TVRZek1MUUFBSFYxSVZrS0FBQUEiLCJpZCI6NTIyNzQ1NSwiZXhwIjoxODA0ODMxMzQ1LCJpYXQiOjE3NzMzOTYxMTUsIm5pdERlbGVnYWRvIjo1MDYyNDM2MDE4LCJzdWJzaXN0ZW1hIjoiU0ZFIn0.Bf-_BNKpY_-AJyRvep6H_q3S_Eaqpm4xpalEe9vBY9AcojCVBA8tWlYNwpo8W_UVyRRGOO_rWJaKKES39XiEKA',
            'codigoAmbiente' => 2,   // 2 = PILOTO
            'codigoSistema' => '371F99692DA0DD4D41DE',
            'codigoSucursal' => 0,
            'codigoModalidad' => 2,
            'puntosVenta' => [
                0 => [
                    'cuis' => 'F498F4FF',
                    'cufd' => 'VBQUE+QmtZR0ZBEwREQ0RDQxREU=Q8KhMm1MRktFYVMzcxRjk5NjkyRE',
                    'codigoControl' => '69E94F741CBAF74',
                ],
                1 => [
                    'cuis' => '6ED780F4',
                    'cufd' => 'JBQT5Ca1lHRkE=EwREQ0RDQxREU=Q3xud0tGS0VhVUMzcxRjk5NjkyRE',
                    'codigoControl' => '9FC9ED741CBAF74',
                ],
            ],
        ],
    ];

    if (!isset($empresas[$empresa])) {
        throw new InvalidArgumentException('Empresa no configurada: ' . $empresa);
    }

    $config = $empresas[$empresa];

    if (!isset($config['puntosVenta'][$codigoPuntoVenta])) {
        throw new InvalidArgumentException(
            'Punto de venta no configurado para ' . $empresa . ': ' . $codigoPuntoVenta
        );
    }

    $puntoVenta = $config['puntosVenta'][$codigoPuntoVenta];

    return [
        'razonSocial' => $config['razonSocial'],
        'nit' => $config['nit'],
        'token' => $config['token'],
        'codigoAmbiente' => $config['codigoAmbiente'],
        'codigoSistema' => $config['codigoSistema'],
        'codigoSucursal' => $config['codigoSucursal'],
        'codigoModalidad' => $config['codigoModalidad'],
        'codigoPuntoVenta' => $codigoPuntoVenta,
        'cuis' => $puntoVenta['cuis'],
        'cufd' => $puntoVenta['cufd'],
        'codigoControl' => $puntoVenta['codigoControl'],
    ];
}
