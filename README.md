# SAFARI TANZANIA — Tourism Booking System

PHP 8 + MySQL implementation of the SAFARI TANZANIA booking platform.
Designed to run on **XAMPP** (Apache + MySQL).

## What's included (v1 — Core booking flow)

- ✅ Phase 1 — PHP folder structure + reusable header/footer
- ✅ Phase 2 — Registration & login with `password_hash()` / `password_verify()`,
  `session_regenerate_id()`, role-based redirect
- ✅ Phase 3 — `checkRole()` middleware in `config/auth.php`
- ✅ Phase 6 — Booking with **overlap-detection availability check** (also respects `total_rooms`)
- ✅ Phase 7 — **Mock payment recorder** (insert into `payments`, flips booking to `paid`)
- ✅ Phase 8 — Traveler dashboard, my bookings, cancel flow
- ✅ Public homepage, listing, search, accommodation details
- ⏳ Phases 4, 5, 9, 10 — Owner CRUD pages and admin panel are **scaffolded** (dashboards land);
  full management UI to be added in the next iteration.

## Setup

1. Copy this folder into `xampp/htdocs/`. The path can be `htdocs/SAFARI TANZANIA/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open phpMyAdmin → **Import** → choose `sql/schema.sql` → Go.
   (Or run `mysql -u root < sql/schema.sql`.)
4. If your MySQL password isn't blank, update `config/db.php` (`DB_USER`, `DB_PASS`).
5. Visit `http://localhost/SAFARI%20TANZANIA/` in your browser.

## Creating accounts

The seed file inserts demo *user rows* but their password hashes are placeholders
that won't verify. The cleanest path is:

1. Go to `/auth/register.php` and create a new traveler account.
2. To promote that account:
   ```sql
   UPDATE users SET role='owner' WHERE email='you@example.com';
   -- or
   UPDATE users SET role='admin' WHERE email='you@example.com';
   ```

## Security highlights

- **PDO prepared statements** everywhere — no string concatenation in SQL
- **`password_hash()` / `password_verify()`** for credentials
- **Session hardening** — `httponly`, `samesite=Lax`, `session_regenerate_id(true)` on login
- **CSRF tokens** on every POST form
- **Output escaping** via `e()` (htmlspecialchars wrapper)
- **Ownership checks** on cancellations and payments
- **Overlap-safe booking** SQL respects `total_rooms` so multi-unit rooms work correctly

## Folder map

```
/SAFARI TANZANIA
├── index.php                  ← entry, redirects to public/
├── .htaccess
├── /config         db.php · auth.php
├── /includes       header.php · footer.php
├── /auth           register.php · login.php · logout.php
├── /public         index.php · accommodation_listing.php · accommodation_details.php
├── /traveler      dashboard.php · book_room.php · payment.php · my_bookings.php
├── /owner          dashboard.php (full CRUD next iteration)
├── /admin          dashboard.php (full panel next iteration)
├── /assets/css     style.css
└── /sql            schema.sql
```

## Next iteration

Owner property/room CRUD (`add_property.php`, `edit_property.php`, `manage_rooms.php`,
`manage_bookings.php`) and admin panel (`users.php`, `accommodations.php`, `bookings.php`,
`reports.php`) — all the structure and middleware are in place.


SAFARI TANZANIA Software Requirements Specification (SRS).
1. Introduction 
1.1 Purpose of the Document This Software Requirements Specification (SRS) describes the complete structure, functional requirements, and non-functional requirements of the SAFARI TANZANIA system. The purpose of this document is to provide a clear and detailed explanation of how the system should operate before the actual development begins. It acts as a formal agreement between stakeholders, developers, designers, testers, and supervisors. This document explains what the system will do, how users will interact with it, what technologies will be used, and what limitations must be considered. It translates the project idea and research findings into technical system requirements that can guide system design and coding. The SRS ensures that misunderstandings are avoided during development. It also helps in testing and validation because every implemented feature must match what is written in this document. Once approved, this document becomes the main reference for system development, implementation, and evaluation. 
1.2 Purpose of the System SAFARI TANZANIA is a web-based accommodation booking and location system designed to improve how travelers search, locate, and reserve hotels, lodges, and guest houses across Tanzania. Many small and medium accommodation providers do not have proper online visibility, making it difficult for travelers to find reliable information. The purpose of the system is to provide one centralized platform where users can search accommodations, compare prices, check availability, view room types, and see the exact location on a map. The system will reduce dependency on phone calls and physical visits. The platform also aims to support small accommodation businesses by giving them online exposure. By digitizing booking and location services, the system improves convenience, saves time, increases transparency, and enhances the overall tourism experience in Tanzania. 
1.3 Scope The SAFARI TANZANIA system will focus on accommodation booking and location services within Tanzania. The system will allow travelers to search accommodations by region, price range, or room type. Users will be able to view detailed accommodation profiles including images, services offered, contact details, and map location. The system will allow users to: Create and manage accounts Search and filter accommodations View room details and availability Make reservations View booking history Cancel bookings (if allowed) Accommodation owners will be able to: Register their property Add room types and prices Update availability Manage bookings The first version will be a web-based system developed using PHP, HTML, CSS, JavaScript, MySQL, and Google Maps API.
 2. Overall Description 
2.1 Product Perspective SAFARI TANZANIA is a standalone web-based system that integrates accommodation booking with real-time location guidance. Unlike traditional systems that only focus on reservations, this platform combines booking functionality with mapping services. The system will function as a centralized tourism support tool. It will connect three main stakeholders: travelers, accommodation owners, and system administrators. The product will run through a web browser and will not require installation. It will have a user friendly interface that works on both desktop and mobile browsers. The system architecture will include a frontend (user interface), backend (server-side logic), and a database (data storage). The goal is to create a structured and reliable digital system rather than just a simple listing website.
 2.2 Product Goals The main goals of SAFARI TANZANIA are: To simplify the accommodation booking process To increase visibility for small and local accommodations To improve tourism digital services in Tanzania To provide accurate location guidance using maps To reduce booking confusion and misinformation To create a reliable and secure reservation system The system aims to reduce the inconvenience of switching between different platforms for booking and location searching. It also seeks to improve trust between travelers and accommodation providers by providing verified and structured information. 
3. Functional Requirements 
3.1 User Registration and Authentication The system shall allow users to register and log in securely. Travelers and accommodation owners must create accounts before accessing certain services. Users shall: Register using name, email, phone number, and password Log in using valid credentials Log out securely Reset password if forgotten Authentication ensures privacy, security, and personalized booking history. User sessions must be managed securely to prevent unauthorized access. 
3.2 Accommodation Management The system shall allow accommodation owners to manage their property listings. Owners shall: Add accommodation details (name, location, description) Upload images Add room types and prices Update room availability Edit or delete listings This module ensures accommodation information is always up to date and accurate for travelers. 
3.3 Booking Management The system shall allow users to make reservations. Users shall: Select accommodation and room Choose booking dates Confirm booking View booking history Cancel booking if policy allows The system shall automatically reduce availability after successful booking to prevent double booking. 
3.4 Location and Map Integration The system shall integrate Google Maps API. Users shall: View exact accommodation location See directions View map markers Access navigation support This feature improves travel convenience and reduces confusion in unfamiliar areas.
 4. Non-Functional Requirements 
4.1 Usability The system shall have a clean and simple interface. Navigation must be clear and consistent. Text should be readable with proper contrast. The design should work well on different screen sizes. 
4.2 Performance The system shall load pages quickly. Search results should display without noticeable delay. Booking confirmation must be processed efficiently.
 4.3 Security Passwords must be encrypted in the database. The system must use HTTPS in production. Input validation must prevent SQL injection and other attacks. 
4.4 Reliability The system must store booking data accurately. It must prevent duplicate bookings. Database backups should be maintained regularly. 
5. System Architecture Requirements 
5.1 Frontend HTML CSS JavaScript 
5.2 Backend PHP REST-like API structure 
5.3 Database MySQL Structured relational tables 
5.4 External Services Google Maps API
 6. Data Requirements Main entities include: Users Accommodations Rooms Bookings Payments Admin Relationships: One user can have many bookings One accommodation can have many rooms One room can have many bookings 
7. Conclusion This SRS defines the full structural and functional requirements of SAFARI TANZANIA. The system is designed to improve accommodation booking efficiency, increase digital visibility for small hospitality businesses, and simplify travel planning. Once approved, this document will guide database design, system development, testing, and deployment