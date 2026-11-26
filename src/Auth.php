<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public static function usuarioAtual(PDO $pdo): ?array
    {
        $id = $_SESSION['usuario_id'] ?? null;
        if (!$id) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, email, nome FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function entrar(PDO $pdo, string $email, string $senha): bool
    {
        $stmt = $pdo->prepare('SELECT id, senha_hash FROM usuarios WHERE email = ?');
        $stmt->execute([trim($email)]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        return true;
    }

    /** Cria a conta pelo formulário de cadastro e já deixa a pessoa logada. */
    public static function cadastrar(PDO $pdo, string $email, string $nome, string $senha, string $confirmacao): int
    {
        if (!hash_equals($senha, $confirmacao)) {
            throw new \DomainException('As senhas não conferem.');
        }
        try {
            $id = self::criarUsuario($pdo, $email, $nome, $senha);
        } catch (\PDOException $e) {
            // Dois cadastros simultâneos com o mesmo e-mail: o UNIQUE do banco barra o segundo.
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new \DomainException('Já existe uma conta com esse e-mail.');
            }
            throw $e;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario_id'] = $id;
        return $id;
    }

    public static function sair(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * Cria o usuário já com carteira (saldo 0) e progresso do neurônio (XP 0).
     * O primeiro usuário herda as matérias e tarefas do protótipo, que ainda não têm dono.
     */
    public static function criarUsuario(PDO $pdo, string $email, string $nome, string $senha): int
    {
        $email = trim($email);
        $nome = trim($nome);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('E-mail inválido.');
        }
        if ($nome === '') {
            throw new \DomainException('Informe o nome.');
        }
        if (mb_strlen($nome) > 60) {
            throw new \DomainException('O nome pode ter no máximo 60 caracteres.');
        }
        if (strlen($senha) < 8) {
            throw new \DomainException('A senha precisa ter pelo menos 8 caracteres.');
        }
        if (strlen($senha) > 72) {
            // password_hash (bcrypt) ignora o que passa de 72 bytes.
            throw new \DomainException('A senha pode ter no máximo 72 caracteres.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            throw new \DomainException('Já existe uma conta com esse e-mail.');
        }

        return Database::transacao($pdo, function (PDO $pdo) use ($email, $nome, $senha): int {
            $agora = agora();
            $pdo->prepare('INSERT INTO usuarios (email, nome, senha_hash, criado_em) VALUES (?, ?, ?, ?)')
                ->execute([$email, $nome, password_hash($senha, PASSWORD_DEFAULT), $agora]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO carteiras (usuario_id, saldo, atualizado_em) VALUES (?, 0, ?)')->execute([$id, $agora]);
            $pdo->prepare('INSERT INTO progresso_neuronio (usuario_id, atualizado_em) VALUES (?, ?)')->execute([$id, $agora]);
            $pdo->prepare('UPDATE materias SET usuario_id = ? WHERE usuario_id IS NULL')->execute([$id]);

            return $id;
        });
    }
}
