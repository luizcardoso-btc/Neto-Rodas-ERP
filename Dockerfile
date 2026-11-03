# Dockerfile do repositorio completo (codigo em pastas normais).
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

RUN printf '%s\n' 'ServerTokens Prod' 'ServerSignature Off' 'ServerName localhost' '<Directory /var/www/html/public/uploads>' '    <FilesMatch "\.(php|phtml|phar)$">' '        Require all denied' '    </FilesMatch>' '    Options -Indexes' '</Directory>' '<FilesMatch "^\.">' '    Require all denied' '</FilesMatch>' > /etc/apache2/conf-available/erp.conf
RUN a2enconf erp
RUN printf '%s\n' 'display_errors=Off' 'log_errors=On' 'error_log=/dev/stderr' 'upload_max_filesize=4M' 'post_max_size=8M' 'date.timezone=America/Bahia' > /usr/local/etc/php/conf.d/erp.ini

WORKDIR /var/www/html
COPY . /var/www/html/
# Falha cedo, com mensagem clara, se algum arquivo essencial faltar.
RUN for f in bin/migrate.php bin/create-admin.php src/bootstrap.php src/Database.php src/Diagnostics.php pages/login.php public/index.php migrations/001_init.sql; do \
      [ -f "$f" ] || { echo "ERRO: $f nao esta no repositorio."; ls -la; exit 1; }; \
    done
RUN mkdir -p public/uploads && chown -R www-data:www-data public/uploads

RUN printf '%s\n' '#!/bin/sh' 'PORT="${PORT:-80}"' 'echo "[ERP] MPM antes do ajuste:"; grep -rn "LoadModule mpm_" /etc/apache2 --include=*.load --include=*.conf 2>/dev/null | grep -v mods-available' 'rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf' 'for f in $(grep -rlE "^[[:space:]]*LoadModule[[:space:]]+mpm_" /etc/apache2 2>/dev/null | grep -v mods-available); do sed -ri "/^[[:space:]]*LoadModule[[:space:]]+mpm_/d" "$f"; done' 'ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load' 'ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf' 'sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf' 'sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf' 'mkdir -p /var/www/html/public/uploads' 'chown -R www-data:www-data /var/www/html/public/uploads || true' 'cd /var/www/html' 'echo "[ERP] MYSQL_URL definida: $([ -n "$MYSQL_URL" ] && echo sim || echo nao) | DB_HOST definida: $([ -n "$DB_HOST" ] && echo sim || echo nao)"' 'apache2ctl configtest || echo "[ERP] ATENCAO: configuracao do Apache invalida (veja acima)."' '(' '  i=0' '  until php bin/migrate.php; do' '    i=$((i+1))' '    if [ "$i" -ge 60 ]; then echo "[ERP] Banco indisponivel; migracoes nao aplicadas."; exit 1; fi' '    echo "[ERP] Aguardando banco... ($i)"; sleep 5' '  done' '  php bin/create-admin.php --if-empty || echo "[ERP] Admin nao criado: defina ADMIN_PASSWORD (10+ caracteres)."' ') &' 'echo "[ERP] Iniciando servidor web na porta ${PORT}"' 'exec ${ERP_WEB_CMD:-apache2-foreground}' > /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
