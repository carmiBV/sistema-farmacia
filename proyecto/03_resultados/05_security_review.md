# 05 — Revisión de seguridad, privacidad, auditoría y retención (security-reviewer)

> Rol: **security-reviewer** (paso 5 del pipeline de `AGENTS.md`). Dueño de **DEC-06** y **DEC-09**; **valida DEC-11**.
> Restricciones: RC-01 (sin productos ni stack), RC-02 (RF/RNF/RN intactos), RC-03 (no inventar plazos, normativa ni RPO/RTO: P-01/P-02 abiertas). **No decide** DEC-04/DEC-05/DEC-08/DEC-10.
> **Ninguna decisión está cerrada**: todo es recomendación preliminar sujeta a **asesoría legal/cliente** y **ADR final**.

## 1. Fuentes y alcance

| Fuente | Qué se usa |
|---|---|
| `04_database_analysis.md` | §2.3 (persistencia local del modo degradado, DEC-07), §4/§5 (historial, retención `null`, RF-091/`Q`), §7 (insumos ya enviados) |
| `03_architecture_options.md` | §4 DEC-11 (corte 11-a/11-b), §7 (10 propiedades), escenarios 7-A/7-B/7-C |
| `02_decision_scope.md` | DEC-06, DEC-09, DEC-11 (=2 alternativas, validadores), P-01/P-02/P-06/P-07/P-14/P-15 |
| `01_requirements_analysis.md` | D-05, D-06, D-14, R-06, R-08, R-10, I-11, I-13, I-14 |
| `RNF.md` | RNF-023/025/030…039, RNF-042, RNF-046, RNF-047, RNF-050, RNF-052, RNF-053 |
| `RF.md` | RF-047/RF-053 (7-C), RF-054 (verificación del químico), RF-090/RF-091 (auditoría) |
| `criterios_aceptacion.md` | CA-08 (seguridad, privacidad, observabilidad, recuperación), CA-11 (datos sensibles y retención) |
| `00_contexto` | RN-10 (correcciones compensatorios), RN-13 (PII con registro de cada consulta), CAM-002-g (pendiente de aprobación) |

## 2. DEC-06 — Estrategia de seguridad y privacidad (dueño)

Drivers: D-06, RN-13, RNF-030…RNF-039, CA-08, CA-11. Decisor: cliente + asesoría legal.

### 2.1 Identidad: **A) gestión interna** vs **B) delegada** (comparación por propiedades)

> **Análisis sin conexión (7-B/7-C, RC-09):** con el centro caído, el puesto debe seguir autenticando y verificando al químico; se analiza aparte por su impacto en la recomendación.

| Propiedad | A — identidad gestionada internamente (el sistema implementa credenciales, MFA, sesiones) | B — identidad delegada (capa externa de identidad; sin nombrar producto, RC-01) |
|---|---|---|
| MFA en login para administrador/auditor/químico (RNF-030, I-13) | El propio sistema debe generar, entregar y validar factores → más superficie en el puesto | Capacidad incorporada de la capa delegada; la farmacia solo consume el resultado |
| Verificación en dispensación (reautenticación o PIN) **sin definir** (CAM-003-a/b) | La elección condiciona toda la implementación interna | La elección condiciona qué exige la integración; **ambas opciones quedan abiertas** (P-14) |
| **Autenticación/MFA en login sin conexión** (7-B/7-C, RC-09) | La validación puede resolverse **en el puesto** contra credenciales locales; la degradación del login se rige por la política P-03 (no inventada aquí) | La capa delegada necesita red: sin ella el login queda fuera (**RNF-042**: timeout y reintentos controlados) o exige caché/validación local **declarada** |
| **Verificación del químico sin conexión** (RF-054: bajo receta y controlados, 7-B/7-C) | Si el método es local (p. ej. PIN), verificable en el puesto | Si la verificación exige llamar a la capa delegada, la dispensación bajo receta/controlada **queda bloqueada en degradado** (7-B y, si P-15 lo permite, 7-C) |
| Expiración/cierre por inactividad en POS (RNF-037) | Control total de la ventana de sesión (R-08: sesiones de 5 min vs fricción) | Depende de lo que la capa delegada exponga; debe comprobarse que soporta ventana corta de POS |
| Contraseñas nunca en texto plano (RNF-033) | Responsabilidad interna de hash adaptativo + rotación | La capa delegada absorbe hash/rotación; verificar exigencia mínima (algoritmo, reutilización) |
| Secretos fuera del repositorio (RNF-034) | Solo secretos internos (claves de cifrado, sal) | Añade credenciales de integración con la capa delegada → también fuera del repo |
| Dependencia operativa | Cero dependencia externa en el mostrador | La disponibilidad del login depende de un tercero: **RNF-042** exige que la dependencia tenga timeout y reintento controlados (DEC-12 aún no cerrada) |
| Segregación de funciones (RNF-031) | Se implementa en autorización propia | La capa delegada entrega identidad; **la autorización por rol sigue siendo del sistema** |

**Recomendación preliminar:** **B (delegada)** por propiedades — concentra MFA/hash/rotación en una superficie especializada y reduce la superficie en el puesto — **condicionada a que ambas funciones operen sin conexión (7-B/7-C, RC-09): (i) el login/MFA y (ii) la verificación del químico en dispensación (RF-054)**, o a que exista política de degradación declarada (P-03/P-15) para cada una; además que soporte ventana de sesión POS (RNF-037). **Si la capa delegada no soporta el modo sin conexión, la recomendación cae a A.** **No se cierra** hasta P-14 (método de verificación) + asesoría legal + ADR.

### 2.2 Cifrado: **A) de aplicación** vs **B) de plataforma/almacenamiento**

| Propiedad | A — cifrado aplicativo (los datos se cifran antes de persistir/transmitir) | B — cifrado de plataforma (el almacén/backup cifra en reposo) |
|---|---|---|
| Cifrado en reposo de datos sensibles y respaldos (RNF-036) | Cubre solo lo que la aplicación cifra; **los respaldos dependen de que todo pase por la app** | Cubre datos + respaldos por defecto, incluido lo que la app no prevé |
| Cifrado en tránsito (RNF-032) | Obligatorio en cualquier caso (canal) | No lo sustituye: **A y B son complementarias** en tránsito vs reposo |
| Cola/caché del puesto en modo degradado (§2.3 del 04, RNF-036) | Única opción cuando el puesto controla sus ficheros locales | No aplica si la cola vive en fichero propio del puesto sin almacén gestionado |
| Claves: ninguna clave en el repositorio (RNF-034) | Gestión de claves explícita de la app | Claves/permisos a cargo de la plataforma; verificar rotación y separación por entorno (RC-11) |
| Portabilidad (RC-01: sin atar a un producto) | Independiente del almacén | Depende del proveedor; exigir que **la propiedad sea verificable sin elegir aún producto** |
| Minimización (RNF-038): cifrar no es minimizar | Añade campo a campo si se cifra todo → exige diseño de campos separados PII/montos | Igual; el cifrado no sustituye enmascaramiento en reportes (RNF-038) |

**Recomendación preliminar:** **B para reposo + respaldos, A para la cola local del puesto, cifrado en tránsito (RNF-032)** — se declara como **propiedades exigibles**, no como elección de producto (RC-01); la elección concreta es de plataforma/infra (ADR con devops-architect).

### 2.3 Autorización, minimización y enmascaramiento (cobertura DEC-06)

| Requisito | Propuesta de control (propiedad, sin producto) | Fuente |
|---|---|---|
| Mínimo privilegio y segregación (quien dispensa no autoriza sus propios ajustes) | Autorización por rol verificada en servidor en cada operación; el "no autoriza lo propio" es **regla de negocio comprobable**, no config de UI | RNF-031 |
| Registro de cada consulta o modificación de PII | Serie de auditoría de accesos (`Q`) con usuario/fecha/objetivo; volumen `null` (P-06), retención `null` (P-02) | RF-091, RN-13, D-06 |
| Minimización | PII solo donde lo exige el proceso; reportes sin PII por defecto | RNF-038 |
| Enmascaramiento en reportes cuando no sea necesario | Máscara activa por defecto; ver PII solo con rol justificado y **esa vista queda auditada** | RNF-038, RF-091 |
| Logs estructurados sin PII de pacientes | Regla de sanitización de errores y logs (R-06: fuga en logs/reportes/errores) | RNF-050 |
| Validación crítica en servidor | Revalidar lote/vencimiento/cantidades/receta **siempre** en backend (la capa de acceso no es frontera de confianza — refuerza DEC-11) | RNF-025, D-14 |

### 2.4 MFA en login y verificación en dispensación — supuestos abiertos (dos cosas distintas)

- **MFA al iniciar sesión** (RNF-030, CAM-003-a): aplica a **administrador, auditor y químico farmacéutico**; cajero y auxiliar **sin MFA por acción** (I-13 cerrado como supuesto). Su método y parámetros dependen de P-14.
- **Verificación en dispensación** (RF-054): exige verificación del **químico farmacéutico** para medicamentos **bajo receta y controlados**; método **sin definir (`null`)** — reautenticación o PIN (CAM-003-a/b). **No se elige aquí** (P-14).
- Efecto sobre DEC-06: ambas precondicionan §2.1 (y, sin conexión, §2.1/H-08); R-08 (fricción en mostrador) depende de la **suma** de ambas.
- Requisito de control **independiente del método**: cada una ocurre en su momento (login al iniciar sesión; verificación al dispensar), con **contexto de rol** y **queda auditada** (RF-090). El funcionamiento sin conexión (7-B/7-C, RC-09) se trata en §2.1/H-08.

## 3. DEC-09 — Auditoría inalterable y retención/anonimización (dueño)

Drivers: D-05, RN-10, RNF-023, RNF-039, RNF-046. Decisor: asesoría legal.

| Propiedad | A — auditoría en el mismo almacén append-only | B — almacén de auditoría independiente con protección adicional |
|---|---|---|
| Inalterabilidad / solo anexar (RNF-046), protección frente a usuarios operativos | La misma unidad transaccional ya no admite **modificación ni borrado** de registros confirmados (§2.2 del 04); la protección es **una** regla para todo | Exige política de acceso y sellado **adicionales** en un almacén aparte |
| Correcciones por movimientos compensatorios (RNF-023, RN-10) | Natural: el compensatorio se anexa junto al original, misma transacción | Requiere correlación entre almacén transaccional y el de auditoría (RNF-052) |
| Coherencia de punto de restauración (RNF-040, §5 del 04) | **Una** restauración deja libro + inventario + auditoría coherentes entre sí | Dos restauraciones que deben conciliarse entre sí |
| Anonimización (CAM-002-g / RNF-039) sin tocar PII del auditor | La anonimización toca **solo** datos personales de pacientes/prescriptores; movimientos, lotes, cantidades y libro **se conservan** | Igual; añade la pregunta de si los registros de **accesos a PII** se anonimizan (ver Q-03) |
| Retención larga de auditoría (P-02 `null`) | El histórico crece junto al transaccional (tensión que ya señala el 04 §5) | Independencia para retener más tiempo sin arrastrar el transaccional |
| Coste/complejidad (CA-09) | Mínima | Añade almacén, sellado y conciliación → exigir justificación medible |
| Consulta de auditoría por evento crítico (RF-090/091) | Misma unidad que el evento → consulta directa | Consulta cruzada con correlación garantizada (RNF-052) |

**Resolución de la tensión I-11 (inalterabilidad vs anonimización):** se mantiene como **supuesto CAM-002-g, pendiente de aprobación** — la anonimización afecta **solo datos personales** de pacientes/prescriptores; movimientos, lotes, cantidades y libro de controlados se conservan (RN-10, RNF-039). **No se fija plazo** (P-02, RC-03); la política de cuándo se aplica es de asesoría legal.

**Recomendación preliminar:** **A con protección adicional explícita** (append-only por política de permisos, sin modificación ni borrado para roles operativos, sellado/índice de integridad) y **B solo si** P-02/asesoría legal impongan una retención de auditoría que no quepa en el histórico transaccional (condición de cambio registrada, no decisión). Sujeta a asesoría legal + ADR.

## 4. DEC-11 — Validación del corte de API (validador: security-reviewer)

Propuesta a validar en `03 §4`: **11-a superficie mínima por rol con validación centralizada** vs **11-b punto único de entrada con políticas por recurso**.

| Veredicto | Detalle |
|---|---|
| ✅ **Valida 11-a** | Cumple D-14 + RNF-025 + RNF-035: cada operación expone solo lo que el rol necesita y la validación crítica queda centralizada en servidor. La propiedad de corte por rol es natural en ALT-1/ALT-2/ALT-5. **Condiciones:** (i) el corte por rol se aplica en **toda** superficie pública e interna (§2.3 del 04 y §4 DEC-11 del 03: en ALT-3 son dos superficies); (ii) en ALT-4 el puesto **no** expone API propia — solo cliente de la central; (iii) si **P-07** (imagen de receta) cambia, se revisa la superficie de adjuntos. |
| ⚠️ **11-b no se acepta hoy** | No por suponer error (CA-04), sino porque **no está declarado el mecanismo** que haga que la política por recurso recupere el contexto del rol; sin ese mecanismo no puede verificarse D-14 en cada operación. Si architect lo declara y lo demuestra, la validación se repite. |
| 🔄 Revalidación | Cambio de P-07, de la superficie pública/interna o incorporación de DEC-12 (integraciones, §4 del 03) → revalidar corte. |

Cobertura RNF-035 (auth, límites de uso, validación de entradas): el **límite de uso** no está en 11-a/11-b → requisito de control **H-05** (tasa por sesión/rol, ver §6).

## 5. Cobertura de CA-08 y CA-11

| CA | Dimensión | Estado en esta revisión | Evidencia / hueco |
|---|---|---|---|
| CA-08 | Seguridad | **Cubierto con condiciones** | §2 (DEC-06), §4 (DEC-11), hallazgos H-01/H-05/H-08; MFA/autorización dependen de P-14 |
| CA-08 | Privacidad | **Cubierto con condiciones** | §2.3 (minimización/enmascaramiento), H-03, **H-11 (PII en derivados y respaldos)**; normativa por validar (R-10, P-02) |
| CA-08 | Observabilidad | **Parcial** | RF-090/091 y logs sin PII (H-03); `Q` sin volumen (H-09); **alertas por accesos anómalos a PII (RNF-053) pendientes de definir con devops-architect (H-12)**; métricas RNF-051 → devops-architect |
| CA-08 | Recuperación | **Insumo enviado** | §7 del 04 (RPO/RTO `null` → S-3); no se fija aquí (RC-03) |
| CA-11 | Datos sensibles | **Cubierto con condiciones** | PII: acceso por rol, registro de consultas, cifrado (§2), cola degradada (H-07), derivados/respaldos (H-11) |
| CA-11 | Plazos de retención | **NO cerrable hoy** | P-02 `null` + normativa por validar (R-10) → **H-02 bloqueante**; solo se declaran propiedades |

## 6. Hallazgos y requisitos de control

### 6.1 Hallazgos

| ID | Hallazgo | Severidad | Fuente |
|---|---|---|---|
| **H-01** | Método de verificación en dispensación (reautenticación o PIN) **sin definir**: condiciona DEC-06, R-08 y la prueba de I-13 | **Bloqueante** (para cerrar DEC-06); tratamiento en §8 | CAM-003-a/b, P-14 |
| **H-02** | Plazos de retención y normativa **`null`**: no se puede cerrar DEC-09 ni CA-11 (plazos); solo propiedades | **Bloqueante** (para cerrar DEC-09/CA-11); tratamiento en §8 | P-02, R-10, RC-03 |
| **H-03** | Fuga de PII en logs, reportes o errores (R-06) — incluida la cola/caché del puesto y los mensajes de error de API | **Alto** | R-06, RNF-038, RNF-050 |
| **H-04** | En 7-C (bajo receta sin conexión) no se verifica el saldo de receta hasta reconectar: exceso potencial de dispensación por receta | **Alto** | I-14, P-15, D-15, RF-047/RF-053 |
| **H-05** | Si se usara 11-b, queda **por verificar** que la política por recurso recupere el contexto de rol (condición no demostrada hoy); además **límite de uso** (RNF-035) no está en el corte propuesto | **Alto** (en 11-a queda como control a implementar) | D-14, RNF-025, RNF-035 |
| **H-06** | Sesiones de POS y MFA en mostrador: riesgo de compartir sesión si la ventana es larga, o de fricción si es corta (R-08) | **Medio** | R-08, RNF-030, RNF-037 |
| **H-07** | PII temporal en cola/caché del puesto en modo degradado: cifrado en reposo y borrado al sincronizar dependen de la opción de DEC-07 aún no cerrada | **Medio** | §2.3 del 04, RNF-036, RNF-039 |
| **H-08** | Identidad delegada (recomendada en §2.1) introduce dependencia externa del login y credenciales de integración; **sin conexión (7-B/7-C, RC-09) puede dejar el login y la verificación del químico (RF-054) inoperativos** | **Alto** | RNF-042, RNF-034, RNF-035, RF-054 |
| **H-09** | `Q` (consultas auditadas RF-091) sin volumen definido: no se dimensiona la serie de auditoría de accesos | **Bajo** | P-06, RF-091 |
| **H-10** | Inalterabilidad temporal: marcas de tiempo de auditoría dependen de relojes consistentes entre sucursales | **Bajo** | RNF-047, RNF-046 |
| **H-11** | PII presente en el **historial derivado** (DEC-04 C, `04 §4`) y en el **payload de eventos** (DEC-05 B, `04 §5`); los **respaldos** pueden conservar PII ya anonimizada en datos vivos (RNF-036/039) | **Alto** | DEC-04 C, DEC-05 B, RNF-036, RNF-039 |
| **H-12** | Sin alerta definida por **accesos anómalos a PII** (RNF-053): RF-091 registra cada consulta, pero nadie vigila patrones anómalos; coordinar con devops-architect | **Alto** | RNF-053, RF-091, RN-13 |

> **Tratamiento de H-01 y H-02:** se cierran en el ADR como **supuesto explícito** con la pregunta abierta (regla de cierre de `02`) y son **requisito antes de producción**; ver §8 (compatibilidad con CA-12).

### 6.2 Requisitos de control por hallazgo

| Hallazgo | Requisito de control exigible (propiedad verificable; sin producto) | Verifica en | CA |
|---|---|---|---|
| H-01 | Toda dispensación bajo receta/controlada exige verificación **en el momento** con contexto de rol y registro (RF-090); hasta P-14 queda como **supuesto explícito** con pregunta en el ADR | DEC-06 + perfil de roles | CA-08 |
| H-02 | Retención expresada como **política paramétrica** (plazos en configuración, `null` hasta P-02) + anonimización que toca **solo PII** (CAM-002-g); nunca plazos fijados por este documento | DEC-09 | CA-11 |
| H-03 | Sanitización obligatoria de logs/errores/reportes (lista de campos PII prohibidos); reportes enmascarados por defecto; acceso a vista sin máscara auditado (RF-091) | DEC-06 + API | CA-08/CA-11 |
| H-04 | En 7-C: registro de excesos por receta al reconectar + alerta + ajuste autorizado (criterio D-15/RF-053); **no** confiar solo en RF-047 (cobre stock, no receta) | DEC-07 (architect) con visto security | CA-08 |
| H-05 | Validación central en servidor con **autorización por rol en cada operación** + **límite de uso** por sesión/rol (RNF-035) declarado como control; 11-b solo con mecanismo de contexto de rol demostrado | DEC-11 | CA-08 |
| H-06 | Expiración por inactividad + cierre de sesión (RNF-037) + **quien actúa queda identificado** en cada evento (RF-090): la sesión compartida se detecta por trazabilidad, no por suposición | DEC-06 | CA-08 |
| H-07 | Cola/caché del puesto cifradas en reposo (RNF-036) y borrado de PII al vaciar la cola (minimización); propiedad exigible a C y L de DEC-07 | §2.3 del 04 (ya enviada) | CA-08/CA-11 |
| H-08 | Credenciales de la capa de identidad fuera del repositorio (RNF-034), rotación definida, y **degradación explícita**: si la identidad externa no responde, el mostrador no queda con sesión indefinida; **condición de aceptación de la opción B (§2.1): login/MFA y verificación del químico (RF-054) deben funcionar sin conexión (7-B/7-C, RC-09)** con la dependencia externa manejada por timeout/reintentos controlados (RNF-042) | DEC-06 + devops-architect | CA-08 |
| H-09 | Definir `Q` con P-06 antes de dimensionar índices/retención de la serie de accesos; mientras, dejar la fórmula paramétrica `Q × H` (§5 del 04) | DEC-06 | CA-08 |
| H-10 | Sincronización de reloj confiable (RNF-047) como requisito de la integridad temporal de la auditoría | devops-architect | CA-08 |
| H-11 | Minimización **extensiva a los derivados**: historial derivado (DEC-04 C) y payloads de eventos (DEC-05 B) sin PII directa (identificadores pseudonimizados/montos sueltos); respaldos cifrados y sometidos a la **misma política de anonimización** al restaurar (RNF-036/039); **plazos `null` — no se fijan aquí** | DEC-04/DEC-05 (database) con visto security | CA-08/CA-11 |
| H-12 | Regla de detección de **accesos anómalos a PII** (frecuencia por usuario/rol, consultas fuera de horario, consultas masivas) sobre la serie `Q`, con alerta operativa; **definida junto a devops-architect** (observabilidad) y **umbrales como parámetro**, no fijados | DEC-06 + devops-architect | CA-08 |

## 7. Preguntas abiertas

| ID | Pregunta | Dueño | Bloquea |
|---|---|---|---|
| Q-01 | **CAM-003-a/b**: ¿reautenticación o PIN en dispensación? (método sin definir) | Cliente (P-14) | Cierre DEC-06, H-01 |
| Q-02 | **P-02** + normativa (R-10): ¿plazos de retención de auditoría y de PII? | Cliente + asesoría legal | Cierre DEC-09, CA-11 (plazos), H-02 |
| Q-03 *(nueva)* | Si la anonimización (CAM-002-g) toca también los **registros de accesos a PII** (RF-091), ¿qué se conserva y qué se anonimiza? | Asesoría legal | Diseño de la serie `Q` |
| Q-04 | **P-15**: ¿se permite dispensar bajo receta sin conexión (7-C)? Si sí, ¿criterio D-15 verificado en reconexión? | Cliente | Condición de H-04 |
| Q-05 | **P-07**: ¿la receta incluye imagen? Cambia superficie de adjuntos → revalida DEC-11. | Cliente | Revalidación DEC-11 |
| Q-06 | **P-03** (política degradada): ¿quién puede operar en degradado y con qué límites por sesión? | Cliente | H-06/H-07 |
| Q-07 *(nueva)* | Identidad: ¿se acepta la dependencia externa del login (opción B de §2.1) **y su comportamiento sin conexión (7-B/7-C, RC-09)** — login/MFA y verificación del químico (RF-054)? | Cliente + devops | Cierre DEC-06 |
| Q-08 | **P-01**: ¿RPO/RTO y frecuencia de prueba de restauración exigibles? | Cliente | DEC-10 (no cierra aquí; insumo de CA-08 Recuperación) |

## 8. Condiciones de esta revisión

1. **Ninguna decisión cerrada**: DEC-06, DEC-09 y la validación de DEC-11 son **recomendación preliminar** sujeta a **asesoría legal/cliente** y **ADR final**.
2. **Condiciones de cambio (reabrir si ocurren):** (i) P-14 define método de verificación distinto a los supuestos → revisar §2.1; (ii) P-02 impone plazos largos de auditoría → revisar A/B de §3 (posible salto a B); (iii) P-15 habilita 7-C → endurecer H-04; (iv) P-07 añade imagen de receta → revalidar DEC-11.
3. **No se decide aquí** DEC-04/DEC-05/DEC-08/DEC-10 (pertenecen a architect, database-specialist/devops y al ADR).
4. **Tratamiento de H-01 y H-02 (hallazgos bloqueantes de cierre):** conforme a la regla de cierre de `02 §5` (§5, "Regla de cierre"), ambos se cierran en el ADR como **supuesto explícito** con su pregunta abierta (Q-01/P-14 y Q-02/P-02 respectivamente), y son **requisito antes de producción**: no se implementa ni se pone en operación el sistema sin responderlos. Esto es **compatible con CA-12** ("no deben quedar hallazgos bloqueantes en revisión final"): CA-12 se verifica en la revisión final del ADR, donde H-01/H-02 ya no son hallazgos sin tratar, sino supuestos explícitos con pregunta abierta y condición de puesta en producción — no se ocultan ni se degradan, se cierran por la vía que `02` establece.
5. **Entrada siguiente:** architecture-reviewer (contrarrevisión) y solution-leader (consolidación + ADR), con H-01/H-02 tratados según el punto 4.
