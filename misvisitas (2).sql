-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-02-2022 a las 07:24:48
-- Versión del servidor: 10.4.17-MariaDB
-- Versión de PHP: 8.0.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sofia`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `misvisitas`
--

CREATE TABLE `misvisitas` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `lat` varchar(100) NOT NULL,
  `lng` varchar(100) NOT NULL,
  `distancia` varchar(100) NOT NULL,
  `cliente_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `misvisitas`
--

INSERT INTO `misvisitas` (`id`, `estado`, `fecha`, `hora`, `lat`, `lng`, `distancia`, `cliente_id`) VALUES
(1, 'PEDIDO', '2022-02-11', '23:56:24', '-17.970371', '-67.112303', '10', 1),
(2, 'PEDIDO', '2022-02-12', '01:54:46', '-17.9955029', '-67.1345173', '4088.6856450696', 2305),
(3, 'PARADO', '2022-02-12', '02:07:56', '-17.9954704', '-67.1344966', '3579.877740329', 1),
(4, 'NO PEDIDO', '2022-02-12', '02:09:50', '-17.9955029', '-67.1345173', '4427.7970011802', 1793),
(5, 'PARADO', '2022-02-12', '02:18:56', '-17.9954704', '-67.1344966', '4509.0829566713', 1794),
(6, 'NO PEDIDO', '2022-02-12', '02:19:04', '-17.9954704', '-67.1344966', '3622.4631488565', 1795),
(7, 'PARADO', '2022-02-12', '02:20:43', '-17.9955029', '-67.1345173', '3805.7722195725', 2052);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `misvisitas`
--
ALTER TABLE `misvisitas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `misvisitas`
--
ALTER TABLE `misvisitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
