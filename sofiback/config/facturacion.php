<?php

return [

    /*
    | Si caja solo puede imprimir facturas y vouchers de un camion despues de
    | que el caminero verifico su carga en /caminero/carga.
    |
    | Apagado mientras la verificacion esta a prueba: se imprime sin esperar.
    | Para volver a exigirla: FACTURACION_EXIGIR_CARGA=true en el .env (y
    | php artisan config:cache si la configuracion esta cacheada).
    */
    'exigir_carga_verificada' => env('FACTURACION_EXIGIR_CARGA', false),

];
