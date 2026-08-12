<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model{
    use HasFactory;
//-- sofia.tbstock definition
//
//CREATE TABLE `tbstock` (
//`CodAut` int(8) NOT NULL AUTO_INCREMENT,
//`cod_prod` varchar(25) NOT NULL,
//`Cod_Prodm` varchar(25) NOT NULL,
//`Unidcant` int(5) NOT NULL,
//`UnidSaldo` int(5) NOT NULL,
//`cant` decimal(10,3) NOT NULL,
//`saldo` decimal(10,3) NOT NULL,
//`PBruto` decimal(10,3) NOT NULL,
//`PreUnit` decimal(10,2) NOT NULL,
//`CantCja` decimal(10,3) NOT NULL,
//`fecha` datetime NOT NULL,
//`fecha_venc` date NOT NULL,
//`Nro` int(3) NOT NULL,
//`AlmaOrig` int(3) NOT NULL,
//`CodStock` int(5) NOT NULL,
//`CodStockS` int(5) NOT NULL,
//`CodStockReg` int(5) NOT NULL,
//`ci` char(15) NOT NULL,
//`MotivoEgreso` char(120) NOT NULL,
//`NroLOte` varchar(50) NOT NULL,
//`comandast` int(10) NOT NULL,
//`Nrocierre` int(8) NOT NULL,
//`sw` int(2) NOT NULL,
//`codtrans` int(5) NOT NULL,
//`posic` int(3) NOT NULL,
//`motivstock` varchar(43) NOT NULL,
//`docum` varchar(10) NOT NULL,
//`esfac` int(1) NOT NULL,
//`proveedor` varchar(50) NOT NULL,
//PRIMARY KEY (`CodAut`),
//UNIQUE KEY `codAut` (`CodAut`),
//KEY `cod_prod` (`cod_prod`)
//) ENGINE=InnoDB AUTO_INCREMENT=70424 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
    protected $table = 'tbstock';
    protected $fillable = [
        'CodAut',
        'cod_prod',
        'Cod_Prodm',
        'Unidcant',
        'UnidSaldo',
        'cant',
        'saldo',
        'PBruto',
        'PreUnit',
        'CantCja',
        'fecha',
        'fecha_venc',
        'Nro',
        'AlmaOrig',
        'CodStock',
        'CodStockS',
        'CodStockReg',
        'ci',
        'MotivoEgreso',
        'NroLOte',
        'comandast',
        'Nrocierre',
        'sw',
        'codtrans',
        'posic',
        'motivstock',
        'docum',
        'esfac',
        'proveedor'
    ];

}
