# 🚗 CASMS - Car Auto Service Management System

A comprehensive web-based Car Auto Service Management System enabling customers to book vehicle services, manage vehicles, and track service history with real-time notifications.

## 📋 Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [Database Structure](#database-structure)
- [Installation](#installation)
- [Default Login](#default-login)
- [Project Structure](#project-structure)
- [Screenshots](#screenshots)
- [Future Enhancements](#future-enhancements)
- [License](#license)

## ✨ Features

### 👤 User Dashboard
- Interactive dashboard with service distribution charts and monthly booking analytics
- Real-time clock and personalized welcome messages
- Quick access to vehicles, bookings, and service catalog
- Statistics cards showing vehicle count, total bookings, completed, and pending services

### 🚘 Vehicle Management
- Add and manage multiple vehicles per user
- Store vehicle details: brand, model, year, license plate, color, fuel type, transmission
- License plate uniqueness validation to prevent duplicates
- Quick vehicle addition from dashboard

### 📅 Service Booking
- Browse available auto services with pricing and duration
- Book services for specific vehicles
- Track booking status (pending, in_progress, completed, scheduled)
- View complete booking history with service details

### 🔔 Notification System
- Real-time notifications for booking updates
- Mark notifications as read/unread
- Delete individual notifications or mark all as read
- Auto-refresh notifications every 30 seconds
- Visual indicators for unread notifications with badge counter

### 👤 Profile Management
- Update personal information (name, email, phone)
- Change password functionality
- View account statistics

### 🆘 Emergency SOS
- Quick emergency booking feature for urgent service needs

## 🛠 Technology Stack

### Backend
- **PHP** 7.4+ (Native)
- **MySQL**/MariaDB Database
- **PDO** for secure database connections

### Frontend
- **HTML5**, **CSS3**, **JavaScript** (Vanilla)
- **Chart.js** for data visualization
- **Font Awesome 6** for icons
- **Google Fonts** (Inter font family)

### Features
- AJAX for seamless notification updates
- Responsive design for mobile and desktop
- Session-based authentication
- Real-time charts and analytics

## 📊 Database Structure

| Table | Description |
|-------|-------------|
| `users` | User authentication and profile data |
| `vehicles` | Customer vehicle information |
| `services` | Available auto services catalog |
| `bookings` | Service booking records |
| `notifications` | User notification system |
| `emergency_bookings` | Urgent service requests |
