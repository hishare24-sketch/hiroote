# ═══ صورة الإنتاج — بناء على مرحلتين، والصورة النهائية بلا أدوات بناء ═══
#
# الأصول تُبنى هنا لا على الخادم: `npm ci` على خادمٍ صغير يستهلك ذاكرةً تكفي
# لإسقاط ما يعمل بجانبه، وبناءٌ يقع وقت النشر يجعل النشر نفسه قابلًا للإخفاق
# لسببٍ لا علاقة له بالكود.
#
# والصورة واحدة للثلاثة (الويب · الطابور · المجدول): نسخةٌ واحدة من الكود تعمل
# في الثلاثة تمنع أن يعمل عاملُ الطوابير على إصدارٍ غير الذي يخدم المستخدم.

# ─── ١) الأصول ───
FROM node:22-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.ts tsconfig.json ./

RUN npm run build

# ─── ٢) اعتماديات PHP ───
FROM composer:2 AS vendor

WORKDIR /build

COPY composer.json composer.lock ./

# بلا سكربتات ولا autoloader هنا: `artisan` غير منسوخ بعد، وتشغيلها يسقط البناء.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# ─── ٣) الصورة النهائية ───
FROM php:8.4-fpm-alpine AS runtime

# nginx و supervisor داخل الصورة: حاويةٌ واحدة تخدم الويب تُبقي الأصول المبنيّة
# ونقطة دخولها في مكانٍ واحد، بلا حجمٍ مشترك بين حاويتين يسهل أن يتقادم.
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        opcache \
        bcmath \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear \
    # nginx يُسقط صلاحياته إلى www-data (المستخدم الذي يملك ملفات التطبيق)،
    # ومجلداته المؤقتة مملوكة لـ nginx من الحزمة. بلا هذا يُخفق أول رفعٍ يتجاوز
    # الذاكرة المؤقتة — أي أول صورة شاشة كبيرة — بـ500 لا برسالة تقول السبب.
    && mkdir -p /var/lib/nginx/tmp /var/log/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx

WORKDIR /var/www/html

COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-hiroote.ini
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# composer نفسه لازمٌ في هذه المرحلة: صورة php-fpm لا تحمله، وبناء المُحمِّل
# أدناه يحتاجه. ويُحذف بعده فلا يبقى في صورة الإنتاج.
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer

COPY --from=vendor /build/vendor vendor
COPY . .
COPY --from=assets /build/public/build public/build

# `--no-dev` هنا أيضًا: مُحمِّلٌ يعرف حزم التطوير يحاول تحميلها إن أُشير إليها
# سهوًا، فيسقط في الإنتاج وحده.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -f /usr/local/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -rf tests .github docs

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
