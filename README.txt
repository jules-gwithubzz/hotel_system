Hotel Management System
========================

Description
-----------
This is a web-based hotel management system for Cool Waves Hotel, built using PHP and MySQL. It provides role-based access for administrators and staff to manage hotel operations including rooms, guests, reservations, and users.

Features
--------
- User authentication with role-based access (Admin and Staff)
- Admin Dashboard: View statistics on guests, rooms, and reservations
- Room Management: Add, edit, and delete hotel rooms
- Guest Management: Manage guest information
- Reservation Management: Create and view reservations
- User Management: Manage system users (Admin only)
- Search and filter reservations by status (active, upcoming, past)

Requirements
------------
- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache recommended)
- XAMPP or similar local development environment

Installation and Setup
-----------------------
1. Install XAMPP (or similar) on your system.
2. Start the Apache and MySQL services.
3. Create a new MySQL database named "hotel_db".
4. Create the following tables in the database:

   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(50) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       role ENUM('admin', 'staff') NOT NULL
   );

   CREATE TABLE guests (
       guest_id INT AUTO_INCREMENT PRIMARY KEY,
       full_name VARCHAR(100) NOT NULL,
       email VARCHAR(100),
       phone VARCHAR(20)
   );

   CREATE TABLE rooms (
       room_id INT AUTO_INCREMENT PRIMARY KEY,
       room_number VARCHAR(10) UNIQUE NOT NULL,
       room_type VARCHAR(50) NOT NULL,
       price DECIMAL(10,2) NOT NULL,
       status ENUM('available', 'occupied') DEFAULT 'available'
   );

   CREATE TABLE reservations (
       reservation_id INT AUTO_INCREMENT PRIMARY KEY,
       guest_id INT NOT NULL,
       room_id INT NOT NULL,
       check_in DATE NOT NULL,
       check_out DATE NOT NULL,
       status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
       FOREIGN KEY (guest_id) REFERENCES guests(guest_id),
       FOREIGN KEY (room_id) REFERENCES rooms(room_id)
   );

5. Place the project files in your web server's document root (e.g., xampp/htdocs/hotel_system).
6. Update the database connection settings in connect_db.php if necessary (default: localhost, root, no password).
7. Create default users by inserting into the users table. Use the hash.php file to generate password hashes.

   Example: To create an admin user with password "pass123":
   - Run hash.php in your browser to get the hash.
   - Insert: INSERT INTO users (username, password, role) VALUES ('admin', 'generated_hash', 'admin');

Usage
-----
1. Access the system at http://localhost/hotel_system
2. Log in with your credentials.
3. Admins will be redirected to the admin dashboard, staff to the staff dashboard.
4. Use the navigation menu to access different features.

File Structure
--------------
- auth.php: Authentication and session management
- connect_db.php: Database connection
- functions.php: Utility functions
- hash.php: Password hashing utility
- home.php: Role-based redirect
- login.php: Login page
- logout.php: Logout functionality
- navbar.php: Navigation bar
- style.css: CSS styles
- admin/: Admin-specific pages
  - admin_dashboard.php: Admin dashboard
  - add_guest.php: Add guest
  - add_reservation.php: Add reservation
  - manage_rooms.php: Room management
  - manage_users.php: User management
  - view_reservations.php: View reservations
- staff/: Staff-specific pages
  - staff_dashboard.php: Staff dashboard
- images/: Image assets

Security Notes
--------------
- Passwords are hashed using PHP's password_hash() function.
- Prepared statements are used to prevent SQL injection.
- Session management includes regeneration on login.
- Role-based access control is implemented.

Support
-------
For issues or questions, please check the code comments or contact the developer.

License
-------
This project is open-source. Feel free to modify and distribute.