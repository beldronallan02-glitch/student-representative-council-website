# MPPConnect: Centralized Notice & Feedback System for Majlis Perwakilan Pelajar UMS

A web-based platform developed as a Final Year Project for **Universiti Malaysia Sabah (UMS)** that centralizes communication between the Student Representative Council (Majlis Perwakilan Pelajar / MPP) and students.

## 📌 About the Project

At UMS, announcements and feedback collection were fragmented across multiple platforms, leading to missed updates, inefficient communication, and limited student engagement. **MPPConnect** solves this by bringing everything into one structured digital system.

Built following the **Waterfall methodology**, the system covers the full lifecycle of student-council communication — from announcements to complaints to event registrations.

**Author:** Beldron Feadrek (BI22110176)  
**Supervisor:** Assoc. Prof. Ts. Dr. Chin Kim On  
**Degree:** Bachelor of Computer Science with Honours (Software Engineering)  
**Institution:** Faculty of Computing and Informatics, Universiti Malaysia Sabah  
**Year:** 2026

---

## ✨ Features

- **Notice & Announcement Management** — MPP publishes official announcements and updates to all students in one place
- **Event Registration** — Students can browse and register for MPP events online
- **Feedback & Rating System** — Students submit ratings and feedback on MPP activities
- **Complaint System** — Structured complaint reporting and tracking
- **MPP Progress & Impact Log** — Records and displays MPP achievements and actions taken
- **Service & Facility Booking** — Students can book MPP-managed services and facilities
- **User Profile Management** — Students manage their own profiles
- **Dashboard & Analytics** — Visual overview of system activity and student engagement

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML, JavaScript, Tailwind CSS |
| Backend | PHP |
| Database | MySQL |
| Server | Apache (XAMPP, or Docker) |

---

## 🚀 Getting Started

The app runs from the web server's document root (it no longer assumes a `/MPPCONNECT` subfolder), so pick whichever setup below matches your environment.

### Option A: Docker (recommended)

**Prerequisites:** [Docker](https://www.docker.com/) and Docker Compose

1. Clone the repository:
   ```bash
   git clone https://github.com/beldronfeadrek/student-representative-council-website.git
   cd student-representative-council-website
   ```

2. Start the stack:
   ```bash
   docker compose up
   ```

3. Open your browser and navigate to:
   ```
   http://localhost:8080
   ```

This spins up PHP + Apache, a MySQL 8 database (auto-seeded from `docker/db/schema.sql`), and phpMyAdmin at `http://localhost:8081`. Database credentials are passed via environment variables in `docker-compose.yml` — see `config.php` for the fallback defaults.

> `docker/db/schema.sql` was reconstructed from the queries in the codebase (no SQL export existed), so treat it as a best-effort starting point rather than a verified source of truth.

### Option B: XAMPP

**Prerequisites:** [XAMPP](https://www.apachefriends.org/) installed on your machine

1. Clone the repository into your XAMPP `htdocs` directory:
   ```bash
   git clone https://github.com/beldronfeadrek/student-representative-council-website.git C:/xampp/htdocs/student-representative-council-website
   ```

2. Start **Apache** and **MySQL** in the XAMPP Control Panel

3. Import the database schema (`docker/db/schema.sql`) via **phpMyAdmin** (`http://localhost/phpmyadmin`)

4. Open your browser and navigate to:
   ```
   http://localhost/student-representative-council-website
   ```

---

## 📁 Project Structure

```
student-representative-council-website/
├── assets/                  # Images and media files
├── css/                     # Stylesheets
├── docker/                  # Dockerfile and DB schema for local dev
├── modules/                 # Feature modules (announcements, events, complaints, etc.)
├── uploads/                 # User uploaded files
├── config.php               # Database configuration (env-overridable)
├── docker-compose.yml       # Local dev stack (app + MySQL + phpMyAdmin)
├── index.php                # Home / landing page
├── login.php                # Login page
├── register_student.php     # Student registration
├── dashboard.php            # Main dashboard
└── logout.php               # Session logout
```

---

## 🧪 Testing

The system was evaluated using:
- **Unit Testing** — individual module validation
- **Integration Testing** — cross-module functionality
- **System Testing** — end-to-end flow testing
- **Usability Testing** — assessed using the **System Usability Scale (SUS)**

---

## 📄 License

This project was developed for academic purposes as part of a Final Year Project at Universiti Malaysia Sabah. All rights reserved.