<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/** Loja de itens cosméticos e coleção do usuário. Nenhum pagamento real: só moedas. */
final class Loja
{
    public const CATEGORIAS = [
        'skin_neuronio' => 'Skins de neurônio',
        'cenario' => 'Cenários',
        'efeito' => 'Efeitos visuais',
    ];

    /** Itens à venda e itens da coleção (mesmo os que saíram da loja), com o estado para este usuário. */
    public static function catalogo(PDO $pdo, int $usuarioId): array
    {
        $stmt = $pdo->prepare('
            SELECT i.*, iu.id AS item_usuario_id, iu.adquirido_em, iu.preco_pago,
                   (e.item_usuario_id IS NOT NULL) AS equipado
            FROM itens_loja i
            LEFT JOIN itens_usuario iu ON iu.item_id = i.id AND iu.usuario_id = ?
            LEFT JOIN itens_equipados e ON e.item_usuario_id = iu.id
            WHERE i.ativo = 1 OR iu.id IS NOT NULL
            ORDER BY i.ordem, i.id
        ');
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }

    /**
     * Compra atômica: debita o saldo (só se houver), adiciona o item à coleção e registra no extrato.
     * Qualquer falha desfaz tudo. O preço vem do catálogo, nunca da requisição.
     */
    public static function comprar(PDO $pdo, int $usuarioId, int $itemId, string $agora): array
    {
        try {
            return Database::transacao($pdo, function (PDO $pdo) use ($usuarioId, $itemId, $agora): array {
                $stmt = $pdo->prepare('SELECT * FROM itens_loja WHERE id = ? AND ativo = 1');
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();
                if (!$item) {
                    throw new \DomainException('Este item não está disponível.');
                }

                $stmt = $pdo->prepare('SELECT 1 FROM itens_usuario WHERE usuario_id = ? AND item_id = ?');
                $stmt->execute([$usuarioId, $itemId]);
                if ($stmt->fetchColumn()) {
                    throw new \DomainException('Este item já está na sua coleção.');
                }

                $preco = (int) $item['preco'];
                if (!Carteira::debitar($pdo, $usuarioId, $preco, $agora)) {
                    $falta = $preco - Carteira::saldo($pdo, $usuarioId);
                    throw new \DomainException($falta === 1 ? 'Falta 1 moeda.' : "Faltam $falta moedas.");
                }

                $pdo->prepare('INSERT INTO itens_usuario (usuario_id, item_id, preco_pago, adquirido_em) VALUES (?, ?, ?, ?)')
                    ->execute([$usuarioId, $itemId, $preco, $agora]);
                $itemUsuarioId = (int) $pdo->lastInsertId();

                Carteira::registrarCompra($pdo, $usuarioId, $itemUsuarioId, $preco, $agora);

                return $item + ['item_usuario_id' => $itemUsuarioId];
            });
        } catch (PDOException $e) {
            // Duas compras simultâneas do mesmo item: o índice único barra a segunda, e o débito é desfeito.
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new \DomainException('Este item já está na sua coleção.');
            }
            throw $e;
        }
    }

    /** Equipa um item da coleção na categoria dele, ou volta ao visual padrão ($itemUsuarioId = 0). */
    public static function equipar(PDO $pdo, int $usuarioId, string $categoria, int $itemUsuarioId): void
    {
        if (!isset(self::CATEGORIAS[$categoria])) {
            throw new \DomainException('Categoria inválida.');
        }
        if ($itemUsuarioId === 0) {
            $pdo->prepare('DELETE FROM itens_equipados WHERE usuario_id = ? AND categoria = ?')->execute([$usuarioId, $categoria]);
            return;
        }

        $stmt = $pdo->prepare('
            SELECT 1 FROM itens_usuario iu JOIN itens_loja i ON i.id = iu.item_id
            WHERE iu.id = ? AND iu.usuario_id = ? AND i.categoria = ?
        ');
        $stmt->execute([$itemUsuarioId, $usuarioId, $categoria]);
        if (!$stmt->fetchColumn()) {
            throw new \DomainException('Você só pode equipar itens da sua coleção.');
        }

        $pdo->prepare('
            INSERT INTO itens_equipados (usuario_id, categoria, item_usuario_id) VALUES (?, ?, ?)
            ON CONFLICT (usuario_id, categoria) DO UPDATE SET item_usuario_id = excluded.item_usuario_id
        ')->execute([$usuarioId, $categoria, $itemUsuarioId]);
    }

    /** Slugs dos itens equipados, por categoria. Viram classes CSS no neurônio. */
    public static function equipados(PDO $pdo, int $usuarioId): array
    {
        $stmt = $pdo->prepare('
            SELECT e.categoria, i.slug
            FROM itens_equipados e
            JOIN itens_usuario iu ON iu.id = e.item_usuario_id
            JOIN itens_loja i ON i.id = iu.item_id
            WHERE e.usuario_id = ?
        ');
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
