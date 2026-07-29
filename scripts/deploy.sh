#!/usr/bin/env bash
# ═══ نشر هاي روت على الخادم ═══
#
# يُشغَّل **على الخادم** من داخل مجلد المشروع:  bash scripts/deploy.sh
#
# `set -e` مقصودة: خطوةٌ تُخفق توقف ما بعدها. ونشرٌ يكمل بعد هجرةٍ فاشلة يترك
# كودًا جديدًا على قاعدةٍ قديمة — وهو أسوأ من نشرٍ لم يقع.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -p hiroote -f docker-compose.prod.yml"

say() { printf '\n\033[1;36m▸ %s\033[0m\n' "$1"; }

# ─── ما لا يُنشر بدونه ───
if [ ! -f .env ]; then
    echo "لا ملف .env — انسخ .env.production.example إليه واملأه أولًا." >&2
    exit 1
fi

# shellcheck disable=SC1091
set -a; . ./.env; set +a

for required in APP_KEY APP_URL DB_PASSWORD; do
    if [ -z "${!required:-}" ]; then
        echo "المتغيّر ${required} فارغ في .env — النشر يتوقف." >&2
        exit 1
    fi
done

# الشبكة المشتركة مع موازين: بدونها يُقلع كل شيء ولا تصله البوّابة، فيُقرأ
# العطل «التطبيق لا يعمل» وهو يعمل ولا يُرى.
GATEWAY="${GATEWAY_NETWORK:-mazeen-staging_app}"
if ! docker network inspect "${GATEWAY}" >/dev/null 2>&1; then
    echo "شبكة البوّابة «${GATEWAY}» غير موجودة." >&2
    echo "اعرف اسمها الصحيح بـ: docker network ls | grep app  ثم ضعه في GATEWAY_NETWORK بملف .env" >&2
    exit 1
fi

say "سحب آخر main"
git pull --ff-only origin main

say "بناء الصورة"
$COMPOSE build

say "إقلاع القاعدة و Redis"
$COMPOSE up -d pgsql redis

say "الهجرات"
# خطوة صريحة قبل إقلاع التطبيق: ثلاث حاويات تُهاجر معًا تتسابق على القاعدة نفسها.
$COMPOSE run --rm --no-deps app php artisan migrate --force

say "إقلاع التطبيق والطابور والمجدول"
$COMPOSE up -d --remove-orphans

say "الحالة"
$COMPOSE ps

cat <<'DONE'

▸ بقي التحقّق — والنشر لا يُعدّ ناجحًا قبله:

  ١) الحاويات الخمس كلها Up أعلاه (app · queue · scheduler · pgsql · redis).
  ٢) افتح النطاق في المتصفح وسجّل الدخول.
  ٣) تأكّد أن المجدول يعمل:
       docker compose -p hiroote -f docker-compose.prod.yml logs --tail=20 scheduler

DONE
