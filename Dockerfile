FROM php:8.3-cli-alpine

# O código usa PDO MySQL (cURL já vem na imagem base)
RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . /app

# Render injeta PORT em runtime (padrão 10000)
ENV PORT=10000
EXPOSE 10000

# Não roda como root
USER www-data

# Servidor embutido do PHP, escutando em 0.0.0.0 na porta do Render
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /app"]
