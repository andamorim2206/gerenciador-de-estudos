# Gerenciador de Estudos

Gerenciador de estudos gamificado: em vez de uma árvore (como no Forest), **um neurônio evolui** conforme você acumula tempo de foco.
PHP puro (sem frameworks) com PostgreSQL (o SQLite continua disponível como alternativa sem servidor).

## Funcionalidades

- **Cronômetro de foco**: contagem regressiva de 10 a 180 min, sem pausa. A sessão fica salva no servidor: recarregar, fechar a aba ou trocar de aparelho não a perde. Desistir não rende nada.
- **Neurônio (XP)**: 1 XP por minuto de sessão concluída, 7 estágios visuais. O estágio nunca regride.
- **Moedas**: exatamente 1 por sessão concluída, com extrato imutável.
- **Loja**: itens cosméticos (skins, cenários, efeitos) comprados com moedas, permanentes na coleção.
- **Painel**, **histórico** por dia com filtro por matéria e **matérias** com cor, arquivamento e reativação.
- **Calendário** de tarefas do protótipo (planejamento; não gera XP nem moedas).

XP e moedas são independentes: XP mede **tempo**; moedas medem **quantidade de sessões**.

## Requisitos

Docker (recomendado), ou PHP 8.1+ com as extensões `pdo_pgsql` (ou `pdo_sqlite`) e `mbstring` (para os testes de concorrência, também `pcntl` e `posix`).

## Como rodar sem Docker (SQLite)

```bash
php -n bin/criar-usuario.php davi@exemplo.com "Davi"   # cria a conta (pede a senha)
./iniciar.sh                                          # porta 8000 (ou a próxima livre)
```

As contas podem ser criadas pela tela de cadastro (`/cadastro`, link na tela de login) ou por esse script. Não há confirmação de e-mail. A primeira conta criada herda as matérias e tarefas que já existiam no banco.

Sem a variável `DB_HOST`, o app usa SQLite: o banco fica em `data/estudos.sqlite` e é migrado automaticamente; antes de aplicar uma migração, é feita uma cópia em `data/estudos.sqlite.backup-*`.
Para usar outro arquivo: `ESTUDOS_DB=/caminho/teste.sqlite ./iniciar.sh`.
Para usar um PostgreSQL próprio: `DB_HOST=localhost DB_PORT=5432 DB_NAME=estudos DB_USER=estudos DB_PASSWORD=... ./iniciar.sh`.

## Rodando com Docker (PostgreSQL)

Não precisa de PHP nem de PostgreSQL instalados na máquina, só do Docker. O compose sobe dois serviços:
`db` (PostgreSQL 17, dados no volume `pgdata`) e `app` (o site). As tabelas são criadas automaticamente na primeira subida
(`migrations/pgsql/`).

```bash
docker compose up -d --build        # http://localhost:8000  (outra porta: PORTA=8080 docker compose up -d)
docker compose exec app php bin/criar-usuario.php davi@exemplo.com "Davi"   # ou use a tela /cadastro
docker compose logs -f              # acompanhar o servidor
docker compose down                 # parar
```

- **Senha do banco**: `estudos` por padrão; para trocar, crie um `.env` com `DB_PASSWORD=...` antes da primeira subida.
- **Acessar o banco**: `docker compose exec db psql -U estudos estudos`, ou um cliente gráfico em `localhost:5433` (mude com `PG_PORTA`).
- **Trazer os dados do SQLite** (só num PostgreSQL ainda sem usuários; o arquivo SQLite não é alterado):
  `docker compose exec app php bin/migrar-sqlite-para-postgres.php data/estudos.sqlite`
- **Backup**: `docker compose exec db pg_dump -U estudos estudos > backup.sql`
- `docker compose down -v` **apaga** o volume do banco; sem o `-v`, os dados ficam.
- O container do app roda com UID/GID 1000; se o seu usuário for outro, use `UID=$(id -u) GID=$(id -g) docker compose up -d`.

## Scripts

| Comando | O que faz |
| --- | --- |
| `php -n tests/run.php` | Testes das regras de XP, moedas e loja, incluindo concorrência (no PostgreSQL se `DB_HOST` estiver definido, cada teste num schema temporário; senão, no SQLite) |
| `php -n bin/verificar-consistencia.php` | Confere saldo = soma do extrato e XP = soma das sessões para todos os usuários |
| `php -n bin/ajustar-moedas.php email valor "motivo"` | Correção manual de saldo, registrada no extrato |
| `php bin/migrar-sqlite-para-postgres.php [arquivo]` | Copia todos os dados de um SQLite para o PostgreSQL vazio, mantendo os ids |

No Docker, rode qualquer um deles com `docker compose exec app php ...`.

## Estrutura

```
public/          raiz web: index.php (front controller) e assets/
src/             regras de negócio (Sessoes, Neuronio, Carteira, Loja, Materias…) e Controllers/
views/           páginas e partials (o neurônio em SVG fica em views/partials/neuronio.php)
migrations/      migrações versionadas do SQLite; migrations/pgsql/ as do PostgreSQL
bin/             scripts de linha de comando
tests/           testes
data/            banco SQLite, quando usado (fora da raiz web)
```

## Regras de integridade

- Concluir uma sessão é uma única transação: status, +XP e +1 moeda. O crédito só acontece na transição de `em_andamento` para `concluida`, e o extrato tem `sessao_id` único: repetir a requisição nunca credita duas vezes.
- Comprar é uma única transação com lock de escrita: o débito só acontece se `saldo >= preço`, e o banco também impede saldo negativo (`CHECK`) e item repetido (`UNIQUE`).
- O extrato e as sessões concluídas não podem ser alterados nem apagados (triggers no banco).
- Duração e preço são sempre calculados no servidor; os valores enviados pelo navegador são ignorados.
