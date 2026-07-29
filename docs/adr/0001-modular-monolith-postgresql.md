# ADR-0001 — Modular Monolith على Laravel/Inertia مع PostgreSQL

- **الحالة:** معتمد
- **التاريخ:** 2026-07-29
- **أصحاب القرار:** مالك المنتج + Product & Architecture Agent

## السياق

وثيقة المعمارية (`docs/02`) تفرض Independent Modular Monolith بمستودع مستقل يجمع Laravel وReact عبر Inertia، دون أن تحدد محرك قاعدة البيانات أو تفاصيل إنفاذ الحدود.

## القرار

1. **Laravel 13 + Inertia v3 + React 19 + TypeScript strict + Tailwind 4** في مستودع واحد.
2. **PostgreSQL 16** محركًا وحيدًا لكل البيئات (تطوير، اختبار، إنتاج):
   - `JSONB` للحقول المرنة المنصوص عليها في وثيقة 02 §8.
   - Triggers تنفذ قاعدة «Immutable logs» على مستوى المحرك (`audit_logs`).
   - Partitioning مدعوم أصلًا لجداول الرسائل والأحداث عند نمو الحجم.
   - امتداد `pgvector` متاح لاحقًا للبحث المتجهي (وثيقة 02 §11) دون خدمة إضافية.
3. **Redis 7** للكاش والطوابير والأقفال الموزعة (وثيقة 02 §9-10).
4. حدود الـ Domains تنفذ بالبنية (`app/Domains/*`) وبمزود `DomainServiceProvider` الذي يحمّل مسارات وهجرات كل Domain من داخله.
5. الاختبارات تعمل على قاعدة `hiroote_test` بنفس المحرك — لا SQLite — إنفاذًا لوثيقة 03 §10.

## البدائل المرفوضة

- **MySQL/MariaDB:** لا يوفر triggers على TRUNCATE ولا partitioning declarative بنفس النضج، ولا pgvector.
- **SQLite للتطوير:** يخفي فروقات الـ migrations والـ constraints عن الإنتاج.
- **Microservices من البداية:** خارج النطاق صراحة (وثيقة 01 §6).

## الأثر

- أي كود يفترض سلوك MySQL يعد خطأ.
- فصل أي خدمة مستقبلًا يتطلب قياسًا يثبت الحاجة وADR جديدًا (وثيقة 02 §13).
