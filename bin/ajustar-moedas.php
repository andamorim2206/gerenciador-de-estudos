<?php

declare(strict_types=1);

// Correção manual de saldo, registrada no extrato como "ajuste" (as transações nunca são editadas).
// Uso: php bin/ajustar-moedas.php email@exemplo.com -2 "Motivo do ajuste"

require __DIR__ . '/../src/bootstrap.php';

use App\Carteira;
use App\Database;

[, $email, $valor, $motivo] = $argv + [null, null, null, null];
if (!$email || !is_numeric($valor) || !$motivo) {
    fwrite(STDERR, "Uso: php bin/ajustar-moedas.php email@exemplo.com <valor> \"motivo\"\n");
    exit(1);
}

$pdo = Database::pdo();
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuarioId = $stmt->fetchColumn();
if (!$usuarioId) {
    fwrite(STDERR, "Usuário não encontrado.\n");
    exit(1);
}

try {
    Database::transacao($pdo, fn(PDO $pdo) => Carteira::ajustar($pdo, (int) $usuarioId, (int) $valor, $motivo, agora()));
} catch (DomainException | InvalidArgumentException $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'Ajuste registrado. Saldo atual: ' . Carteira::saldo($pdo, (int) $usuarioId) . "\n";
