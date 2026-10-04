<?php
declare(strict_types=1);

function envRequired(string $key): string {
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        fwrite(STDERR, "Falta variable requerida: {$key}\n");
        exit(1);
    }
    return $value;
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    envRequired('DB_HOST'),
    envRequired('DB_PORT'),
    envRequired('DB_NAME')
);

$pdo = new PDO($dsn, envRequired('DB_USER'), envRequired('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$name = envRequired('DEMO_USER_NAME');
$email = envRequired('DEMO_USER_EMAIL');
$password = envRequired('DEMO_USER_PASSWORD');

$hash = password_hash($password, PASSWORD_ARGON2ID);
if ($hash === false) {
    throw new RuntimeException('No se pudo generar hash Argon2id.');
}

$now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

$pdo->beginTransaction();
try {
    $q = $pdo->prepare('SELECT id FROM auth_usuario WHERE email = :email LIMIT 1');
    $q->execute(['email' => $email]);
    $id = $q->fetchColumn();

    if ($id) {
        $q = $pdo->prepare(
            'UPDATE auth_usuario
             SET nombre=:nombre, hash_password=:hash, estado="activo",
                 user_update=1, user_update_at=:now
             WHERE id=:id'
        );
        $q->execute(['nombre'=>$name,'hash'=>$hash,'now'=>$now,'id'=>$id]);
    } else {
        $q = $pdo->prepare(
            'INSERT INTO auth_usuario
             (nombre,email,hash_password,estado,user_create,user_update,user_created_at,user_update_at)
             VALUES (:nombre,:email,:hash,"activo",1,1,:now,:now)'
        );
        $q->execute(['nombre'=>$name,'email'=>$email,'hash'=>$hash,'now'=>$now]);
    }

    $pdo->commit();
    echo "Usuario demo preparado: {$email}\n";
    echo "La contraseña no se muestra.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "No se pudo preparar el usuario demo.\n");
    exit(1);
}
