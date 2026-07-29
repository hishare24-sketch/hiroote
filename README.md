# Hiroote AI

منصة مستقلة لإدارة المساعد الذكي وتشغيله وربطه بمنصة **Hi-Share** — طبقة ذكاء وإدارة ومراقبة وتحليل وتنفيذ أدوات مصرح بها عبر API، وليست بديلًا عن أنظمة Hi-Share الأساسية.

## التقنيات

| الطبقة | التقنية |
| --- | --- |
| الخلفية | Laravel 13 (PHP 8.4) — Modular Monolith بسبعة Domains |
| الواجهة | Inertia v3 + React 19 + TypeScript strict + Tailwind CSS 4 (RTL + Light/Dark) |
| قاعدة البيانات | PostgreSQL 16 |
| الكاش والطوابير | Redis 7 |

## التشغيل محليًا

```bash
docker compose up -d              # PostgreSQL + Redis
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # حسابات تجريبية لكل دور، كلمة المرور: password
composer dev                      # serve + queue + logs + vite معًا
```

ثم افتح `http://localhost:8000` وسجل الدخول بـ `admin@hiroote.test`.

> **ويندوز / Laravel Herd:** لا يحتاج المشروع امتداد `phpredis`؛ الافتراضي `REDIS_CLIENT=predis`
> وهو عميل مكتوب بالكامل بـ PHP. حوّله إلى `phpredis` في الإنتاج للحصول على أداء أعلى.

## بوابات الجودة

```bash
composer quality    # Pint + PHPStan (level 8) + Tests
npm run lint && npm run types && npm run format:check
```

تعمل البوابات نفسها في CI مع فحص الأسرار؛ الدمج المباشر إلى `main` ممنوع.

## الوثائق

المرجعية الكاملة في [`docs/`](docs/) — المنتج والنطاق، المعمارية والبيانات، معايير الهندسة، سير عمل الوكلاء، التشغيل والأمن، ومتطلبات تصميم الشاشات. القرارات المعمارية في [`docs/adr/`](docs/adr/). دليل وكلاء الذكاء الاصطناعي في [`CLAUDE.md`](CLAUDE.md).
