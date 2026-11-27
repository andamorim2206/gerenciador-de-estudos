<?php

declare(strict_types=1);

// Cria uma conta pelo terminal (também dá para criar pela tela /cadastro).
// Uso: php bin/criar-usuario.php email@exemplo.com "Nome"   (a senha é pedida no terminal)
// O primeiro usuário criado herda as matérias e tarefas do protótipo.

require __DIR__ . '/../src/bootstrap.php';

use App\Auth;
use App\Database;

[, $email, $nome] = $argv + [null, null, null];
if (!$email || !$nome) {
    fwrite(STDERR, "Uso: php bin/criar-usuario.php email@exemplo.com \"Nome\"\n");
    exit(1);
}

$senha = getenv('SENHA') ?: null;
if ($senha === null) {
    fwrite(STDOUT, 'Senha (mínimo 8 caracteres): ');
    system('stty -echo 2>/dev/null');
    $senha = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}

try {
    $id = Auth::criarUsuario(Database::pdo(), $email, $nome, $senha);
} catch (DomainException $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
} catch (PDOException $e) {
    fwrite(STDERR, Database::violaUnicidade($e) ? "Erro: já existe uma conta com esse e-mail.\n" : 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Usuário #$id criado: $email\n";
