# ADR-001 · Arquitectura inicial del sistema de gestión de farmacia

**Estado:** Aceptado (2026-10-02, CAM-006)
**Fecha:** 2026-09-28
**Decisor:** cliente (aprobación), solution-leader (paso 8) — arquitecto de soluciones como proponente
**Contexto:** `proyecto/03_resultados/` (01…07) e `informe_arquitectura.md` (AR-01…AR-08)
**RC-01:** este ADR no nombra ningún producto, motor, framework, nube ni proveedor.

## Contexto

El sistema debe controlar inventario de medicamentos por lote (FEFO, bloqueo de vencidos,
cuarentena y retiros) y registrar dispensación con trazabilidad de recepción a entrega, en ~8
sucursales y ~48 cajas (~180 usuarios), con volumen declarado de 25.000 txn/h **a validar (RC-03)**,
y con 10x (CA-06) como escenario de diseño. Los radios duros: sin stock negativo, sin doble
descuento, libro de control con saldo permanente de controlados (RNF-021, RN-05/RN-06, RC-06),
auditoría inalterable y modo degradado con conciliación al reconectar (RF-047, DEC-07).

## Decisión

Se adopta la arquitectura descrita en `08_final_recommendation.md` **§3** (Arquitectura
recomendada) como base inicial:

- **DEC-04 → A:** una base de registro con atomicidad multi-registro para inventario, libro,
  recetas y auditoría. C (almacén de solo lectura) queda como **camino de crecimiento** sujeto a
  P-02/P-01; B (partición obligatoria) solo con prueba de contención a 10x.
- **DEC-02 → 2-b + 2-a:** verificación optimista con control de versión por lote (rechazo y
  reintento) y serialización exclusiva como cierre del lote más disputado; el saldo global de
  receta (RF-053) usa el mismo mecanismo con su propia prueba D-15.
- **DEC-02x → opción A (preliminar):** doble autorización de ajustes/bajas de controlados dentro
  de una única transacción, `autorizante ≠ proponente` (RF-046, RN-05, RN-06, RC-06, RNF-031);
  pendiente de validación de architect y químico.
- **DEC-03 → 3-a:** clave de idempotencia persistida para cobro, dispensación, recepción y
  transferencia; 3-c (identidad de negocio) queda **sin cerrar** hasta P-16 (ventana de claves).
- **DEC-05 → A:** historial con índices como fuente de verdad; proyecciones derivadas solo como
  optimización reconstruible (CA-10).
- **DEC-07 → 7-A/7-B/7-C:** componente mínimo (caché + cola en sucursal/caja) para el modo
  degradado y cola de escritura local con conciliación exacta al reconectar; P-03 y P-15 siguen
  abiertos.
- **DEC-08:** el 10x se absorbe escalando la misma base, no añadiendo complejidad (CA-09).
- **DEC-10/DEC-11/DEC-12:** respaldo con prueba de restauración (RNF-040), corte de API en la
  puerta de seguridad y extensibilidad de medios de pago como dependencia externa.

## Supuestos decididos

| # | Supuesto | Dueño / quién lo cierra |
|---|---|---|
| S-1 | 25.000 txn/h (base) — `a validar`, RC-03 | Cliente (P-06) |
| S-2 | P-16: ventana de retención de claves de idempotencia = `null` → 3-c no cerrado | Cliente + database-specialist |
| S-3 | P-17: saldo de receta no está en la enumeración de RNF-021 → se modela sin editar RNF (RC-02) | Cliente + requirements-analyst → architect |
| S-4 | P-03: ¿modo degradado habilitado y hasta dónde? | Cliente + químico |
| S-5 | P-05/P-01: p95 y RPO/RTO = `null` → sin umbral de latencia ni de recuperación | Cliente |
| S-6 | AR-05: alcance de P-03 (perimétrico a DEC-07) | Cliente |
| S-7 | AR-06: ancla de D-15 como supuesto decidido | solution-leader |
| S-8 | AR-07: extensión V-02 del perfil de carga | Cliente (arquitecto) |
| S-9 | AR-08: excepción de devoluciones (RF-100 como parámetro, P-08) | Cliente |

## Condiciones de producción

- **CA-12:** sin hallazgos bloqueantes en la ronda final de architecture-reviewer.
- **H-01 / H-02** (`05_security_review.md`): cifrado y gestión de claves verificados, roles con
  MFA (CAM-003) antes de exponer servicios administrativos.

## Alternativas descartadas

- **ALT-3 — base particionada desde el día 1:** coordinación distribuida en RF-045/RF-053 sin
  justificación medible (CA-09).
- **ALT-4 — almacén en cada sucursal como fuente:** propuesta de 10-b no validada; el modo
  degradado se resuelve con componente mínimo + cola (DEC-07), no con soberanía total local.
- **ALT-5 — proyecciones como fuente de lectura:** arriesga RN-02 con lecturas atrasadas en venta.

## Consecuencias

- **Positivas:** una sola frontera transaccional sostiene RN-02/RN-03/RNF-021 sin mecanismos
  especiales; complejidad mínima cumple CA-09; el 10x se absorbe escalando (DEC-08); trazabilidad
  CA-10 demostrable por camino de acceso e índices.
- **Negativas (riesgos R-01…R-11 de `03 §6`):** contención en el lote más disputado (R-01,
  probabilidad Media, severidad ALTA), acumulación de auditoría (R-04), modo degradado como
  fuente de divergencia (R-11), retención de claves sin plazo (P-16) y dependencias de respuestas
  del cliente (P-01, P-02, P-05, P-06).
- **Neutrales:** ADR-002 (stack) queda **bloqueado** hasta que este ADR sea aceptado.

## Condiciones de revisión

1. Se reabre si P-01/P-02 imponen RPO/RTO o retenciones incompatibles con la opción A.
2. Se reabre si CA-12 falla en la ronda final de architecture-reviewer.
3. Se reabre si la prueba C1–C4 de `04 §5` detecta cualquier stock negativo o salida de lote
   bloqueado.
4. Se reabre si el cliente responde P-17 con modificación de RNF (reabre 01→02→03).

## Referencias

- `03_resultados/08_final_recommendation.md` — recomendación final y condiciones.
- `03_resultados/09_validation_plan.md` — plan de validación F0–F4.
- `03_resultados/informe_arquitectura.md` — AR-01…AR-08 y sus cierres.
- `00_contexto/registro_cambios.md` — CAM-001…CAM-004.

## Registro de correcciones

1. **ARR-05:** la línea de Decisión remitía al propio ADR (autoreferencia circular); ahora remite a `08_final_recommendation.md` **§3** (Arquitectura recomendada). **Por qué:** la autoreferencia impedía localizar la descripción adoptada desde la decisión. **Quién aprueba:** dueño del ADR (solution-leader), con architecture-reviewer en F0. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
2. **F0-01 (ronda F0):** las 4 referencias de este ADR a archivos pre-renombre (`Decisión:20`, `Referencias:92-93` y `Registro de correcciones:99`) pasan a citar `08_final_recommendation.md` y `09_validation_plan.md`, que son los nombres que existen en disco. **Qué:** solo el nombre del archivo citado; el sentido de cada frase (adopción de la §3, listado de referencias, registro ARR-05) queda intacto. **Por qué:** los nombres antiguos remitían a archivos inexistentes y rompían la trazabilidad decisión→evidencia (CA-01, CA-12). **Quién aprueba:** solution-leader (dueño del ADR); criterio de cierre = grep de nombres pre-renombre en `03_resultados/` sin resultados sobre este archivo. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
