# Room Booking System

A PHP-based room booking system that allows users to select and temporarily block a room for a specified time slot while they complete the booking process.

## Features

- User registration and authentication
- Room browsing and booking
- Room blocking for a limited duration (5 minutes)
- Automatic release of expired blocks
- Waitlist functionality for unavailable rooms
- Booking confirmation process
- My Bookings page to view and manage bookings

## Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache/Nginx)

## Installation

1. Clone the repository to your web server's document root
2. Import the database schema into a MySQL database named `room_booking_system`
3. Update database connection details in `config/database.php` if needed
4. Access the application through your web browser

## Usage

1. Register a new account or login with existing credentials
2. Browse available rooms and select one to book
3. Choose a date and time slot for your booking
4. Complete the booking process within 5 minutes
5. View and manage your bookings in the "My Bookings" section

## Screenshots

Screenshots are available in the `screenshots` folder.
