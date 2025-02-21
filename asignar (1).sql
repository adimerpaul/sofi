-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-01-2022 a las 19:13:51
-- Versión del servidor: 10.4.18-MariaDB
-- Versión de PHP: 8.0.3

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
-- Estructura de tabla para la tabla `asignar`
--

CREATE TABLE `asignar` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(25) COLLATE utf8mb4_swedish_ci NOT NULL DEFAULT 'PENDIENTE',
  `hora` time NOT NULL,
  `lat` varchar(120) COLLATE utf8mb4_swedish_ci NOT NULL,
  `lng` varchar(120) COLLATE utf8mb4_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

--
-- Volcado de datos para la tabla `asignar`
--

INSERT INTO `asignar` (`id`, `cliente_id`, `usuario_id`, `fecha`, `estado`, `hora`, `lat`, `lng`) VALUES
(3, 1, 1, '2022-01-27', 'PENDIENTE', '01:00:00', '', '');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignar`
--
ALTER TABLE `asignar`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignar`
--
ALTER TABLE `asignar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
