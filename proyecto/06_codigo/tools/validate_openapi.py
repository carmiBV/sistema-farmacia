#!/usr/bin/env python3
"""Valida el contrato OpenAPI del recurso catalog_categories (CP-API-01)."""
import sys
from pathlib import Path

import yaml

SPEC = Path(__file__).resolve().parents[1] / "docs" / "openapi.yaml"

EXPECTED_OPS = {
    "/api/v1/catalog/categories": {"get", "post"},
    "/api/v1/catalog/categories/{id}": {"get", "put", "delete"},
}


def main() -> int:
    doc = yaml.safe_load(SPEC.read_text(encoding="utf-8"))
    assert doc["openapi"].startswith("3.1"), "openapi debe ser 3.1.x"
    assert doc["info"]["title"] and doc["info"]["version"], "info incompleto"

    for path, ops in EXPECTED_OPS.items():
        assert path in doc["paths"], f"falta path {path}"
        present = {m for m in doc["paths"][path] if m in {"get", "post", "put", "delete"}}
        assert present == ops, f"{path}: operaciones {present} != {ops}"

    schemas = doc["components"]["schemas"]
    for name in ("Category", "CategoryCreate", "CategoryUpdate", "ErrorEnvelope", "Meta"):
        assert name in schemas, f"falta schema {name}"

    # El lado del servidor nunca expone columnas internas/generadas.
    exposed = set(schemas["Category"]["properties"])
    assert exposed == {"id", "nombre", "parent_id"}, f"propiedades expuestas: {exposed}"

    # DELETE debe quedar documentado como borrado lógico (no destructivo).
    delete = doc["paths"]["/api/v1/catalog/categories/{id}"]["delete"]
    assert "204" in delete["responses"], "DELETE sin 204"
    assert "estado='inactivo'" in delete["description"], "DELETE sin inactivación lógica"

    # Sin stack traces ni detalle sensible en errores.
    err_props = schemas["ErrorEnvelope"]["properties"]["error"]["required"]
    assert err_props == ["code", "message"], f"envelope de error inesperado: {err_props}"

    print(f"PASS: contrato válido -> {SPEC}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except AssertionError as exc:
        print(f"FAIL: {exc}")
        sys.exit(1)
