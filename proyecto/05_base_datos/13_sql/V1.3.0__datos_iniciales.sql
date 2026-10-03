-- =====================================================================
-- Flyway V1.3.0__datos_iniciales.sql
-- Workflow 02 · Paso 15 · Datos iniciales (Paso 13 §1 F4)
--
-- ⚠ Esta migración solo inserta lo que está APROBADO en la fuente de verdad.
--   Ningún valor pendiente del cliente se materializa aquí (RC-03).
--
-- FUENTE: 01_requisitos/RF.md — RF-001.
--   "Administrar usuarios, roles y permisos (químico farmacéutico, auxiliar,
--    cajero, administrador, auditor)."
--
-- LO QUE DELIBERADAMENTE NO SE INSERTA (y por qué):
--   * `system_config`: todos los parámetros de RF-100 siguen en `null`
--     (perfil_carga.yaml + 02_configuracion). RNF-043 (modo degradado),
--     RNF-039 (retención), RNF-048 (trazabilidad), RPO/RTO, 25.000 txn/h,
--     método de verificación del químico (RNF-030) y DB-P01..DB-P12 están
--     pendientes de validación con el cliente. Inventarlos viola RC-03 y
--     AGENTS.md.
--   * `auth_permissions`: RF-001 no enumera permisos; no se inventa un catálogo.
--   * `ops_stores` / `ops_registers`: sucursales y cajas son datos de
--     operación, no de esquema.
--
-- Idempotente: re-ejecutable sin duplicar filas (INSERT IGNORE + UNIQUE).
-- =====================================================================

INSERT IGNORE INTO auth_roles (nombre, descripcion) VALUES
  ('químico farmacéutico', 'RF-001 · verificación de recetas y controlados (RF-054)'),
  ('auxiliar',             'RF-001 · apoyo de mostrador e inventario'),
  ('cajero',               'RF-001 · dispensación y cobro en caja'),
  ('administrador',        'RF-001 · administración del sistema y de usuarios'),
  ('auditor',              'RF-001 · lectura de auditoría y reportes regulatorios (RF-071)');
