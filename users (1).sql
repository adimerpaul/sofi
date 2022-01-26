-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-01-2022 a las 16:13:37
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
-- Base de datos: `comprobante`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechalimite` date NOT NULL DEFAULT '2021-12-31',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unid_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `codigo`, `email`, `fechalimite`, `email_verified_at`, `password`, `remember_token`, `unid_id`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'ADM', 'admin@test.com', '2022-12-31', NULL, '$2y$10$Epkl11Q8ZuxLcG7bv5uk0et5HjWG6EqDxwqDzGl/Mn5Q3ggnulYsa', NULL, 16, NULL, NULL),
(2, 'CELSO ACHOCALLE', 'CEL', 'c@t.com', '2021-12-31', NULL, '$2y$10$iI7Fj3AmkwOhTjMao6gO7uCa8Wjh4/1WXGdDqi7A8VCQESrXUTZYK', NULL, 3, NULL, NULL),
(3, 'cementerio', 'CEM', 'cementerio@gmail.com', '2022-03-02', NULL, '$2y$10$KOvd8aBh1xbJOmKytWj74eDIqaUIWBkLThG.VmAm9H5lXvUOkNHdC', NULL, 7, '2022-01-26 12:22:20', '2022-01-26 12:22:20'),
(4, 'ROSARIO MURGA', 'ROS', 'rosario@gmail.com', '2022-03-02', NULL, '$2y$10$vFKVJb.ROMmvpqrfPjzzNOTCIC1hcgnj8hg0fUZE8KUOdoR0PBNQi', NULL, 16, '2022-01-26 12:49:24', '2022-01-26 12:49:24'),
(5, 'cajero', 'CAJ', 'cajero@gmail.com', '2022-02-02', NULL, '$2y$10$V7PR.QmyMSrwpdF8B2VIi.RiFDzHoSaKMHukJsfv0WBoQTHmCYXMO', NULL, 17, '2022-01-26 14:59:42', '2022-01-26 14:59:42');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_unid_id_foreign` (`unid_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_unid_id_foreign` FOREIGN KEY (`unid_id`) REFERENCES `unids` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
