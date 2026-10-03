-- =====================================================================
-- Flyway V1.4.0__triggers_auditoria.sql
-- Workflow 02 · Paso 15 · Triggers de red de seguridad (Paso 10a §3)
--
-- Fuente: 08_auditoria/auditoria.md §3.
-- Ambos son AFTER INSERT y fallan la transacción (SIGNAL 45000) si la
-- aplicación omitió una regla que la BD ya debería garantizar.
-- Defensa en profundidad: NO sustituyen a los CHECK de V1.0.0 ni a las
-- transacciones de 11_transacciones_concurrencia.
--
-- ⚠ DELIMITER: el cuerpo de un trigger contiene `;`. El parser por defecto de
--   Flyway separa en `;` y cortaría el `END IF`. Se usa `DELIMITER //`, que la
--   documentación de Flyway para MySQL respeta explícitamente
--   (reference/database-driver-reference/mysql → "Flyway will respect prefixing
--   your script with the DELIMITER statement").
--   Respaldo si el runner no lo interpretara: aplicar este archivo con el
--   cliente `mysql` (ver README §6).
--
-- Se ejecuta con `migrator` (privilegio TRIGGER concedido en V1.2.0).
-- =====================================================================

-- ---------------------------------------------------------------------
-- trg_movements_sign · red de seguridad RN-02 / CA-03
--
-- Los CHECK de V1.0.0 garantizan `cantidad > 0` y `signo IN (-1,1)`, pero NO
-- que el signo concuerde con el tipo de movimiento. Este trigger añade esa
-- coherencia para los seis tipos direccionales.
--
-- `devolucion` y `compensatorio` quedan fuera: su dirección depende del
-- contexto de la operación (T-6 escribe `devolucion` sin tocar
-- `stock_available`; el compensatorio puede ser de cualquier sentido, RN-10).
-- ---------------------------------------------------------------------
DELIMITER //

CREATE TRIGGER trg_movements_sign
AFTER INSERT ON inventory_movements
FOR EACH ROW
  IF (NEW.cantidad <= 0)
     OR (NEW.signo NOT IN (-1, 1))
     OR (NEW.tipo IN ('entrada', 'ajuste_incremento', 'transferencia_entrada')
         AND NEW.signo <> 1)
     OR (NEW.tipo IN ('salida', 'ajuste_baja', 'transferencia_salida')
         AND NEW.signo <> -1)
  THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'inventory_movements: cantidad o signo incoherentes con el tipo de movimiento';
  END IF//

DELIMITER ;

-- ---------------------------------------------------------------------
-- trg_ledger_vs_stock · red de seguridad RF-055 / RF-045
--
-- Un asiento de salida en el libro de controlados debe tener su movimiento de
-- inventario correspondiente en la MISMA transacción (RNF-021).
-- Coincide por (ref_tipo, ref_id): ambos apuntan al documento de origen.
--
-- Orden de escritura garantizado por 11_transacciones_concurrencia §4:
-- `inventory_movements` (paso 4) se escribe ANTES que `ctrl_ledger_entries`
-- (paso 5), por lo que la fila ya es visible dentro de la transacción.
-- ---------------------------------------------------------------------
DELIMITER //

CREATE TRIGGER trg_ledger_vs_stock
AFTER INSERT ON ctrl_ledger_entries
FOR EACH ROW
  IF NEW.tipo = 'salida'
     AND NOT EXISTS (
       SELECT 1
         FROM inventory_movements m
        WHERE m.ref_tipo = NEW.ref_tipo
          AND m.ref_id   = NEW.ref_id
     )
  THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'ctrl_ledger_entries: asiento de salida sin movimiento de inventario correspondiente';
  END IF//

DELIMITER ;
