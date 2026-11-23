# FabLine - Textile Manufacturing & Production Tracking ERP

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-5.7%20%7C%208.0-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Frontend-Bootstrap%205%20%7C%20W3.CSS-563D7C?style=flat)](https://getbootstrap.com/)
[![Repository](https://img.shields.io/badge/GitHub-BzSiam25%2FFeb--Line-181717?style=flat&logo=github)](https://github.com/BzSiam25/Feb-Line)

FabLine is an enterprise-grade web application built to digitize, monitor, and streamline end-to-end operations in textile mills and garment manufacturing plants. It connects core production stages—from knitting programs and floor inspection to dyeing batching, rack transfer, store management, and delivery challans.

---

## 📑 Table of Contents

- [Overview](#-overview)
- [System Workflow](#-system-workflow)
- [Key Modules & Features](#-key-modules--features)
  - [1. Knitting Operations](#1-knitting-operations)
  - [2. Quality Control & Inspection](#2-quality-control--inspection)
  - [3. Store & Warehouse Logistics](#3-store--warehouse-logistics)
  - [4. Dyeing & Finishing Operations](#4-dyeing--finishing-operations)
  - [5. Reporting & Analytics](#5-reporting--analytics)
  - [6. Access Control & User Roles](#6-access-control--user-roles)
- [Technology Stack](#-technology-stack)
- [Prerequisites](#-prerequisites)
- [Installation & Setup](#-installation--setup)
- [Database Configuration](#-database-configuration)
- [Directory Structure](#-directory-structure)
- [Contributing](#-contributing)
- [Author & Credits](#-author--credits)

---

## 🌟 Overview

Textile manufacturing involves fast-paced floor operations where real-time tracking of rolls, batches, yarn consumption, and machine status is essential. FabLine replaces manual paper logs with automated cards, barcode/QR tracking, real-time status dashboards, and role-restricted interfaces.

### Core Objectives:
- Eliminate roll loss and inventory mismatch between floor stages.
- Provide end-to-end traceability for every roll from yarn knitting to dyeing and finishing.
- Prevent unauthorized actions on the plant floor using role-based access.
- Generate automated printable cards, reports, and exportable analytics (PDF & Excel).

---

## 🔄 System Workflow

```
[ Knitting Program Planning ]
             │
             ▼
[ Knit Card Generation & Roll Tagging ]
             │
             ▼
[ Knitting Floor Production & Machine Tracking ]
             │
             ▼
[ Roll Inspection & Quality Verification ]
             │
             ▼
[ Knitting Store & Rack Transfer ]
             │
             ▼
[ Dyeing Batch Card Creation & Batch Split ]
             │
             ▼
[ Dyeing Machine Loading & Unloading ]
             │
             ▼
[ Finishing Department & Final Delivery Challan ]
```

---

## 🚀 Key Modules & Features

### 1. Knitting Operations
- **Knitting Program Planning:** Create and schedule knitting jobs with detailed yarn specs, machine allocation, and color codes.
- **Knit Card Generation:** Generates identifiable tracking cards equipped with QR codes for fast scanning on the shop floor.
- **Roll Splitting:** Split large fabric rolls into required child rolls while maintaining parent traceability.
- **Subcontract Tracking:** Monitor inputs and outputs of subcontracted knitting lots.

### 2. Quality Control & Inspection
- **Roll Inspection:** Inspect knitted rolls against defect standards, log defect counts, point grades, and overall weight.
- **Verification Logs:** Prevent unverified fabric from moving downstream to the dyeing department.

### 3. Store & Warehouse Logistics
- **Knitting Store Management:** Receive verified rolls into grey store inventory.
- **Rack Transfer:** Manage location changes across warehouse racks with real-time transfer histories.
- **Batch Delivery:** Create delivery dispatches and print delivery challans for internal and external transfers.

### 4. Dyeing & Finishing Operations
- **Dyeing Batch Cards:** Formulate batch compositions by grouping compatible rolls.
- **Batch Verification & Split:** Verify roll weights before loading into dyeing vessels; split batches when required.
- **Machine Load / Unload:** Track machine vessel numbers, load times, cycle completions, and unload timestamps.
- **Redye Process:** Flag and manage redye batches that require re-processing.
- **Finishing Floor Production:** Manage final post-dyeing finishing processes (heat setting, compaction, stentering).

### 5. Reporting & Analytics
- **Live Floor Reports:** Real-time visibility into machine output, operator logs, and stage transitions.
- **Export Options:** Generate high-resolution printable PDF cards (via mPDF/FPDF) and spreadsheets (via PhpSpreadsheet).
- **Audit Trails:** Track modifications and user activities across shifts.

### 6. Access Control & User Roles
Role-based interface filtering ensures operators only access their respective departmental tasks:
- **Admin:** Full access to configuration, database administration, and user management.
- **Knitting Operator (`k01`):** Program management, knit cards, production, store, and rack transfer.
- **Dyeing Operator (`d01`):** Batch cards, verification, loading, unloading, and redyeing.
- **Finishing Operator (`f01`):** Finished goods tracking and department reports.
- **TV / Display Mode:** Read-only live display for production dashboards on plant monitors.

---

## 🛠 Technology Stack

- **Backend:** PHP 7.4 / 8.x
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (ES6+), jQuery, Bootstrap 5, W3.CSS
- **Libraries & Dependencies:**
  - `setasign/fpdf`: PDF generation for cards and chalan printing.
  - `mpdf/mpdf`: Advanced HTML-to-PDF rendering for reports.
  - `phpoffice/phpspreadsheet`: Excel and CSV data export/import.
  - `qrcode.js`: Dynamic client-side QR code generation.

---

## 📋 Prerequisites

Before setting up FabLine, ensure you have:
- A local web server environment: **XAMPP**, **WAMP**, **Laragon**, or standard **LAMP/LEMP** stack.
- **PHP** >= 7.4 with `mysqli`, `gd`, `mbstring`, and `zip` extensions enabled.
- **MySQL** >= 5.7 or **MariaDB** >= 10.3.
- **Composer** installed globally.

---

## 💻 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/BzSiam25/Feb-Line.git
   cd Feb-Line
   ```

2. **Install Composer dependencies:**
   ```bash
   composer install
   ```

3. **Configure Database Connection:**
   - Copy `config.example.php` to `config.php`:
     ```bash
     cp config.example.php config.php
     ```
   - Open `config.php` and set your MySQL database name, user, and password:
     ```php
     $hostname     = "localhost";
     $username     = "root";
     $password     = "";
     $databaseName = "fablinedb";
     ```

4. **Import Database:**
   - Create a database named `fablinedb` in phpMyAdmin or via MySQL CLI:
     ```sql
     CREATE DATABASE fablinedb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```

5. **Run the Application:**
   - If using XAMPP/WAMP, place the project inside `htdocs` or `www` folder.
   - Or start PHP's built-in development server:
     ```bash
     php -S localhost:8000
     ```
   - Open your browser and navigate to `http://localhost:8000/login.php`.

---

## 📁 Directory Structure

```plaintext
Feb-Line/
├── css/                             # Stylesheets (Bootstrap, W3.CSS, custom themes)
├── js/                              # Client-side scripts (QR generator, reports)
├── image/                           # Logos, brand assets, and icons
├── vendor/                          # Composer third-party packages (ignored in git)
├── ajaxDyeing_*.php                 # Dyeing asynchronous handlers
├── ajaxKnitting_*.php               # Knitting asynchronous handlers
├── config.example.php               # Database configuration template
├── config.php                       # Active environment database configuration
├── db_admin.php                     # Internal database viewer & manager
├── dyeing_batch_card.php            # Dyeing batch card creation
├── initialPage.php                  # Primary dashboard & navigation router
├── knit_card.php                    # Knit card generation & QR print
├── knitting_program.php             # Knitting scheduling and program setup
├── knitting_rack_transfer.php       # Warehouse rack management
├── login.php                        # Authentication entry point
├── report.php                       # Production and inventory reporting
├── user_management.php              # User registration and access control
├── composer.json                    # PHP package definitions
└── README.md                        # Documentation
```

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!
1. Fork the Project.
2. Create your Feature Branch (`git checkout -b feature/NewFeature`).
3. Commit your Changes (`git commit -m 'Add NewFeature'`).
4. Push to the Branch (`git push origin feature/NewFeature`).
5. Open a Pull Request.

---

## 👤 Author & Repository

- **Repository:** [https://github.com/BzSiam25/Feb-Line](https://github.com/BzSiam25/Feb-Line)
- **Maintainer:** [BzSiam25](https://github.com/BzSiam25)
