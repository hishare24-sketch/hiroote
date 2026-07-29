# Hiroote AI — دليل وكلاء الذكاء الاصطناعي

مركز تحكم لكل مشاريع الشركة — لكل مشروع لوحة مستقلة، والربط مع كل مشروع عبر API فقط.
Hi-Share هو المشروع الأول لا نطاق المنصة (ADR-0003).
**اقرأ الوثائق في `docs/` قبل أول مساهمة** (قاعدة ملزمة — وثيقة 04 §5).

## المرجعية

| الوثيقة | المحتوى |
| --- | --- |
| `docs/01-المنتج-والنطاق.md` | التعريف، الأهداف، النطاق، المراحل |
| `docs/02-المعمارية-والبيانات.md` | Modular Monolith، الجداول، Queues، التكامل |
| `docs/03-معايير-الهندسة-والتصميم.md` | معايير Laravel/React، نظام التصميم، Definition of Done |
| `docs/04-سير-عمل-الذكاء-والوكلاء.md` | دورة المهمة، بوابات الموافقة، قاعدة التوقف |
| `docs/05-التشغيل-والأمن-والجودة.md` | الاختبارات، بوابات الجودة، الأمن، المراقبة |
| `docs/06-متطلبات-تصميم-لوحة-الإدارة.md` | مواصفات الشاشات العشر وحالات التصميم |
| `docs/adr/` | القرارات المعمارية المسجلة |

## البنية

- **Laravel 13 + Inertia + React 19 + TypeScript strict + Tailwind 4** — قاعدة البيانات **PostgreSQL 16**، الكاش والطوابير **Redis**.
- الكود مقسوم إلى Domains تحت `app/Domains/{Projects,Providers,Conversations,Knowledge,Integrations,Analytics,Alerts,Administration}` — كل Domain يحوي `Actions, DTOs, Models, Policies, Jobs, Events, Services, Enums`.
- تسجيل مسارات/هجرات الـ Domain يتم تلقائيًا عبر `app/Providers/DomainServiceProvider.php` (ضع `Routes/web.php` أو `Database/Migrations` داخل الـ Domain).
- الواجهة: صفحات Inertia في `resources/js/Pages`، المكونات المشتركة في `resources/js/Components/ui`، الأنواع المشتركة في `resources/js/types/index.ts`.
- **RTL افتراضي** — استخدم الخصائص المنطقية (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start`, `end`) وليس `ml/mr/pl/pr/left/right`.
- **الهوية البصرية = نظام موازين** (ADR-0002). التوكنات كلها في `resources/css/app.css`: الخط Cairo، اللكنة `--color-accent`، الأسطح والحدود والنصوص، الزوايا 5/8/12/16/999، المسافات أساس 4px، ومقاسات الخط `text-micro/caption/body/title/display/metric`.
- **توكنز فقط** — يمنع لون أو مسافة أو زاوية أو مقاس خط بقيمة صريحة في أي مكوّن. استخدم الأسماء الدلالية (`bg-surface-raised`, `text-fg-muted`, `text-body`, `rounded-card`…). المرجع الكامل: `mawazin1/front/DESIGN-SYSTEM.md`.

## قواعد غير قابلة للتفاوض

1. **ممنوع الاتصال المباشر بقاعدة بيانات أي مشروع** — التكامل عبر REST فقط (وثيقة 02 §5).
2. **ممنوع استدعاء مزود ذكاء خارج طبقة الـ Orchestrator** عند بنائها (وثيقة 02 §4).
3. **الصلاحيات**: كل صلاحية تُعرّف في `Permission` enum وتُمنح عبر مصفوفة `Role::permissions()` — لا `Gate::before` ولا تجاوزات. الدور يُحلّ **لكل مشروع** من `project_user`؛ و`is_platform_admin` عضويةٌ بدور `SystemAdmin` في كل مشروع لا استثناءٌ من البوابة. الواجهة تخفي فقط؛ الخادم يعيد التحقق دائمًا.
4. **كل استعلام تشغيلي مقيَّد بالمشروع الحالي** على الخادم — لا يُعتمد على الواجهة في العزل.
5. **`audit_logs` append-only** — محمي بـ triggers في PostgreSQL. كل تغيير حساس يسجل عبر `RecordAuditEntry`.
6. **الاختبار على PostgreSQL** (`hiroote_test`) وليس SQLite — نفس محرك الإنتاج (وثيقة 03 §10).
7. **لا تدعي نجاح اختبار لم تشغله** (وثيقة 04 §5).
8. **قاعدة التوقف** (وثيقة 04 §12): توقف واطلب قرارًا عند تضارب متطلبات، احتمال فقد بيانات، تغيير أمني كبير، أو تجاوز نطاق يمس الإنتاج.

## بوابات الجودة (شغّلها قبل أي تسليم)

```bash
composer lint          # Pint --test
composer analyse       # PHPStan level 8
composer test          # PHPUnit على hiroote_test
npm run lint           # ESLint (strict type-checked)
npm run types          # tsc --noEmit
npm run format:check   # Prettier
npm run build          # Vite
```

كلها تعمل في CI (`.github/workflows/ci.yml`) مع فحص أسرار Gitleaks. الدمج المباشر إلى `main` ممنوع.

## بيئة التطوير

```bash
docker compose up -d          # PostgreSQL 16 + Redis 7
cp .env.example .env && php artisan key:generate
php artisan migrate --seed    # يزرع مستخدمًا لكل دور، كلمة المرور: password
composer dev                  # serve + queue + logs + vite
```

حسابات التطوير: `admin@hiroote.test` (مدير النظام) و `{role}@hiroote.test` لكل دور.

## اصطلاحات

- Conventional Commits، فروع قصيرة العمر، PR واحد لكل تغيير منطقي.
- التعليقات تشرح «لماذا» وتُكتب حيث يلزم فقط؛ رسائل الأخطاء الموجهة للمستخدم بالعربية.
- أخطاء API بصيغة موحدة: `{ error: { code, message, details, request_id } }` عبر `ApiErrorResponse`.
- كل قرار معماري جديد يوثق في `docs/adr/` قبل التنفيذ أو معه.
