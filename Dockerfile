FROM phpswoole/swoole:latest
MAINTAINER author "dean7410@163.com"

ARG timezone

ENV TIMEZONE=${timezone:-"Asia/Shanghai"} \
    APP_ENV=prod \
    SCAN_CACHEABLE=(true)

RUN set -ex \
    # install composer
    && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    # show php version and extensions
    && pecl install redis \
    && docker-php-ext-enable redis \
    && php -v \
    && php -m \
    && php --ri swoole \
    #  ---------- some config ----------
    && cd /usr/local/etc/php \
    && mv php.ini-development php.ini \
    # - config PHP
    && { \
        echo "upload_max_filesize=128M"; \
        echo "post_max_size=128M"; \
        echo "memory_limit=1G"; \
        echo "date.timezone=${TIMEZONE}"; \
    } | tee conf.d/simoole.ini \
    # - config timezone
    && ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone

WORKDIR /www

COPY . /www

RUN chmod u+x simoole

#RUN composer install

EXPOSE 9200

ENTRYPOINT ["/www/simoole", "start"]
