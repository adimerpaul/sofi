<?php

namespace App\Console;

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
        $schedule->call(function () {
            DB::SELECT("UPDATE tbclientes set venta='ACTIVO'");

            DB::SELECT("UPDATE tbclientes set venta='INACTIVO'
            where Id not in ('4041584010','5722359015','7903071014','7313393','2763010019','387115028','7279536013','6656467','2773242015','3509547','5720977','7205489','3501059017','3544875019','2762953013','4034692','8560810','3513987','168266022','341104028','5068381','4525672011','370194024','8025247') and
              ((SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda) )
                FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>5 )>12000
            or (SELECT DATEDIFF( curdate(), (select min(c.FechaEntreg) from tbctascobrar c where c.CINIT =tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5)))>9 )");

        })->weekdays()->dailyAt(9,15);

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
