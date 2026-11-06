\# AgriConnect – Agricultural Marketplace System



\## 1. Project Overview



AgriConnect is a web-based agricultural marketplace that connects farmers directly with buyers. Farmers can manage their profiles and produce listings, while buyers can browse available produce and place orders. Administrators oversee users and system activities.



\## 2. Main Features



\- User registration and login.

\- Separate dashboards for administrators, farmers and buyers.

\- Farmer profile management.

\- Produce catalogue with product images and details.

\- Add, edit and delete personal produce listings.

\- Shopping cart and order management.

\- M-Pesa payment information.

\- Contact messages and notifications.

\- Testimonials.



\## 3. Technologies Used



\- HTML5

\- CSS3

\- Bootstrap 5.3.3

\- JavaScript

\- PHP

\- MySQL

\- Bootstrap Icons

\- XAMPP



\## 4. System Requirements



\- Windows operating system.

\- XAMPP with Apache and MySQL.

\- Web browser.

\- Git (optional, for cloning the repository).



\## 5. Installation and Setup



\### Step 1: Download the Project



Clone the repository:



```bash

git clone https://github.com/techgirl-phaline/Agriconnect.git

```



Alternatively, download the project as a ZIP file from GitHub.



\### Step 2: Place the Project in XAMPP



Copy the project folder into:



```text

C:\\xampp\\htdocs\\AgriConnect

```



\### Step 3: Start XAMPP



Open XAMPP Control Panel and start Apache and MySQL.



\### Step 4: Create the Database



1\. Open `http://localhost/phpmyadmin`.

2\. Create a database named `agriconnect`.

3\. Select the database.

4\. Import `agriconnect\_schema.sql`.

5\. Import `agriconnect\_categories.sql`.



\### Step 5: Configure the Database Connection



Open `db.php` and confirm the settings:



\- Host: `localhost`

\- Username: `root`

\- Password: Leave blank (default XAMPP setup).

\- Database: `agriconnect`



\### Step 6: Configure Payment Settings



The project uses a local `payment-config.php` file for payment settings. If it is not included in the repository, use `payment-config.example.php` as a guide to create your local configuration.



\### Step 7: Open the Application



Visit:



```text

http://localhost/AgriConnect/index.php

```



\## 6. Test Accounts and Login Credentials



The following existing accounts are provided for assessment and testing.



\### Administrator Account



| Detail | Information |

|---|---|

| Name | Phaline Kemunto |

| Email | kemuntophaline109@gmail.com |

| Password | Agriconnect@123 |

| Role | Administrator |

| Login URL | `http://localhost/AgriConnect/admin/login.php` |



\### Farmer Accounts



\*\*Farmer 1\*\*



| Detail | Information |

|---|---|

| Name | Dennis okindo |

| Email | dennisokindo@gmail.com |

| Password | Dennis@3848 |

| Role | Farmer |

| Login URL | `http://localhost/AgriConnect/farmer/login.php` |



\*\*Farmer 2\*\*



| Detail | Information |

|---|---|

| Name | zipporah araka |

| Email | zipporaharaka40@gmail.com |

| Password | Zippy@5487 |

| Role | Farmer |

| Login URL | `http://localhost/AgriConnect/farmer/login.php` |



\### Buyer Accounts



\*\*Buyer 1\*\*



| Detail | Information |

|---|---|

| Name | Zawadi Zenka |

| Email | zawadi@gmail.com |

| Password | Zawadi@3494 |

| Role | Buyer |

| Login URL | `http://localhost/AgriConnect/buyer/login.php` |



\*\*Buyer 2\*\*



| Detail | Information |

|---|---|

| Name | Neema Aisha |

| Email | neemaaisha23@gmail.com |

| Password | Neema@011 |

| Role | Buyer |

| Login URL | `http://localhost/AgriConnect/buyer/login.php` |



These accounts allow the assessor to test the administrator, farmer and buyer functionalities.



\## 7. Database Files



The repository contains:



\- `agriconnect\_schema.sql` – Database structure and table definitions.

\- `agriconnect\_categories.sql` – Sample agricultural product categories.



Database name: `agriconnect`.



\## 8. Workplace Safety, Health Practices and E-Waste Handling



\### 8.1 Workplace Safety and Health (OHS) Practices



During the development, coding and testing phases of the AgriConnect application, strict occupational health and safety standards were observed to maintain a secure and productive environment.



\- \*\*Ergonomic Workstation Maintenance:\*\* Maintained proper postural alignment, adjustable seating and proper monitor eye-level positioning to mitigate repetitive strain injuries (RSI) and physical fatigue during extended coding sessions.

\- \*\*Electrical Safety Protocols:\*\* Ensured all power strips, device chargers and peripheral hardware cords were neatly routed and free from fraying, overload or exposed wiring to eliminate potential electrical hazards.

\- \*\*Environmental Comfort:\*\* Ensured adequate lighting and room ventilation to minimize visual strain and maintain a safe indoor climate.



\### 8.2 Correct E-Waste Handling and Disposal



In compliance with sustainable environmental guidelines and responsible hardware management for electronic waste (e-waste):



\- \*\*Hardware Longevity and Reuse:\*\* Maximized the lifespan of existing development hardware and digital peripherals through routine maintenance and component repurposing before considering replacements.

\- \*\*Responsible Recycling and Disposal:\*\* Isolated obsolete electronic components, faulty cables and dead batteries, ensuring they were routed to appropriate e-waste recycling collection facilities rather than disposed of via standard municipal waste streams.

\- \*\*Data Sanitization:\*\* Performed secure data wiping and partition formatting on storage media or hardware components undergoing decommissioning to protect system security and user privacy.



\### 8.3 Assessor Compliance Summary



\- \*\*Task Item:\*\* Observe workplace safety and health practices and correct e-waste handling.

\- \*\*Compliance Status:\*\* Addressed through adherence to ergonomic and electrical safety practices and responsible e-waste handling.



\## 9. GitHub Repository



Project Repository:



https://github.com/techgirl-phaline/Agriconnect.git



\## 10. Project Information



\- \*\*Project Name:\*\* AgriConnect

\- \*\*Project Type:\*\* Web-Based Agricultural Marketplace

\- \*\*Database:\*\* MySQL

\- \*\*Development Environment:\*\* XAMPP

\- \*\*Purpose:\*\* To connect farmers and buyers through an online agricultural marketplace.

