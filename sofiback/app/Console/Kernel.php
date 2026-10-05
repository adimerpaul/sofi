<?php

namespace App\Console;

use App\Services\DeudaCliente;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//         $schedule->command('inspire')->hourly();
        $schedule->command('pedidos:enviar-automatico')->everyMinute();
        // Bloqueo por deuda de cobranzas/creditos (regla en DeudaCliente::bloquear)
        $schedule->call(function () {
            DeudaCliente::bloquear();
        })->cron('0 9,15,18 * * 1-5');

        $schedule->call(function () {

            $fecha1 = date('Y-m-d');

            $fecha1 = date('Y-m-d', strtotime($fecha1 . ' -1 day'));
            error_log($fecha1);

            $sql="UPDATE tbctascow c set c.estado='ENVIADO' WHERE c.estado='CREADO' and date(c.fecha)='$fecha1' and (SELECT cl.venta from tbclientes cl where cl.Id=c.idCli)='ACTIVO'";
            error_log($sql);
        DB::SELECT($sql);
       /* $cont="(SELECT w.comanda,w.pago as Acuenta,0 as Importe,99999 as Nrocierre,idCli as CINIT,fecomanda as FechaEntreg  FROM tbctascow w WHERE date(w.fecha)='$fecha1' union
        select t.comanda,t.Importe,t.Acuenta,Nrocierre,CINIT,t.FechaEntreg from tbctascobrar t )";

        DB::SELECT(" UPDATE tbclientes set venta='ACTIVO'
    where(SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from $cont c2 where c2.comanda=c.comanda) )
    FROM $cont c
    WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from $cont c2 where c2.comanda=c.comanda))>5 )<7000
         ");*/
        })->daily();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
