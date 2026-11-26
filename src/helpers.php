<?php

declare(strict_types=1);

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const SEMANA_EXTENSO = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
    'Quinta-feira', 'Sexta-feira', 'Sábado'];
const TOTAL_CORES = 8;

/** Data e hora atuais no formato gravado no banco (horário de Brasília). */
function agora(): string
{
    return date('Y-m-d H:i:s');
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function data_valida(?string $valor): ?string
{
    if (!is_string($valor) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valor : null;
}

function cor(int $indice): string
{
    return 'cor-' . (abs($indice) % TOTAL_CORES);
}

function duracao(int $minutos): string
{
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    if ($h === 0) {
        return $m . 'min';
    }
    return $h . 'h' . ($m ? str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

function data_extenso(string $data): string
{
    $d = new DateTimeImmutable($data);
    return SEMANA_EXTENSO[(int) $d->format('w')] . ', ' . $d->format('j') . ' de '
        . mb_strtolower(MESES[(int) $d->format('n') - 1]);
}

function plural(int $n, string $singular, string $plural): string
{
    return $n . ' ' . ($n === 1 ? $singular : $plural);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Campos ocultos de todo formulário POST: token CSRF + campos extras. */
function campos_form(array $extras = []): string
{
    $html = '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    foreach ($extras as $nome => $valor) {
        $html .= '<input type="hidden" name="' . e($nome) . '" value="' . e((string) $valor) . '">';
    }
    return $html;
}

function flash(string $mensagem, string $tipo = 'info'): void
{
    $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
}

function pegar_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirecionar(string $url, string $mensagem = '', string $tipo = 'info'): never
{
    if ($mensagem !== '') {
        flash($mensagem, $tipo);
    }
    header('Location: ' . $url);
    exit;
}

function json(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Renderiza views/$view.php dentro do layout (ou sozinha, com $layout = false). */
function render(string $view, array $dados = [], bool $layout = true): never
{
    extract($dados);
    ob_start();
    require RAIZ . '/views/' . $view . '.php';
    $conteudo = ob_get_clean();
    if ($layout) {
        require RAIZ . '/views/layout.php';
    } else {
        echo $conteudo;
    }
    exit;
}

/** Renderiza um trecho de views/partials e devolve o HTML. */
function parcial(string $nome, array $dados = []): string
{
    extract($dados);
    ob_start();
    require RAIZ . '/views/partials/' . $nome . '.php';
    return ob_get_clean();
}
