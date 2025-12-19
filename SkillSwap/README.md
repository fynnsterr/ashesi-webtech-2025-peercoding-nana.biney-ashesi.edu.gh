# SkillSwap - Skill Exchange Platform

A community-driven platform where knowledge is the currency. Users can teach skills they possess and learn new ones from others in a direct, peer-to-peer exchange environment.

## Features

- **User Profiles**: Manage personal details and contact info.
- **Skill Management**: 
  - List skills you can **teach** (Offerings).
  - List skills you want to **learn** (Requests).
- **Skill Discovery**: Browse and search a catalog of skills offered by other users.
- **Exchange System**:
  - **Propose Exchange**: Offer to teach a skill in return for learning one.
  - **Pending Proposals**: View and accept/reject incoming exchange requests.
  - **Active Exchanges**: Track ongoing learning engagements.
- **Dashboard**: personalized overview of your skills, requests, and exchange stats.
- **Direct Messaging**: Communicate with your exchange partners.
- **Admin Panel**: Manage users, categories, and oversee platform activity.

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (PDO)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Server**: Apache (XAMPP)

## Installation

### Prerequisites

- XAMPP (or similar PHP/MySQL environment)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Steps

1. **Clone/Copy the project** to your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\skillswap\
   ```

2. **Create the database**:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the SQL schema file: `sql/schema.sql`
   - This will create the database `ssc2027` (or similar, check schema file) and all required tables.

3. **Configure database connection**:
   - Edit `config/database.php`
   - Update database credentials if needed (default: user='root', password='')

4. **Access the application**:
   - Open browser: `http://localhost/pages/index.php` 
   - **Default Admin Login**:
     - Email: `admin@fynn.com`
     - Password: `admin123`

## Project Structure

```
skillswap/
├── admin/              # Admin panel pages
├── assets/             # CSS and JavaScript
├── config/             # Configuration files
├── includes/           # Shared PHP includes (Auth, Header, Footer)
├── pages/              # Main application pages (Dashboard, Exchanges, Skills)
├── sql/                # Database schema
└── uploads/            # User uploaded files
```

## Database Schema

Key tables including:
- `users`: Registered learners and teachers.
- `skills_catalog`: Master list of available skills.
- `user_skills`: Pivot table linking users to skills (teaching/learning status).
- `exchange_proposals`: Records of exchange offers (Pending/Proposed).
- `exchange_matches`: Confirmed active exchanges.

## License

Built for educational purposes.
