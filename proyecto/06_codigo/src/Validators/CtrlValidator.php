<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\AppException;

/**
 * Validacion de entradas del Libro Oficial de Controlados (CP-BACK-09).
 * RF-046 (doble autorizacion de ajustes), RF-055 (saldo permanente), RF-071
 * (reportes por periodo).
 */
final class CtrlValidator
{
    /** @var list<string> */
    public const TIPOS = ['entrada', 'salida', 'ajuste', 'devolucion', 'transferencia_out', 'transferencia_in'];

    /** @var list<string> */
    public const REF_TIPOS = ['venta', 'recepcion', 'transferencia', 'devolucion', 'ajuste'];

    /** @var list<string> */
    public const DIRECCIONES = ['baja', 'incremento'];

    public function __construct(
        private readonly InventarioValidator $base = new InventarioValidator(),
    ) {
    }

    /**
     * @return array{store_id:?int,product_id:?int,tipo:?string,ref_tipo:?string,
     *   desde:?string,hasta:?string,page:int,limit:int}
     */
    public function filtrosAsientos(array $q): array
    {
        $f = [
            'store_id' => $this->base->entero($q, 'store_id', 1, PHP_INT_MAX),
            'product_id' => $this->base->entero($q, 'product_id', 1, PHP_INT_MAX),
            'tipo' => $this->base->enumParam($q, 'tipo', self::TIPOS),
            'ref_tipo' => $this->base->enumParam($q, 'ref_tipo', self::REF_TIPOS),
            'desde' => $this->fecha($q, 'desde'),
            'hasta' => $this->fecha($q, 'hasta'),
        ];
        if ($f['desde'] !== null && $f['hasta'] !== null && $f['hasta'] < $f['desde']) {
            throw new AppException('VALIDATION_ERROR', "El filtro 'hasta' no puede ser anterior a 'desde'.", 400);
        }
        return $f + $this->base->paginacion($q);
    }

    /** @return array{store_id:?int,product_id:?int,page:int,limit:int} */
    public function filtrosSaldos(array $q): array
    {
        return [
            'store_id' => $this->base->entero($q, 'store_id', 1, PHP_INT_MAX),
            'product_id' => $this->base->entero($q, 'product_id', 1, PHP_INT_MAX),
        ] + $this->base->paginacion($q);
    }

    /**
     * POST /control/adjustments (RF-046): ajuste con doble autorizacion.
     *
     * @return array{store_id:int,product_id:int,lot_id:int,cantidad:int,
     *   direccion:string,motivo:string,autorizador_id:int}
     */
    public function validarAjuste(array $in): array
    {
        $dir = isset($in['direccion']) ? (string) $in['direccion'] : '';
        if (!in_array($dir, self::DIRECCIONES, true)) {
            throw new AppException('VALIDATION_ERROR', "Campo 'direccion' invalido (baja|incremento).", 400);
        }
        return [
            'store_id' => $this->base->enteroObligatorio($in, 'store_id', 1, PHP_INT_MAX),
            'product_id' => $this->base->enteroObligatorio($in, 'product_id', 1, PHP_INT_MAX),
            'lot_id' => $this->base->enteroObligatorio($in, 'lot_id', 1, PHP_INT_MAX),
            'cantidad' => $this->base->enteroObligatorio($in, 'cantidad', 1, PHP_INT_MAX),
            'direccion' => $dir,
            'motivo' => $this->base->textoObligatorio($in, 'motivo', 500),
            'autorizador_id' => $this->base->enteroObligatorio($in, 'autorizador_id', 1, PHP_INT_MAX),
        ];
    }

    /** Fecha Y-m-d con round-trip; null si el parametro no viene. */
    private function fecha(array $q, string $key): ?string
    {
        if (!isset($q[$key]) || $q[$key] === '' || $q[$key] === null) {
            return null;
        }
        if (!is_string($q[$key])) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido (YYYY-MM-DD).", 400);
        }
        $v = trim($q[$key]);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($dt === false || $dt->format('Y-m-d') !== $v) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido (YYYY-MM-DD).", 400);
        }
        return $v;
    }
}
