# 🌾 Agrogranja — Laravel 10

Sistema de gestión para fincas: cultivos, animales, gastos, ingresos y tareas.  
Diseño responsive con **modo PC** (sidebar) y **modo móvil** (bottom nav), botón toggle 🖥️/📱.

---

## ⚙️ Instalación paso a paso

### Requisitos
- PHP 8.1+  
- Composer  
- MySQL (puerto 3306, usuario `root`, sin contraseña)

### 1. Instala las dependencias
```bash
cd agrogranja-laravel
composer install
```

### 2. Configura el entorno
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Crea la base de datos
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS agrogranja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
O desde **phpMyAdmin**: crear base de datos llamada `agrogranja`.

### 4. Ejecuta migraciones y datos demo
```bash
php artisan migrate --seed
```

### 5. Inicia el servidor
```bash
php artisan serve
```
Abre → **http://localhost:8000**

---

## 🔑 Usuario demo

| Campo | Valor |
|-------|-------|
| Email | `demo@demo.com` |
| Contraseña | `demo123` |

---

## 🖥️📱 Modos de vista

El botón **🖥️ / 📱** en la esquina superior derecha alterna entre:

| Modo | Layout |
|------|--------|
| 📱 Móvil | App centrada 430px + bottom nav |
| 🖥️ PC | Sidebar verde + full width |

En pantallas ≥ 900px se activa modo PC automáticamente.  
La preferencia se guarda en `localStorage`.

---

## 📁 Estructura

```
agrogranja-laravel/
├── artisan                        ← CLI de Laravel
├── composer.json
├── .env.example
├── app/
│   ├── Console/Kernel.php
│   ├── Exceptions/Handler.php
│   ├── Http/
│   │   ├── Kernel.php
│   │   ├── Middleware/AuthSession.php
│   │   └── Controllers/          ← Auth, Dashboard, Cultivo, Gasto,
│   │                               Ingreso, Animal, Tarea, Reporte, Perfil
│   ├── Models/User.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── RouteServiceProvider.php
├── bootstrap/app.php
├── config/                        ← app, database, session, cache, etc.
├── database/
│   ├── migrations/
│   └── seeders/DatabaseSeeder.php
├── public/
│   ├── index.php
│   ├── css/app.css
│   └── js/app.js
├── resources/views/
│   ├── layouts/app.blade.php      ← Layout maestro (sidebar + bottom nav)
│   ├── auth/                      ← welcome, login, register, onboarding
│   └── pages/                     ← dashboard, cultivos, gastos, ingresos,
│                                    animales, calendario, reportes, perfil
├── routes/
│   ├── web.php
│   ├── api.php
│   └── console.php
└── storage/
```

---

## Si hay error de permisos en storage/
```bash
chmod -R 775 storage bootstrap/cache
```
