<?php
declare(strict_types=1);

// CP-BACK-02: seed idempotente (NO es endpoint de la API).
// Crea rol 'admin', el permiso 'auth.users.manage' vinculado y el usuario
// administrador desde variables de entorno (nunca por parámetro ni hardcode).
//
// Uso:
//   $env:ADMIN_USER='admin'; $env:ADMIN_PASSWORD='<fuerte>'; php tools/init_admin.php
// NO imprimir ni loguear la contraseña.

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\App\Support\Env::load($root . '/.env');

use App\Repositories\RoleRepository;
use App\Repositories\UsuarioRepository;

$user = getenv('ADMIN_USER') ?: 'admin';
$pass = getenv('ADMIN_PASSWORD');

if ($pass === false || $pass === '' || mb_strlen((string)$pass) < 8) {
    fwrite(STDERR, "Defina ADMIN_PASSWORD (min 8 caracteres) en variables de entorno.\n");
    exit(1);
}

$roles = new RoleRepository();
$usuarios = new UsuarioRepository();

// 1. Rol admin
$roleId = $roles->findIdPorNombre('admin');
if ($roleId === null) {
    $roleId = $roles->crear('admin', 'Administrador con acceso completo a gestión de usuarios');
    echo "Rol 'admin' creado (id {$roleId}).\n";
} else {
    echo "Rol 'admin' ya existe (id {$roleId}).\n";
}

// 2. Permiso + vínculo (idempotente)
$permId = $roles->upsertPermiso('auth.users.manage', 'Gestión de usuarios, roles y asignaciones');
$roles->vincularPermiso($roleId, $permId);
echo "Permiso 'auth.users.manage' (id {$permId}) vinculado al rol.\n";

// CP-BACK-03: permiso de escritura de operaciones (sucursales, cajas, config).
$permOps = $roles->upsertPermiso('ops.config.manage', 'Escritura de sucursales, cajas, parámetros y turnos');
$roles->vincularPermiso($roleId, $permOps);
echo "Permiso 'ops.config.manage' (id {$permOps}) vinculado al rol.\n";

// CP-BACK-04: permiso de escritura de catálogo (productos, precios, promos, directorios).
$permCat = $roles->upsertPermiso('catalog.manage', 'Escritura de productos, precios, promociones, proveedores, pacientes y prescriptores');
$roles->vincularPermiso($roleId, $permCat);
echo "Permiso 'catalog.manage' (id {$permCat}) vinculado al rol.\n";

// CP-BACK-05: permiso de escritura de compras (órdenes, recepciones, confirmación).
$permComp = $roles->upsertPermiso('purchases.manage', 'Escritura de órdenes de compra, recepciones y confirmación de inventario');
$roles->vincularPermiso($roleId, $permComp);
echo "Permiso 'purchases.manage' (id {$permComp}) vinculado al rol.\n";

// CP-BACK-06: permisos de escritura de inventario (liberación, alertas,
// incidentes, transferencias, reservas) y ajuste con doble autorización.
$permInv = $roles->upsertPermiso('inventory.manage', 'Escritura de inventario: liberación de lotes, alertas, incidentes, transferencias y reservas');
$roles->vincularPermiso($roleId, $permInv);
echo "Permiso 'inventory.manage' (id {$permInv}) vinculado al rol.\n";

$permAdj = $roles->upsertPermiso('inventory.adjust', 'Ajuste de incidentes con doble autorización (RF-046)');
$roles->vincularPermiso($roleId, $permAdj);
echo "Permiso 'inventory.adjust' (id {$permAdj}) vinculado al rol.\n";

// CP-BACK-07: registro y dispensación de recetas médicas (RF-052/RF-053).
$permRx = $roles->upsertPermiso('rx.manage', 'Registro y dispensación de recetas médicas (RF-052, RF-053)');
$roles->vincularPermiso($roleId, $permRx);
echo "Permiso 'rx.manage' (id {$permRx}) vinculado al rol.\n";

// CP-BACK-08: permiso de escritura de ventas POS (órdenes, pagos,
// anulaciones y devoluciones).
$permSales = $roles->upsertPermiso('sales.manage', 'Registro de ventas POS, pagos, anulaciones y devoluciones (RF-050, RF-060)');
$roles->vincularPermiso($roleId, $permSales);
echo "Permiso 'sales.manage' (id {$permSales}) vinculado al rol.\n";

// CP-BACK-09: ajustes del Libro Oficial de Controlados (RF-046, doble autorizacion).
$permCtrl = $roles->upsertPermiso('control.manage', 'Ajustes del libro de controlados con doble autorizacion (RF-046, RN-06)');
$roles->vincularPermiso($roleId, $permCtrl);
echo "Permiso 'control.manage' (id {$permCtrl}) vinculado al rol.\n";

// CP-BACK-10: auditoria (RF-090/091, RN-13) y ciclo del outbox (decision 16).
$permAuditRead = $roles->upsertPermiso('audit.read', 'Consulta de registros de auditoria y accesos PII (RF-090, RF-091, RN-13)');
$roles->vincularPermiso($roleId, $permAuditRead);
echo "Permiso 'audit.read' (id {$permAuditRead}) vinculado al rol.\n";

$permAudit = $roles->upsertPermiso('audit.manage', 'Gestion del ciclo de vida de eventos salientes (outbox, decision 16)');
$roles->vincularPermiso($roleId, $permAudit);
echo "Permiso 'audit.manage' (id {$permAudit}) vinculado al rol.\n";


// 3. Usuario admin con rol (idempotente)
$algo = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$existe = $usuarios->findByUsuario($user);
if ($existe === null) {
    $nuevo = $usuarios->crear($user, password_hash($pass, $algo));
    $usuarios->asignarRoles($nuevo->id, [$roleId]);
    echo "Usuario '{$user}' creado con rol admin.\n";
} else {
    // ponytail: la seed fija el estado deseado; si el hash no verifica la
    // contrasena de entorno, se resincroniza (antes solo creaba, nunca actualizaba)
    if (!password_verify($pass, $existe->passwordHash)) {
        $usuarios->actualizarPasswordHash($existe->id, password_hash($pass, $algo));
        echo "Contrasena del usuario '{$user}' resincronizada desde entorno.\n";
    }
    $stmt = \App\Support\Database::pdo()->prepare('SELECT role_id FROM auth_user_roles WHERE user_id = ?');
    $stmt->execute([$existe->id]);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'role_id'));
    if (!in_array($roleId, $ids, true)) {
        $usuarios->asignarRoles($existe->id, array_merge($ids, [$roleId]));
        echo "Rol 'admin' asignado al usuario existente '{$user}'.\n";
    } else {
        echo "Usuario '{$user}' ya existe con rol admin.\n";
    }
}

echo "OK: seed auth completado.\n";
