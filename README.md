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
| Server | Apache (XAMPP) |

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) installed on your machine

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/beldronallan02-glitch/student-representative-council-website.git
   ```

2. Move the project folder to your XAMPP htdocs directory:
   ```
   C:/xampp/htdocs/MPPCONNECT
   ```

3. Start **Apache** and **MySQL** in the XAMPP Control Panel

4. Import the database via **phpMyAdmin** (`http://localhost/phpmyadmin`)

5. Open your browser and navigate to:
   ```
   http://localhost/MPPCONNECT
   ```

---

## 📁 Project Structure

```
MPPCONNECT/
├── assets/                  # Images and media files
├── css/                     # Stylesheets
├── modules/                 # Feature modules (announcements, events, complaints, etc.)
├── uploads/                 # User uploaded files
├── config.php               # Database configuration
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