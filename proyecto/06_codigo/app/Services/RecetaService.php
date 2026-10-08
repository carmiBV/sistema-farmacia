<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\RecetaRepository;
use App\Core\Database;
use App\Validators\InventarioValidator;

/**
 * CP-BACK-07 · Recetas médicas (RF-052 registro, RF-053 saldo, RF-003
 * prescriptores). Dispensar sólo controla el saldo global con CAS dentro de
 * Tx; el stock/kárdex se vincula en CP-08 (Opción A, supuesto registrado).
 */
final class RecetaService
{
    public const CONDICIONES_RX = ['receta', 'controlado'];

    public function __construct(
        private readonly RecetaRepository $repo = new RecetaRepository(),
        // ponytail: helpers genéricos de paginación/enteros reutilizados en
        // lugar de un segundo validador sólo para 4 métodos.
        private readonly InventarioValidator $v = new InventarioValidator(),
    ) {
    }

    /** GET /rx/prescriptions */
    public function listar(array $q): array
    {
        $filtros = [
            'patient_id' => $this->v->entero($q, 'patient_id', 1, PHP_INT_MAX),
            'prescriber_id' => $this->v->entero($q, 'prescriber_id', 1, PHP_INT_MAX),
        ];
        $pag = $this->v->paginacion($q);
        try {
            $total = $this->repo->contar($filtros);
            $items = $this->repo->listar($filtros, $pag['limit'], ($pag['page'] - 1) * $pag['limit']);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $pag['page'], 'limit' => $pag['limit']]];
    }

    /** GET /rx/prescriptions/{id} */
    public function obtener(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        $r = $this->repo->buscarReceta($id);
        if ($r === null) {
            throw new AppException('PRESCRIPTION_NOT_FOUND', 'Receta no encontrada.', 404);
        }
        return ['prescripcion' => $r, 'items' => $this->repo->itemsReceta($id)];
    }

    /** POST /rx/prescriptions (RF-052). */
    public function crear(array $input): array
    {
        $d = $this->validarCreacion($input);

        $estadoPrescriptor = $this->repo->estadoPrescriptor($d['prescriber_id']);
        if ($estadoPrescriptor === null) {
            throw new AppException('PRESCRIBER_NOT_FOUND', 'Prescriptor no encontrado.', 404);
        }
        if ($estadoPrescriptor !== 'activo') {
            throw new AppException('PRESCRIBER_NOT_ACTIVE', 'El prescriptor no está activo.', 409);
        }
        $estadoPaciente = $this->repo->estadoPaciente($d['patient_id']);
        if ($estadoPaciente === null) {
            throw new AppException('PATIENT_NOT_FOUND', 'Paciente no encontrado.', 404);
        }
        if ($estadoPaciente !== 'activo') {
            throw new AppException('PATIENT_NOT_ACTIVE', 'El paciente no está activo.', 409);
        }

        $vistos = [];
        foreach ($d['items'] as $item) {
            if (isset($vistos[$item['product_id']])) {
                throw new AppException('VALIDATION_ERROR', "Producto {$item['product_id']} duplicado en la receta.", 400);
            }
            $vistos[$item['product_id']] = true;

            $p = $this->repo->producto($item['product_id']);
            if ($p === null) {
                throw new AppException('PRODUCT_NOT_FOUND', "Producto {$item['product_id']} no encontrado.", 404);
            }
            if ($p['estado'] !== 'activo') {
                throw new AppException('PRODUCT_INACTIVE', "Producto {$item['product_id']} inactivo.", 409);
            }
            if (!in_array($p['condicion_venta'], self::CONDICIONES_RX, true)) {
                throw new AppException(
                    'PRODUCT_NOT_UNDER_RX',
                    "El producto {$item['product_id']} no requiere receta.",
                    409
                );
            }
        }

        $pdo = Database::pdo();
        $enTx = false;
        $rid = 0;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            $rid = $this->repo->insertarReceta(
                $d['prescriber_id'],
                $d['patient_id'],
                $d['fecha'],
                $d['numero_referencia']
            );
            $this->repo->insertarItems($rid, $d['items']);
            $pdo->commit();
            $enTx = false;
        } catch (AppException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (\PDOException) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->respuesta($rid);
    }

    /**
     * POST /rx/prescriptions/{id}/dispensar (RF-053): sólo control de saldo.
     * Idempotencia y stock quedan en CP-08 (supuesto registrado).
     */
    public function dispensar(string $rawId, array $input): array
    {
        $id = $this->idEntero($rawId);
        $d = $this->validarDispensacion($input);
        $receta = $this->repo->buscarReceta($id);
        if ($receta === null) {
            throw new AppException('PRESCRIPTION_NOT_FOUND', 'Receta no encontrada.', 404);
        }

        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            foreach ($d['items'] as $item) {
                if ($this->repo->itemDeReceta($id, $item['rx_item_id']) === null) {
                    throw new AppException(
                        'RX_ITEM_NOT_IN_PRESCRIPTION',
                        "El ítem {$item['rx_item_id']} no pertenece a la receta {$id}.",
                        400
                    );
                }
                if (!$this->repo->aumentarDispensado($item['rx_item_id'], $item['cantidad'])) {
                    throw new AppException(
                        'RX_SALDO_INSUFICIENTE',
                        "Saldo insuficiente en el ítem {$item['rx_item_id']}.",
                        409
                    );
                }
            }
            $pdo->commit();
            $enTx = false;
        } catch (AppException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (\PDOException) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->respuesta($id);
    }

    // ------------------------------------------------------------ validación

    /** @return array{prescriber_id:int,patient_id:int,fecha:string,numero_referencia:?string,items:list<array{product_id:int,cantidad_prescrita:int}>} */
    private function validarCreacion(array $input): array
    {
        $prescriberId = $this->v->enteroObligatorio($input, 'prescriber_id', 1, PHP_INT_MAX);
        $patientId = $this->v->enteroObligatorio($input, 'patient_id', 1, PHP_INT_MAX);
        $fecha = $this->v->textoObligatorio($input, 'fecha', 10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $fm) !== 1
            || checkdate((int) $fm[2], (int) $fm[3], (int) $fm[1]) === false) {
            throw new AppException('VALIDATION_ERROR', "Campo 'fecha' inválida (use AAAA-MM-DD).", 400);
        }

        $numeroRef = null;
        if (isset($input['numero_referencia']) && $input['numero_referencia'] !== '' && $input['numero_referencia'] !== null) {
            $numeroRef = $this->v->textoObligatorio($input, 'numero_referencia', 100);
        }

        $items = $input['items'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 500) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' debe tener entre 1 y 500 elementos.", 400);
        }
        $limpios = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new AppException('VALIDATION_ERROR', 'Cada item debe ser un objeto.', 400);
            }
            $limpios[] = [
                'product_id' => $this->v->enteroObligatorio($item, 'product_id', 1, PHP_INT_MAX),
                'cantidad_prescrita' => $this->v->enteroObligatorio($item, 'cantidad_prescrita', 1, 100000),
            ];
        }
        return [
            'prescriber_id' => $prescriberId,
            'patient_id' => $patientId,
            'fecha' => $fecha,
            'numero_referencia' => $numeroRef,
            'items' => $limpios,
        ];
    }

    /** @return array{items:list<array{rx_item_id:int,cantidad:int}>} */
    private function validarDispensacion(array $input): array
    {
        $items = $input['items'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 500) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' debe tener entre 1 y 500 elementos.", 400);
        }
        $limpios = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new AppException('VALIDATION_ERROR', 'Cada item debe ser un objeto.', 400);
            }
            $limpios[] = [
                'rx_item_id' => $this->v->enteroObligatorio($item, 'rx_item_id', 1, PHP_INT_MAX),
                'cantidad' => $this->v->enteroObligatorio($item, 'cantidad', 1, 100000),
            ];
        }
        return ['items' => $limpios];
    }

    private function respuesta(int $recetaId): array
    {
        $r = $this->repo->buscarReceta($recetaId);
        if ($r === null) {
            throw new AppException('PRESCRIPTION_NOT_FOUND', 'Receta no encontrada.', 404);
        }
        return ['prescripcion' => $r, 'items' => $this->repo->itemsReceta($recetaId)];
    }

    private function idEntero(string $rawId): int
    {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'id' inválido.", 400);
        }
        return $id;
    }
}
