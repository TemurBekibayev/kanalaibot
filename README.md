# AI Telegram Kanal Manager Bot

Telegram kanal egalari uchun AI yordamchisi: post yaratish, formatlash, rejalashtirish, eski postlarni tozalash, dublikat aniqlash va statistika berish.

## 🚀 Texnologik Stack

- **Backend:** Laravel 11 (PHP 8.3)
- **Database:** MySQL 8
- **Cache/Queue:** Redis + Laravel Horizon
- **Admin Panel:** Laravel Filament v3
- **Mini App:** Vue 3 (Telegram Web App SDK)
- **AI Engine:** Google Gemini, Groq, OpenRouter (Fallback circuit breaker)

---

## 🛠️ O'rnatish va Joylashtirish (Deployment)

### 1. Muhitni sozlash (.env)

Loyihaning ildiz papkasida `.env.example` faylidan nusxa olib, `.env` faylini yarating:

```bash
cp .env.example .env
```

Quyidagi o'zgaruvchilarni o'z qiymatlaringiz bilan to'ldiring:

- `TELEGRAM_BOT_TOKEN` - `@BotFather` orqali olingan token.
- `TELEGRAM_ADMIN_CHAT_ID` - Admin xabarnomalari boradigan Telegram ID.
- `GEMINI_API_KEY` - Google AI Studio bepul kaliti.
- `GROQ_API_KEY` - Groq Console kaliti.
- `OPENROUTER_API_KEY` - OpenRouter paid API kaliti.

### 2. Docker Konteynerlarni ishga tushirish

Barcha xizmatlarni (Nginx, PHP-FPM, MySQL, Redis, Horizon) ishga tushirish uchun quyidagi buyruqni bosing:

```bash
docker compose up -d --build
```

### 3. Ma'lumotlar bazasini tayyorlash va Seeding

Konteyner ichida migratsiyalar va standart admin panel profilini yaratish seederini ishga tushiring:

```bash
docker compose exec app php artisan migrate --seed
```

### 4. Telegram Webhook ulash

Bot faqat webhook orqali ishlaydi (HTTPS talab qilinadi). Webhook manzilini ulash uchun quyidagi URL'ga so'rov yuboring (brauzer orqali yoki curl):

```bash
curl https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://<sizning_domeningiz.uz>/api/telegram/webhook
```

---

## 🔑 Admin Panel (Filament) Kirish ma'lumotlari

Admin panelga o'tish: `https://<sizning_domeningiz.uz>/admin`

- **Email:** `admin@tgmanager.uz`
- **Parol:** `admin1234`

*Tizimga kirgach, xavfsizlik uchun parolni o'zgartirishingiz tavsiya etiladi.*

---

## 📝 Ishlatish tartibi

1. Botga kirib `/start` buyrug'ini bosing.
2. Bot ko'rsatgan yo'riqnoma asosida botni kanalingizga **admin** qilib tayinlang.
3. `/mychannels` orqali kanalingizni ulab, standart sozlamalarni o'rnating.
4. Bot chatiga qisqa matn yuboring (masalan: `Spark 2020 sotiladi, oq rang, narxi 8500$`). AI sizga chiroyli formatlangan post tayyorlab, tasdiqlash uchun inline tugmalar taqdim etadi.
5. Postni to'liq tahrirlash yoki kalendarni ko'rish uchun **"Batafsil tahrirlash (Web)"** tugmasi orqali Mini App paneliga kiring.
