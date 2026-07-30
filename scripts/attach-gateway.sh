#!/usr/bin/env bash
# ═══ وصل هاي روت ببوّابة موازين — بتراجعٍ تلقائي ═══
#
#   bash scripts/attach-gateway.sh [النطاق]
#
# منفذا 80 و443 مشغولان ببوّابة موازين، فلا بوّابة ثانية. والحلّ كتلة موقعٍ
# ثانية في `Caddyfile` نفسه — أي **تعديلٌ في إعداد موازين**، وخطأٌ فيه يُسقط
# `mawazinswift.com` معه.
#
# فالسكربت لا يطلب من أحد تحرير الملف بيده: ينسخ نسخة احتياطية، ويُلحق الكتلة،
# ويتحقّق من الصيغة، **ويستعيد النسخة القديمة إن كانت فاسدة** ثم يخرج بإخفاق.
# وإن صحّت، يُعيد التحميل بـ`reload` لا `restart` — فموازين لا ينقطع لحظة.
#
# والنطاق يُكتب في الملف صريحًا لا كمتغيّر بيئة: المتغيّر يحتاج تعديلًا ثانيًا
# في `docker-compose.prod.yml` وإعادةَ إنشاء الحاوية — أي انقطاعًا لموازين لأجل
# قيمةٍ لا تتغيّر.
set -euo pipefail

DOMAIN="${1:-hiroote.com}"
MAWAZIN_DIR="${MAWAZIN_DIR:-/srv/mazeen/app}"
CADDY_CONTAINER="${CADDY_CONTAINER:-mazeen-staging-caddy-1}"
CADDYFILE="${MAWAZIN_DIR}/Caddyfile"
BLOCK="$(cd "$(dirname "$0")/.." && pwd)/deploy/hiroote.caddy"

say() { printf '\n\033[1;36m▸ %s\033[0m\n' "$1"; }
die() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

[ -f "$CADDYFILE" ] || die "لا ملف ${CADDYFILE} — تأكّد من MAWAZIN_DIR."
[ -f "$BLOCK" ] || die "لا ملف ${BLOCK} — شغّل: git pull --ff-only origin main"
docker inspect "$CADDY_CONTAINER" >/dev/null 2>&1 || die "لا حاوية ${CADDY_CONTAINER} تعمل."

# مُلحَقٌ سلفًا؟ الإلحاق ثانيًا يُنتج كتلتين للنطاق نفسه، و Caddy ترفض الإعداد
# كله — فيسقط موازين لأجل تشغيلٍ مكرر.
if grep -q "^${DOMAIN} {" "$CADDYFILE"; then
    say "الكتلة موصولة سلفًا لـ${DOMAIN} — لم يُلمس شيء."
    exit 0
fi

BACKUP="${CADDYFILE}.$(date +%Y%m%d-%H%M%S).bak"
cp "$CADDYFILE" "$BACKUP"
say "نسخة احتياطية: ${BACKUP}"

{
    printf '\n# ═══ هاي روت — مركز التحكم (أُلحقت بـ scripts/attach-gateway.sh) ═══\n'
    sed "s|{\$HIROOTE_DOMAIN}|${DOMAIN}|g" "$BLOCK"
} >> "$CADDYFILE"

say "التحقق من الصيغة"

if ! docker exec "$CADDY_CONTAINER" caddy validate --config /etc/caddy/Caddyfile; then
    cp "$BACKUP" "$CADDYFILE"
    die "الإعداد فاسد — استُعيدت النسخة القديمة وموازين كما كان. أرسل الخطأ أعلاه."
fi

say "إعادة التحميل بلا انقطاع"

if ! docker exec "$CADDY_CONTAINER" caddy reload --config /etc/caddy/Caddyfile; then
    cp "$BACKUP" "$CADDYFILE"
    docker exec "$CADDY_CONTAINER" caddy reload --config /etc/caddy/Caddyfile || true
    die "أخفق التحميل — استُعيدت النسخة القديمة."
fi

say "موازين ما زال يعمل؟"

# ⚠️ `|| true` مقصودة، وغيابها كان عيبًا حقيقيًّا: صورة Caddy لا تحمل `wget`،
# فأخفق الفحص، ومع `set -e` **مات السكربت بعد أن أنجز عمله كله** — فقرأ المشغّل
# توقّفًا صامتًا حيث كان النجاح. وفحصٌ تجميليّ لا يجوز أن يُسقط ما نجح.
#
# والفحص من المضيف بـ`curl` لا من داخل الحاوية: يمرّ بالبوّابة كما يمرّ الزائر،
# فيقيس ما يهمّ فعلًا لا ما يُرى من الداخل.
for site in "https://mawazinswift.com/" "https://${DOMAIN}/"; do
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$site" 2>/dev/null || true)"
    printf '  %-34s → %s\n' "$site" "${code:-لا رد}"
done

printf '\n  وشهادة %s تُطلب عند أول زيارة، فـ000 أو 502 في أول محاولة عاديّ.\n' "$DOMAIN"

cat <<DONE

‣ وُصلت. بقي أمران يقعان تلقائيًّا ويحتاجان دقيقةً أو دقيقتين:

  ١) شهادة ${DOMAIN} تُطلب من Let's Encrypt عند أول زيارة.
  ٢) فتح https://${DOMAIN} في المتصفح — أول فتح قد يتأخّر حتى تصل الشهادة.

  وإن ظهر خطأ شهادة، افحص السجلّ:
    docker logs --tail=40 ${CADDY_CONTAINER}

DONE
