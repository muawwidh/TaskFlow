# TaskFlow

TaskFlow is a web-based team and task management system for student project groups. It helps users create teams, invite members, organize tasks, track progress, and stay updated through notifications.

The project is built as a PHP and MySQL application with a responsive HTML, CSS, and JavaScript interface.

## Features

- User registration and login
- Session-based authentication
- Team creation and invite-code joining
- Team member management
- Task creation, editing, status updates, and deletion
- Task search and filtering
- Dashboard with team and task statistics
- Notifications for team and task activity
- User profile and password management
- External resource search and saving
- Responsive interface with light/dark theme support
- Embedded project-management guide videos

## Technology Stack

- PHP
- MySQL / MariaDB
- PDO for database access
- HTML5
- CSS3
- JavaScript
- Bootstrap

## Project Structure

```text
TaskFlow/
├── api/
│   ├── external_resources.php
│   ├── notifications.php
│   └── tasks.php
├── assets/
│   ├── css/
│   ├── js/
│   └── videos/
├── auth/
│   ├── login.php
│   ├── login_action.php
│   ├── logout.php
│   ├── register.php
│   └── register_action.php
├── config/
│   ├── database.php
│   ├── helpers.php
│   └── migration.sql
├── views/
│   ├── apis.php
│   ├── dashboard.php
│   ├── profile.php
│   ├── tasks.php
│   ├── teams.php
│   └── partials/
├── index.php
└── README.md
```

## Database

The database schema is defined in:

```text
config/migration.sql
```

Main tables include:

- `users`
- `teams`
- `team_members`
- `tasks`
- `task_comments`
- `notifications`
- `external_resources`

## Local Setup

1. Place the project folder inside a PHP server root, such as XAMPP `htdocs`.
2. Create or import the database using `config/migration.sql`.
3. Update database credentials in `config/database.php`.
4. Open the application in the browser.

Example local URL:

```text
http://localhost/TaskFlow/
```

## Demo Data

The migration file includes demo users for testing. The demo password listed in the project is:

```text
Password1!
```

## Main Pages

- Login: `auth/login.php`
- Register: `auth/register.php`
- Dashboard: `views/dashboard.php`
- Teams: `views/teams.php`
- Tasks: `views/tasks.php`
- Profile: `views/profile.php`
- API Resources: `views/apis.php`

## Purpose

TaskFlow is designed to support student teamwork by keeping team membership, task ownership, task progress, and collaboration resources in one place.
