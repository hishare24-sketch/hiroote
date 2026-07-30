#!/usr/bin/env bash
# ═══ تهيئة بيئة الإنتاج — مرة واحدة على الخادم ═══
#
#   bash scripts/setup-env.sh [النطاق]
#
# يولّد `APP_KEY` وكلمة مرور القاعدة **بلا عرضهما**: من ينسخ سرًّا من الشاشة
# يتركه في سجلّ الطرفية وفي صور الشاشة. والتأكيد يُطبع طولًا لا قيمة.
#
# ولا يُعيد التوليد على ملفٍ قائم فيه مفتاح: مفتاحٌ جديد يُبطل كل سرٍّ مشفَّر
# محفوظ — أسرار جسور المشاريع وتوقيعات التنبيهات — بلا رسالة خطأ واحدة، إلى أن
# يُخفق أول نداء ولا يُعرف السبب.
set -euo pipefail

cd "$(dirname "$0")/.."

DOMAIN="${1:-hiroote.com}"

if [ ! -f .env.production.example ]; then
    echo "لا ملف .env.production.example — أنت على إصدارٍ أقدم من إعداد النشر." >&2
    echo "شغّل: git pull --ff-only origin main" >&2
    exit 1
fi

# مفتاحٌ قائم = هذا الخادم مُهيَّأ سلفًا. لا يُلمس.
if [ -f .env ] && grep -qE '^APP_KEY=base64:.+' .env; then
    echo "‣ .env مُهيَّأ سلفًا ويحمل APP_KEY — لم يُلمس شيء."
    echo "  ولو أردت مفتاحًا جديدًا فاعلم أنه يُبطل كل سرٍّ مشفَّر محفوظ في القاعدة."
    grep '^APP_URL=' .env
    exit 0
fi

cp .env.production.example .env

# `|` فاصلًا لا `/`: قيمة base64 تحوي `/` فتكسر التعبير.
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=')|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=\"alerts@${DOMAIN}\"|" .env

chmod 600 .env

echo
echo "‣ جاهز:"
grep '^APP_URL=' .env
awk -F= '/^(APP_KEY|DB_PASSWORD)=/{
    v = substr($0, index($0, "=") + 1)
    printf "  %s ← %s\n", $1, (length(v) > 0 ? length(v) " محرفًا ✓" : "فارغ ✗")
}' .env
echo
echo "‣ بقي قبل النشر: احفظ APP_KEY خارج الخادم. اعرضه مرة واحدة بـ:"
echo "    grep '^APP_KEY=' /srv/hiroote/.env"
echo "  ضياعه = ضياع كل سرٍّ مشفَّر في اللوحة بلا استرجاع."
