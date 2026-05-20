# JIADA Portal - Jharkhand Industrial Area Development Authority

## 🏭 Complete Digital Single-Window Portal

A comprehensive enterprise web application for managing industrial land allotment, plug & play infrastructure, utility services, grievance redressal, lease management, HRMS, and all JIADA operations.

## 📋 Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Configuration](#configuration)
- [API Documentation](#api-documentation)
- [Modules](#modules)
- [Database Schema](#database-schema)
- [Security](#security)
- [License](#license)

## ✨ Features

### 1. Land Allotment System
- GIS-based plot search and visualization
- Online application submission
- Automated bidding process
- Provisional & final allotment orders
- Premium payment integration

### 2. Utility Management System
- Water, electricity, sewage tickets
- Priority-based SLA management
- Auto-assignment to authorities/vendors
- Escalation to RD/MD for unresolved issues

### 3. Plug & Play Infrastructure
- Flatted factory unit booking
- Six-month rent payment
- Auto-generated allotment letters

### 4. Grievance Redressal System
- OTP-protected grievance filing
- Assignment to Secondment Officers
- 5-day SLA with escalation matrix
- Re-submission cycles (max 3)

### 5. Court Case Management
- CNR number tracking
- Hearing date alerts
- Document management
- Status monitoring

### 6. Lease Management
- Surrender of Allotted Plots (with refund calculation)
- Mortgage of Lease Hold Rights
- Merge/Demerge/Amalgamate operations
- Transfer of Lease Hold Rights
- Change of Constitution
- Change in Shareholding (CIS)
- Cancellation of Allotted Plots
- Lease Renewal

### 7. Revenue Collection System
- Razorpay payment gateway integration
- Automated invoicing & receipts
- GST computation
- Multi-channel payment tracking

### 8. HRMS & Payroll
- Employee lifecycle management
- Attendance & leave tracking
- Salary structure with PF, ESI, PT, TDS
- Automated payroll processing
- Employee Self-Service portal

### 9. Land Survey Module
- GIS-integrated field data collection
- Leaflet-based map visualization
- Mobile app sync (React Native)

### 10. Notification System
- Email, SMS, WhatsApp, In-App notifications
- Redis queue processing
- Automated payment reminders

### 11. WhatsApp Bot
- Hindi & English support
- Service status tracking
- Direct access to all modules

## 🛠 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Core PHP (PDO) |
| **Frontend** | HTML5 + jQuery + CSS3 |
| **GIS** | Leaflet + GeoJSON |
| **Database** | MySQL 8.0+ |
| **Queue** | Redis |
| **Authentication** | JWT + OTP |
| **Server** | IIS / XAMPP (Apache) |
| **Mobile** | React Native (React WebView) |
| **Payment** | Razorpay |

## 📁 Project Structure

```
JIADA/
├── api/                    # API Endpoints
│   ├── auth/              # Authentication (login, register, OTP)
│   ├── land/              # Land allotment & plots
│   ├── lease/             # Lease management (surrender, mortgage, transfer)
│   ├── payment/           # Payment gateway integration
│   ├── grievances/        # Grievance management
│   ├── utilities/         # Utility ticket system
│   ├── court-cases/       # Court case tracking
│   ├── hrms/              # HRMS operations
│   ├── survey/            # Land survey data
│   └── whatsapp/          # WhatsApp bot integration
├── config/                 # Configuration files
│   ├── config.php         # Main configuration
│   └── database.php       # Database connection
├── core/                   # Core classes
│   ├── JWT.php            # JWT authentication
│   ├── OTP.php            # OTP service
│   ├── Notification.php   # Multi-channel notifications
│   ├── Middleware.php     # Request authentication
│   └── helpers.php        # Helper functions
├── database/               # Database files
│   └── schema.sql         # Complete database schema (50+ tables)
├── pages/                  # Front-end pages
│   ├── home.php           # Home page with GIS map
│   ├── dashboard.php      # User dashboard
│   └── auth/              # Login, Register pages
├── templates/             # HTML templates
│   └── layout.php         # Master layout
├── uploads/               # File uploads
├── logs/                  # Application logs
├── index.php              # Main entry point & router
└── README.md
```

## 🚀 Installation

### Prerequisites
- PHP 7.4+ or 8.x
- MySQL 8.0+
- Apache (XAMPP) or IIS
- Redis (optional, for queues)
- Composer (optional)

### Steps

1. **Clone/Download** the project to your XAMPP htdocs directory:
   ```
   c:\xampp\htdocs\JIADA
   ```

2. **Import Database:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

3. **Configure Database:**
   Edit `config/database.php` and set your MySQL credentials:
   ```php
   private $host = 'localhost';
   private $username = 'root';
   private $password = '';
   private $database = 'jiada_portal';
   ```

4. **Configure Application:**
   Edit `config/config.php` for:
   - Payment gateway credentials (Razorpay)
   - Email SMTP settings
   - SMS gateway API
   - WhatsApp API details

5. **Set Permissions:**
   ```bash
   chmod 755 uploads/ logs/
   ```

6. **Start Server:**
   ```
   http://localhost/JIADA
   ```

## ⚙️ Configuration

### Environment Variables (config/config.php)

| Setting | Description | Default |
|---------|-------------|---------|
| `JWT_SECRET` | JWT signing key | Auto-generated |
| `JWT_EXPIRE` | Token expiry (seconds) | 3600 |
| `SMTP_HOST` | Email server | smtp.gmail.com |
| `PAYMENT_API_KEY` | Razorpay key ID | - |
| `PAYMENT_API_SECRET` | Razorpay key secret | - |
| `REDIS_HOST` | Redis server | 127.0.0.1 |

## 📡 API Documentation

### Authentication

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/auth/login` | POST | Login with mobile/password or OTP |
| `/api/auth/register` | POST | New user registration |
| `/api/auth/send-otp` | POST | Send OTP to mobile/email |
| `/api/auth/verify-otp` | POST | Verify OTP |
| `/api/auth/logout` | POST | Logout & invalidate token |

### Land Allotment

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/land/plots` | GET | List available plots (with GIS) |
| `/api/land/apply` | POST | Submit land application |
| `/api/land/bids` | GET/POST | Manage bids |

### Payments

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/payment/initiate` | POST | Initiate payment |
| `/api/payment/callback` | POST | Payment gateway callback |
| `/api/payment/dues` | GET | View outstanding dues |

### Grievances

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/grievances/submit` | POST | File new grievance |
| `/api/grievances/list` | GET | List grievances |
| `/api/grievances/respond` | POST | Respond to grievance |

### Utility Tickets

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/utilities/tickets` | GET/POST | List/create tickets |

### Lease Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/lease/surrender` | POST | Submit surrender request |
| `/api/lease/mortgage` | POST | Request mortgage certificate |
| `/api/lease/transfer` | POST | Request lease transfer |
| `/api/lease/modify` | POST | Merge/demerge/amalgamate |

## 🗄 Database Schema

50+ tables covering:
- User management & authentication
- Land bank with GIS coordinates
- Application & bidding system
- Lease records and modifications
- Utility tickets & grievances
- Court cases
- HRMS (employees, attendance, payroll)
- Payment & revenue tracking
- Notifications & audit logs
- Workflow engine
- WhatsApp interactions

## 🔒 Security

- JWT-based API authentication
- OTP verification for login
- CSRF token protection
- Password hashing (bcrypt)
- SQL injection prevention (PDO prepared statements)
- XSS protection (htmlspecialchars)
- Rate limiting via Redis
- Token blacklisting for logout
- Audit logging for all actions

## 📱 Mobile App Structure (React Native)

```
mobile/
├── src/
│   ├── screens/
│   │   ├── LoginScreen.js
│   │   ├── DashboardScreen.js
│   │   ├── LandBankScreen.js
│   │   ├── ApplicationsScreen.js
│   │   └── PaymentsScreen.js
│   ├── navigation/
│   ├── services/
│   └── components/
└── App.js
```

## 📄 License

Proprietary - Jharkhand Industrial Area Development Authority  
Government of Jharkhand

## 📞 Support

- **Email:** info@jiada.in
- **Phone:** +91 651-223-4567
- **Address:** Udyog Bhawan, Dhurwa, Ranchi - 834004

---

**Built for JIADA - Jharkhand Industrial Area Development Authority**  
**Single Window Portal for Industrial Development**