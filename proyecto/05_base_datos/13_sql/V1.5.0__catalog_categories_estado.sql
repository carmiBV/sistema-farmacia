-- =====================================================================
-- Flyway V1.5.0__catalog_categories_estado.sql
-- CP-API-07 (OPEN-API-01, aprobado por el cliente 2026-10-04)
-- Contexto: catalog_categories no tenia columna de estado y el borrado
--           logico exigido por decisiones_api.md / CP-API-07
--           (estado='inactivo', DELETE FROM prohibido) era imposible.
-- Version: V1.5.0 (siguiente en la secuencia V1.0.0..V1.4.0 del plan;
--          "V1.0.1" quedaria fuera de orden y Flyway lo rechaza).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE catalog_categories
  ADD COLUMN estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo';
