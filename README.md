# Pamantasan ng Lungsod ng San Pablo (PLSP)
## Office of the Vice President for Research and Extension (OVPRE) Management Information System

A centralized web-based Research and Extension Management Information System designed to facilitate, streamline, and monitor institutional research proposals, extension projects, accomplishment reports, deliverables, and academic documentations.

---

## 🏛️ System Architecture & Role Portals

The application implements Role-Based Access Control (RBAC) with dedicated workspaces for institutional administrators, college coordinators, and faculty proponents.

```
                    ┌────────────────────────┐
                    │      OVPRE System      │
                    └───────────┬────────────┘
                                │
        ┌───────────────────────┼────────────────────────┐
        ▼                       ▼                        ▼
┌──────────────┐     ┌─────────────────────┐     ┌────────────────┐
│ Institutional│     │ College Coordination│     │ Proponents     │
│ Administration│    │ Portals             │     │ Portals        │
├──────────────┤     ├─────────────────────┤     ├────────────────┤
│ • Super Admin│     │ • Research Coord.   │     │ • Researchers  │
│ • OVPRE Admin│     │ • Extension Coord.  │     │ • Extensionists│
│ • Directors  │     └─────────────────────┘     └────────────────┘
└──────────────┘
```

### Role Access Matrix
| Role | Portal / Directory | Primary Capabilities |
| :--- | :--- | :--- |
| **Super Admin** | `dashboard.php` | Full system administration, role assignment, audit trails, database backup & recovery. |
| **OVPRE Admin** | `dashboard.php` | Document routing, proposal approvals, institutional reports, announcements. |
| **Research / Extension Director** | `dashboard.php` | Institutional oversight, final endorsements, review logs, university-wide metrics. |
| **Research Coordinator** | `research-coordinator/` | College-level research proposal screening, college project monitoring, endorsements. |
| **Extension Coordinator** | `extension-coordinator/` | College-level extension project screening, community impact tracking. |
| **Researcher** | `researcher/` | Proposal creation, milestone updates, liquidation & research paper uploads. |
| **Faculty Extensionist** | `faculty-extensionist/` | Extension activity proposals, beneficiary reports, terminal accomplishments. |

---

## 📂 Project Directory Structure

```plaintext
ovpre/
├── assets/                     # Frontend styles, scripts, and media
│   ├── css/                    # Main stylesheet (style.css) & landing theme
│   ├── img/                    # Official institutional logos and seals
│   └── js/                     # Client-side JavaScript (app.js)
├── database/                   # Database migrations and schema definitions
│   ├── audits/                 # Security and audit trail SQL scripts
│   ├── migrations/             # Timestamped schema & feature migrations
│   └── schema.sql              # Clean baseline relational schema
├── extension-coordinator/      # Extension Coordinator role portal
├── faculty-extensionist/       # Faculty Extensionist role portal
├── includes/                   # Core application infrastructure
│   ├── bootstrap.php           # DB connection (Supabase/PostgreSQL), Auth, Mailer
│   ├── layout.php              # Global header, navigation, and footer templates
│   ├── admin-shell.php         # Admin components and shell layout
│   └── portal-data.php         # Data aggregation for role dashboards
├── research-coordinator/       # Research Coordinator role portal
├── researcher/                 # Faculty Researcher role portal
├── role-portal/                # Shared layout & reusable portal pages
├── storage/                    # Internal system caches & storage rules
├── uploads/                    # Upload directories
│   ├── proposals/              # Research and extension submission attachments
│   ├── records/                # Seeded and archived PDF records
│   └── reports/                # Progress and accomplishment reports
├── vendor/                     # Third-party dependencies (PHPMailer)
├── activities.php              # Institutional activities calendar & monitoring
├── admin-documents.php         # Document management & status tracking
├── admin-pending-users.php     # Account registration verification queue
├── admin-reports.php           # Aggregated reports and export generation
├── admin-settings.php          # Security controls & backup navigation
├── admin-users.php             # User management & role assignment
├── announcements.php           # Targeted broadcast announcements
├── dashboard.php               # Institutional Admin Dashboard
├── extension.php               # Extension projects registry
├── index.php                   # Authentication login portal
├── landing.php                 # Public institutional portal homepage
├── notifications.php           # Real-time notification center
├── profile.php                 # User profile settings & password changes
├── register.php                # Multi-step role registration
├── research.php                # Research proposals & publications registry
├── search.php                  # Global search engine across modules
├── seed-demo-data.php          # Demo dataset & mock document seeder
├── submitted-files.php         # Repository of files shared across departments
├── system-activity.php         # Comprehensive audit logging & access trail
└── system-backup.php           # Database snapshot export & recovery engine
```

---

## 🚀 Getting Started

### Prerequisites
- **Web Server**: Apache (e.g., via XAMPP)
- **PHP**: Version 8.2 or higher (with `pdo`, `pdo_pgsql`, `pdo_mysql`, `curl`, and `mbstring` extensions enabled)
- **Database**: PostgreSQL (Supabase Cloud) or local MySQL instance

### Installation & Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/superdabs37-creator/ovpre.git
   ```

2. **Configure Local Environment**
   Copy the sample configuration file:
   ```bash
   cp config.local.php.example config.local.php
   ```
   Update `config.local.php` with your database and Supabase keys:
   ```php
   <?php
   define('DB_DRIVER', 'pgsql');
   define('DB_HOST', 'aws-0-ap-southeast-1.pooler.supabase.com');
   define('DB_PORT', '5432');
   define('DB_NAME', 'postgres');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');

   define('SUPABASE_URL', 'https://your-project.supabase.co');
   define('SUPABASE_ANON_KEY', 'your_anon_key');
   define('SUPABASE_SECRET_KEY', 'your_secret_key');
   ```

3. **Database Initialization**
   - Apply `database/schema.sql` to your PostgreSQL database.
   - Run necessary migrations from `database/migrations/`.
   - Optionally seed demonstration records:
     ```bash
     php seed-demo-data.php
     ```

4. **Launch Application**
   Open your browser and navigate to:
   ```
   http://localhost/ovpre/
   ```

---

## 🔒 Security Features
- **Session Isolation & CSRF Protection**: Every mutating form request is validated with anti-CSRF tokens.
- **Role Isolation**: Automatic route guarding prevents unauthorized access to privileged portals.
- **Audit Trails**: Security actions (logins, role alterations, data modifications) are recorded with timestamps and client IP addresses in `system-activity.php`.
- **Encrypted Credentials**: Password storage powered by Bcrypt hashes.
