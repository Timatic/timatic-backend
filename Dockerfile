FROM dunglas/frankenphp:1.9-php8.4-alpine AS app

RUN install-php-extensions \
	pdo_mysql \
	gd \
	intl \
	zip \
	opcache \
    json \
    redis

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

RUN apk add --no-cache mysql-client \
    && echo -e "[client]\nssl-verify-server-cert=FALSE" > /etc/my.cnf

# Add the app to the container
COPY ./ /app/
