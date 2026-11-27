# Gerenciador de Estudos — PHP puro + SQLite
FROM php:8.3-cli-alpine

# pdo_pgsql para o PostgreSQL (banco principal); pdo_sqlite e mbstring já vêm na imagem oficial;
# pcntl é usado nos testes de concorrência.
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .deps-build postgresql-dev \
    && docker-php-ext-install pcntl pdo_pgsql \
    && apk del .deps-build \
    && echo "date.timezone=America/Sao_Paulo" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /app
COPY . .

# Pasta do SQLite (opcional, montada pelo docker-compose): usada só sem DB_HOST e para migrar os dados antigos.
RUN mkdir -p data && chmod 0777 data

# Atende várias requisições em paralelo no servidor embutido.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000

# Aplica as migrações pendentes (com backup) e sobe o servidor.
CMD ["sh", "-c", "php -r 'require \"src/bootstrap.php\"; App\\Database::pdo();' && exec php -S 0.0.0.0:8000 -t public public/index.php"]
