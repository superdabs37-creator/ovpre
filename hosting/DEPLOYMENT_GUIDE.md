# I-Record (OVPRE) - Deployment & Architecture Guide

This project is organized into a clean, modern modular structure that supports dual hosting environments:

```
ovpre/
├── frontend/               # Client-side Static Web Application (Vercel)
│   ├── assets/             # CSS, JavaScript, Logos & Images
│   ├── index.html          # Frontend Landing Page
│   ├── login.html          # Client-side Authentication
│   ├── register.html       # Client Registration Flow
│   ├── dashboard.html      # Main Client Dashboard
│   └── *.html              # All other static application pages (27 pages)
│
├── backend/                # Fullstack Dynamic PHP Application (XAMPP / Apache / Server)
│   ├── assets/             # Shared styling & assets
│   ├── includes/           # Core bootstrap, auth, RBAC, and Supabase helpers
│   ├── vendor/             # PHPMailer & external libraries
│   ├── config.php          # Core configuration and environment loader
│   ├── config.local.php    # Local machine credentials (ignored by git)
│   ├── index.php           # Secure Login Controller
│   ├── register.php        # Account Registration Controller
│   ├── dashboard.php       # Role-Based Administrative Dashboard
│   ├── roles/ subfolders   # Coordinator, Director, and Researcher sub-portals
│   └── *.php               # All dynamic PHP controllers & actions (35 files)
│
├── hosting/                # Server & Cloud Hosting Configurations
│   ├── vercel/             # Vercel deployment routes and documentation
│   ├── apache/             # XAMPP Apache .htaccess and VirtualHost guides
│   └── DEPLOYMENT_GUIDE.md # This guide
│
├── database/               # Relational Database Schemas & Migrations
│   ├── schema.sql          # Primary MySQL / MariaDB Table Schemas
│   ├── seed.sql            # Initial Seed Data
│   └── supabase_data.sql   # Supabase cloud database synchronization
│
├── uploads/                # Document Uploads & Academic Record Storage
├── storage/                # System File Storage
│
├── vercel.json             # Root Vercel router (directs traffic to frontend/)
├── .htaccess               # Root Apache configuration (directs traffic to backend/)
└── index.php               # Root entry router for XAMPP
```

---

## 1. Hosting on Vercel (`ovpre.vercel.app`)
The frontend is hosted as a static web application on Vercel.

* **Live URL**: `https://ovpre.vercel.app`
* **Root Configuration**: `vercel.json` routes all browser requests (e.g. `/`, `/dashboard`, `/login`, `/assets/*`) directly to the corresponding files in `frontend/`.
* **Zero Downtime**: When pushing to GitHub, Vercel will rebuild automatically without any 404 errors.

---

## 2. Hosting Locally on XAMPP (`http://localhost/ovpre`)
The backend is a PHP/MySQL system running inside Apache.

* **Root Access**: `http://localhost/ovpre` automatically invokes `backend/landing.php` or `backend/index.php`.
* **Direct Access**: `http://localhost/ovpre/backend/`
* **Shared Assets**: Both `frontend/assets` and `backend/assets` are synchronized.
