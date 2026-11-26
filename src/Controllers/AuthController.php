<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use PDO;

final class AuthController
{
    public static function formulario(PDO $pdo, ?array $usuario): void
    {
        if ($usuario) {
            redirecionar('/');
        }
        render('login', ['email' => ''], layout: false);
    }

    public static function entrar(PDO $pdo, ?array $usuario): void
    {
        $email = (string) ($_POST['email'] ?? '');
        if (Auth::entrar($pdo, $email, (string) ($_POST['senha'] ?? ''))) {
            redirecionar('/');
        }
        flash('E-mail ou senha incorretos.', 'erro');
        render('login', ['email' => $email], layout: false);
    }

    public static function formularioCadastro(PDO $pdo, ?array $usuario): void
    {
        if ($usuario) {
            redirecionar('/');
        }
        render('cadastro', ['nome' => '', 'email' => ''], layout: false);
    }

    public static function cadastrar(PDO $pdo, ?array $usuario): void
    {
        if ($usuario) {
            redirecionar('/');
        }
        $nome = (string) ($_POST['nome'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        try {
            Auth::cadastrar($pdo, $email, $nome, (string) ($_POST['senha'] ?? ''), (string) ($_POST['confirmacao'] ?? ''));
        } catch (\DomainException $e) {
            // Mostra o erro sem perder o que já foi digitado (menos as senhas).
            flash($e->getMessage(), 'erro');
            render('cadastro', ['nome' => $nome, 'email' => $email], layout: false);
        }
        redirecionar('/', 'Conta criada. Bem-vindo(a)! Cadastre uma matéria para começar.', 'sucesso');
    }

    public static function sair(PDO $pdo, ?array $usuario): void
    {
        Auth::sair();
        redirecionar('/login');
    }
}
