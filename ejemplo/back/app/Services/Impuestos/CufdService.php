<?php

namespace App\Services\Impuestos;

use App\Models\Cufd;
use App\Models\Cui;

class CufdService
{
    public function generateCufdDaily(int $codigoPuntoVenta = 0, int $codigoSucursal = 0): array
    {
        $existing = Cufd::query()
            ->where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->latest('id')
            ->first();

        if ($existing) {
            return [
                'status' => 'skipped',
                'message' => 'El CUFD vigente ya existe para hoy',
                'cufd' => $existing,
            ];
        }

        $cui = $this->ensureCui($codigoPuntoVenta, $codigoSucursal);
        $cufd = $this->createCufd($cui, $codigoPuntoVenta, $codigoSucursal);

        return [
            'status' => 'created',
            'message' => 'CUFD creado correctamente',
            'cufd' => $cufd,
        ];
    }

    private function ensureCui(int $codigoPuntoVenta, int $codigoSucursal): Cui
    {
        $cui = Cui::query()
            ->where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->latest('id')
            ->first();

        if ($cui) {
            return $cui;
        }

        $client = $this->siatSoapClient('FacturacionCodigos?WSDL');
        $result = $client->cuis([
            'SolicitudCuis' => [
                'codigoAmbiente' => env('AMBIENTE'),
                'codigoModalidad' => env('MODALIDAD'),
                'codigoPuntoVenta' => $codigoPuntoVenta,
                'codigoSistema' => env('CODIGO_SISTEMA'),
                'codigoSucursal' => $codigoSucursal,
                'nit' => env('NIT'),
            ],
        ]);

        if (!isset($result->RespuestaCuis->codigo)) {
            throw new \RuntimeException('SIAT no devolvio un CUI valido');
        }

        return Cui::query()->create([
            'codigo' => $result->RespuestaCuis->codigo,
            'fechaVigencia' => date('Y-m-d H:i:s', strtotime($result->RespuestaCuis->fechaVigencia)),
            'fechaCreacion' => now()->format('Y-m-d H:i:s'),
            'codigoPuntoVenta' => $codigoPuntoVenta,
            'codigoSucursal' => $codigoSucursal,
        ]);
    }

    private function createCufd(Cui $cui, int $codigoPuntoVenta, int $codigoSucursal): Cufd
    {
        $client = $this->siatSoapClient('FacturacionCodigos?WSDL');
        $result = $client->cufd([
            'SolicitudCufd' => [
                'codigoAmbiente' => env('AMBIENTE'),
                'codigoModalidad' => env('MODALIDAD'),
                'codigoPuntoVenta' => $codigoPuntoVenta,
                'codigoSistema' => env('CODIGO_SISTEMA'),
                'codigoSucursal' => $codigoSucursal,
                'cuis' => $cui->codigo,
                'nit' => env('NIT'),
            ],
        ]);
        error_log(json_encode($result));

        if (!isset($result->RespuestaCufd->codigo) || !isset($result->RespuestaCufd->codigoControl)) {
            throw new \RuntimeException('SIAT no devolvio un CUFD valido');
        }

        return Cufd::query()->create([
            'codigo' => $result->RespuestaCufd->codigo,
            'codigoControl' => $result->RespuestaCufd->codigoControl,
            'fechaVigencia' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'fechaCreacion' => now()->format('Y-m-d H:i:s'),
            'codigoPuntoVenta' => $codigoPuntoVenta,
            'codigoSucursal' => $codigoSucursal,
        ]);
    }

    private function siatSoapClient(string $path): \SoapClient
    {
        return new \SoapClient(env('URL_SIAT') . $path, [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => 'apikey: TokenApi ' . env('TOKEN'),
                ],
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
    }
}
