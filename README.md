# 🏗️ DPWH 2nd DEO — Project Monitoring System

**An end-to-end web-based infrastructure tracking and management platform built for the Department of Public Works and Highways (2nd District Engineering Office).**

![PHP](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/UI-Bootstrap%204.5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![FPDF](https://img.shields.io/badge/Reports-FPDF-E63946?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=for-the-badge)

<div align="center">

<!-- YouTube Clickable Video Showcase (GitHub automatically renders this with a play preview) -->
<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" target="_blank">
  <img src="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg" width="85%" style="border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.2);" alt="DPWH 2nd DEO Project Monitoring System Video Walkthrough">
</a>

<p>
  <b>▶ <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">"DPWH 2nd DEO Project Monitoring System — Video Walkthrough"</a> · Click to Watch on YouTube</b>
</p>

</div>

<table>
  <tr>
    <td width="33%"><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"><img src="https://i.guim.co.uk/img/media/9d9b0fcdd73aae889672edb06e34828460c2001a/0_764_4480_5576/master/4480.jpg?width=1020&dpr=2&s=none&crop=none" width="100%" alt="District Overview"></a></td>
    <td width="33%"><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"><img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT9EQP56cyXu0XO-4bCbPBXfg0uLDx3cEs7f8gkn--Uiw&s" width="100%" alt="Infrastructure Inspection"></a></td>
    <td width="33%"><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"><img src="https://i.ytimg.com/vi/jzmz6K8K4L0/hqdefault.jpg" width="100%" alt="Project Tracking"></a></td>
  </tr>
  <tr>
    <td align="center"><sub>District Infrastructure Scope</sub></td>
    <td align="center"><sub>Field Inspection & Monitoring</sub></td>
    <td align="center"><sub>Project Status & Slippage Tracking</sub></td>
  </tr>
</table>

<p align="center"><sub><em>▶ Click any preview card above to launch the walkthrough</em></sub></p>

---

## 📌 What it is

The **DPWH 2nd DEO Project Monitoring System (PMS)** replaces cumbersome spreadsheets and paper records with a centralized, real-time command center. It empowers district administrators, project engineers, and evaluators to track public infrastructure projects throughout their entire lifecycle — ensuring contract transparency, financial integrity, and zero unaccounted slippage.

---

## ⚙️ How it works

Every public work follows a structured lifecycle governed by official DPWH protocols, tracked in real-time from bidding to final turn-over:

```text
Project Proposal & Bidding
  │
  ├─ 1. Initiation       → Register Project ID, Scope, Contractor, Budget, & Timeline
  ├─ 2. Monitoring       → Log Physical % vs. Financial % Progress & compute Slippage (+ / -)
  ├─ 3. Variations       → Issue Contract Work Suspensions, Resumptions & Time Extensions
  ├─ 4. Collaboration    → Assign Project Engineers; gate modifications behind Edit Approval requests
  ├─ 5. Audit & Media    → Attach site progress inspection photos & compliance certificates
  └─ 6. Executive Export → Auto-generate official DPWH-format PDF Status Reports via FPDF
```

---

## 🚀 Key Features

| Feature | Description |
|---|---|
| **📊 Real-Time Progress Engine** | Tracks planned vs. actual accomplishment, billing releases, and automated positive/negative slippage computation. |
| **⏸️ Contract Suspension Controls** | Formally record Contract Work Suspensions, Work Resumptions, and Time Extensions with documented justifications. |
| **👥 Multi-Engineer Collaboration** | Assign multiple project collaborators to a single contract with granular edit request permissions. |
| **📝 Edit Request Approval Gates** | Prevents unauthorized data tampering — modifications to sensitive project details require admin approval. |
| **👷 Manpower Tracking** | Monitor on-site labor metrics for both Contractor Manpower and Implementing Office personnel. |
| **📑 Executive PDF Reports** | One-click export of official DPWH project status reports and audit sheets powered by FPDF. |
| **🔒 Enterprise Security** | Session management, password hashing, SQL injection defenses, and login attempt rate limiting. |

---

## 👥 Role-Based Access Control (RBAC)

The system enforces three distinct user roles to maintain accountability across the district:

| Capability | 👑 District Admin (`role_id: 1`) | 👷 Project Engineer (`role_id: 2`) | 👁️ Guest / Auditor (`role_id: 3`) |
|:---|:---:|:---:|:---:|
| **Dashboard Analytics** | District-wide (All Projects) | Assigned Projects Only | Summary View Only |
| **Create New Project** | ✅ Yes | ❌ No | ❌ No |
| **Log Accomplishment %** | ✅ Full Access | ✅ Assigned Contracts | ❌ Read Only |
| **Issue Time Suspension / Extension** | ✅ Approve & Log | 🟡 Request Only | ❌ Read Only |
| **Approve / Reject Edit Requests** | ✅ Yes | ❌ No | ❌ No |
| **Export Official PDF Reports** | ✅ All Projects | ✅ Assigned Contracts | ✅ View & Export |
| **User & Role Management** | ✅ Full Control | ❌ No | ❌ No |

---

## 🗄️ Database Architecture

The system is backed by a relational schema (`dpwhpms.sql`) designed around contract accountability:

```text
projects ──────┬──────< project-collaborators >────── users (roles)
               ├──────< progress >
               ├──────< contract-work-suspension >
               ├──────< contract-work-resumption >
               ├──────< contract-time-extension >
               ├──────< variation-orders >
               ├──────< contract-manpower >
               ├──────< implementing-office-manpower >
               └──────< other-documents / site photos >
```

---

## 📂 What's in the Box

```text
├── css/                     # Custom stylesheet themes & Bootstrap overrides
├── js/                      # Dynamic chart rendering, AJAX progress calculators & modal triggers
├── fpdf/                    # FPDF engine for server-side official report generation
├── images/                  # DPWH emblems, system badges, and branding assets
├── includes/                # Reusable database connections, auth guards, navbars & sidebars
│   ├── database.php         # Central MySQLi database connection handler
│   ├── navbar.php           # Dynamic top-bar navigation
│   └── sidebar.php          # Collapsible role-based navigation menu
├── pages/                   # User-facing portal pages
│   ├── dashboard.php        # Executive KPIs, project stats & quick actions
│   ├── projects.php         # Master project grid & data tables
│   ├── reports.php          # District performance report generator
│   └── edit-requests.php    # Admin review queue for project modification requests
├── project-management/      # Core CRUD logic, slippage calculator & FPDF report exporters
│   ├── add-project.php      # New infrastructure project entry form
│   ├── calculate-progress.php # Automated slippage & progress logic
│   ├── generate-report-pdf.php # Official DPWH PDF format report builder
│   └── project-timeline-adjustment.php # Suspension and resumption handlers
├── dpwhpms.sql              # Clean database dump with pre-configured schema & roles
└── default.php / index.php  # Portal landing page and secure login authentication
```

---

## 🛠️ Quick Start & Local Installation

### 1. Prerequisites
* **PHP 7.4+** or **PHP 8.x**
* **MySQL 5.7+** or **MariaDB 10.4+**
* Local web server (**XAMPP**, **WampServer**, or **Laragon**)

### 2. Setup Database
1. Launch **phpMyAdmin** (e.g. `http://localhost/phpmyadmin`).
2. Create a new database named:
   ```sql
   CREATE DATABASE dpwhpms;
   ```
3. Import the [`dpwhpms.sql`](dpwhpms.sql) file located in the root of this repository.

### 3. Clone Repository
Clone or place the project into your local server web directory (e.g., `C:/xampp/htdocs` for XAMPP):
```bash
cd C:/xampp/htdocs
git clone https://github.com/SrShachou01/DPWH-2nd-DEO-Project-Monitoring-System.git dpwhpms
```

### 4. Configure Database Connection
Verify or update your database credentials in `includes/database.php`:
```php
function ConnectDB() {
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "dpwhpms";
    
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
```

### 5. Launch the Application
Open your browser and navigate to:
```text
http://localhost/dpwhpms/
```

---

## 🏛️ Core Principles & Mandate

> **Mission:** To provide and manage quality infrastructure facilities and services responsive to the needs of the Filipino people in the pursuit of national development objectives.  
> **Vision:** By 2040, DPWH is an excellent government agency, enabling a comfortable life for Filipinos through safe, reliable, and resilient infrastructure.

---

## 📄 License & Attribution

Distributed under internal government and educational fair-use guidelines. Built for the **DPWH 2nd District Engineering Office**.
