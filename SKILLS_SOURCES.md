# Fuentes de Skills existentes

los orquesta y define en qué momento utilizarlos.

| Skill | Repositorio | Licencia | Uso |
|---|---|---|---|
| `database-schema-designer` | `https://github.com/softaworks/agent-toolkit` | MIT | Diseño relacional, normalización, constraints, índices, migraciones, ERD, auditoría y patrones temporales. |
| `postgresql-table-design` | `https://github.com/wshobson/agents` | MIT | Diseño y revisión específica de PostgreSQL: tipos, índices, constraints, rendimiento y funciones avanzadas. |
| `databases` | `https://github.com/iuliandita/skills` | MIT | Operación/configuración de PostgreSQL, MySQL/MariaDB y MSSQL, migración, tuning y revisión. |
| `database-documentation` | `https://github.com/a-tokyo/agent-skills` | MIT | Introspección de base real, ERD, diccionario de datos, documentación y detección de drift. |
| `mssql` | `https://github.com/sanjay3290/ai-skills` | Apache-2.0 | Consultas de verificación de solo lectura sobre Microsoft SQL Server. |
| `sql-server` | `https://github.com/chrishuffman5/sqlserver` | MIT | Router/fundamentos especializados de SQL Server. |
| `sqlserver-engineering` | `https://github.com/chrishuffman5/sqlserver` | MIT | Ingeniería específica de SQL Server. |
| `sqlserver-security` | `https://github.com/chrishuffman5/sqlserver` | MIT | Seguridad y autenticación de SQL Server. |

## Regla de actualización

No modificar el contenido original de un skill externo salvo que se cree una
adaptación separada y se documente explícitamente.

Para actualizar los skills instalados:

```powershell
npx skills update
```

El CLI genera `skills-lock.json`, que permite registrar el origen/versionado de
los skills instalados.
