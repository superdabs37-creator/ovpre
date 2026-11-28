# Local Apache / XAMPP Hosting Configuration

This folder documents the Apache server configuration for running the **I-Record (OVPRE)** backend locally via XAMPP.

## Requirements
* Apache 2.4+ (included with XAMPP)
* PHP 8.1+ with PDO MySQL and OpenSSL extensions
* MySQL / MariaDB (Port 3306)

## Setup Instructions
1. Place this project inside `C:\xampp\htdocs\ovpre`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Access the application in your browser:
   * **Root Entry Point**: `http://localhost/ovpre` (auto-redirects to `backend/index.php`)
   * **Direct Backend**: `http://localhost/ovpre/backend/index.php`
4. Configure database settings in `backend/config.local.php`.
