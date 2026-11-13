# 🚀 QuickQuiz – Online Classroom Quiz Application

QuickQuiz is a lightweight, web-based classroom quiz application designed to make conducting and participating in quizzes simple and efficient.

The platform provides separate interfaces for teachers and students. Teachers can create quizzes, manage questions, publish or unpublish quizzes, and monitor student performance. Students can attempt published quizzes, receive automatically calculated scores, and review their results.

Built using **PHP, MySQL, HTML, CSS, and JavaScript**, QuickQuiz provides a simple and secure environment for managing classroom assessments.

---

## 📸 Screenshots

### 👨‍🏫 Teacher Dashboard

The teacher dashboard allows teachers to create quizzes, manage questions, control quiz publication, and view student results.

![Teacher Dashboard](screenshots/Screenshot%202026-11-10%20233853.png)

### 👨‍🎓 Student Dashboard

The student interface displays available quizzes, allows students to attempt questions, and provides access to their quiz results.

![Student Dashboard](screenshots/Screenshot%202026-11-10%20233941.png)

---

## ✨ Key Features

### 👤 User Management
- Separate student and teacher accounts.
- Role-based authentication and authorization.
- Teacher accounts are assigned through an email allowlist (`teacher_emails`).
- Secure login and registration using PHP sessions.
- Passwords stored using secure password hashing.

### 📝 Quiz Management
- Teachers can create and manage multiple quizzes.
- Add questions with 2–6 answer options.
- Specify the correct answer for each question.
- Publish and unpublish quizzes.
- Edit quizzes after unpublishing them.
- Students can only access published quizzes.

### 📊 Quiz Assessment
- Students can attempt available quizzes.
- Automatic evaluation of submitted answers.
- Automatic score calculation.
- Students can view their previous results.
- Teachers can view student performance for their quizzes.
- Duplicate attempts are prevented.

### 🔐 Security
- PHP session-based authentication.
- CSRF token protection for sensitive operations.
- Role-based access control.
- Server-side input validation.
- Prepared SQL statements using PDO.
- Database constraints to maintain data consistency.

---

## 🛠️ Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.1+ | Backend development and application logic |
| MySQL / MariaDB | Database management |
| HTML5 | Application structure |
| CSS3 | Styling and interface design |
| JavaScript | Frontend interactions and API communication |
| PDO | Secure database connectivity |
| PHP Sessions | User authentication and session management |

---

## 📁 Project Structure

```text
QuickQuiz/
│
├── api.php                  # JSON API and server-side application logic
├── app.js                   # Browser UI behavior and API requests
├── index.html               # Main application page
├── styles.css               # Application styles
├── config.example.php       # Example database configuration
│
├── database/
│   ├── schema.sql           # Database and table definitions
│   └── seed.sql             # Currently empty; no sample records
│
├── docs/
│   └── database-design.md   # Database tables and relationships
│
└── README.md
```

---

## ⚙️ Installation and Setup

Follow these steps to run QuickQuiz locally on Windows.

### Prerequisites

Make sure you have installed:

- XAMPP
- PHP 8.1 or later
- MySQL or MariaDB
- A modern web browser

### Step 1: Clone the Repository

```bash
git clone <repository-url>
```

Navigate to the project directory:

```bash
cd QuickQuiz
```

Alternatively, download the project and extract it into your XAMPP `htdocs` directory.

### Step 2: Start XAMPP

1. Open the XAMPP Control Panel.
2. Start the **MySQL** service.
3. Apache is optional if you use PHP's built-in development server.

### Step 3: Configure the Database Connection

Create a local configuration file by copying the example:

```powershell
Copy-Item config.example.php config.php
```

Open `config.php` and configure your database credentials:

```php
<?php

return [
    'db_host' => '127.0.0.1',
    'db_name' => 'quiz_app',
    'db_user' => 'root',
    'db_password' => '',
    'teacher_emails' => 'teacher@example.com',
];
```

**Note:** These are example credentials for a default local XAMPP installation. Update them according to your MySQL configuration.

To register multiple teachers, provide comma-separated email addresses.

### Step 4: Import the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

1. Navigate to the **Import** section.
2. Select `database/schema.sql`.
3. Execute the SQL file.

This creates the `quiz_app` database and all required tables.

Alternatively, use the MySQL command line:

```cmd
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\QuickQuiz\database\schema.sql
```

Add `-p` after `-u root` if MySQL requires a password. The client will prompt you to enter it.

### Step 5: Start the Application

Open PowerShell in the project directory:

```powershell
Set-Location C:\xampp\htdocs\QuickQuiz
C:\xampp\php\php.exe -S localhost:8000
```

### Step 6: Open QuickQuiz

Visit the following URL in your browser:

```text
http://localhost:8000
```

Alternatively, with Apache running, visit:

```text
http://localhost/QuickQuiz
```

**Important:** Do not open `index.html` directly from the file system. The application requires a PHP server to communicate with its backend API.

---

## 🚦 Getting Started

QuickQuiz does not include predefined accounts. Users must register through the application.

### Teacher Workflow

1. Add the teacher's email address to `teacher_emails` in `config.php`.
2. Register using that email address.
3. Log in to access the teacher dashboard.
4. Create a new quiz.
5. Add questions and answer options.
6. Select the correct answer for each question.
7. Publish the quiz.
8. Monitor student submissions and results.

### Student Workflow

1. Register using an email address not included in the teacher allowlist.
2. Log in to access the student dashboard.
3. Browse available published quizzes.
4. Select a quiz and answer its questions.
5. Submit the quiz.
6. View the automatically calculated score.
7. Review previous attempts in the **My Results** section.

---

## 🗄️ Database Design

QuickQuiz uses MySQL to store user information, quizzes, questions, and student assessment records.

The database consists of five main tables:

| Table | Description |
|---|---|
| `users` | Stores user accounts, password hashes, and roles. |
| `quizzes` | Stores quiz details, descriptions, ownership, and publication status. |
| `questions` | Stores question text, JSON-encoded answer options, correct answers, and display order. |
| `attempts` | Stores student quiz attempts and calculated scores. |
| `attempt_answers` | Stores selected answers and correctness for each question. |

### Database Relationships

- A teacher can create multiple quizzes.
- Each quiz can contain multiple questions.
- A student can attempt multiple quizzes.
- Each quiz attempt belongs to one student and one quiz.
- Each attempt can contain multiple question answers.
- A unique constraint prevents a student from attempting the same quiz more than once.

For detailed database documentation, refer to:

[`docs/database-design.md`](docs/database-design.md)

---

## 🔌 API Overview

QuickQuiz uses a PHP-based JSON API.

The frontend communicates with `api.php` using HTTP POST requests. Requests contain an `action` field to identify the required operation.

Protected requests use the PHP session cookie and an `X-CSRF-Token` header.

| API Action | Description |
|---|---|
| `register` | Register a new user. |
| `login` | Authenticate a user. |
| `me` | Restore the current user session. |
| `logout` | Sign out the current user. |
| `list_quizzes` | Retrieve quizzes visible to the user. |
| `get_quiz` | Retrieve quiz details and questions. |
| `save_quiz` | Create or update a quiz. |
| `save_question` | Add or update a question. |
| `delete_question` | Delete a question. |
| `delete_quiz` | Delete a quiz. |
| `publish_quiz` | Publish or unpublish a quiz. |
| `submit_attempt` | Submit answers and calculate the score. |
| `my_results` | Retrieve the student's results. |
| `quiz_results` | Retrieve results for a teacher's quiz. |

---

## 🧪 Testing

The following test cases can be used to verify the application's functionality.

### Teacher Testing

- Register using an allowlisted email address and confirm teacher tools appear.
- Create a quiz and add questions with two to six answer options.
- Verify that a quiz cannot be published without questions.
- Publish and unpublish quizzes.
- Verify that published quizzes are visible to students.
- Verify that published quizzes cannot be edited.
- View student submissions and results.

### Student Testing

- Register using a non-allowlisted email address.
- Verify that only published quizzes are visible.
- Attempt a quiz and submit answers.
- Verify automatic score calculation.
- View previous quiz results.
- Verify that duplicate attempts are rejected.
- Verify that students cannot access teacher-only operations.

### Validation Testing

- Attempt registration with an existing email address.
- Attempt login with an incorrect password.
- Submit duplicate answer options.
- Submit an invalid correct-answer selection.
- Submit malformed answer-option objects.
- Verify that valid answer options contain 2–6 distinct strings in sequential order.

---

## 🔒 Security and Configuration

QuickQuiz implements several security measures:

- **Password Security:** Passwords are stored using PHP password hashing functions.
- **Session Management:** PHP sessions maintain authenticated user states.
- **CSRF Protection:** CSRF tokens protect sensitive operations.
- **Role-Based Authorization:** Teacher-only operations are restricted to authorized teachers.
- **Input Validation:** Server-side validation prevents malformed data from being stored.
- **SQL Injection Prevention:** PDO prepared statements are used for database queries.
- **Configuration Security:** Database credentials are maintained in `config.php`, which should not be committed to Git.

**Note:** This project is intended for educational purposes and local development. Additional security hardening is recommended before production deployment.

---

## 👥 Our Team

QuickQuiz was developed collaboratively by a team of six members, with each member contributing to different aspects of the project, including backend development, frontend development, database management, testing, debugging, and documentation.

| No. | Team Member | Role & Contribution |
|---|---|---|
| 1 | **Parth Garg** | Backend development and server-side application logic. |
| 2 | **Jitu Rewar** | Frontend development – UI implementation and interface design. |
| 3 | **Yash Sharma** | Database design, MySQL integration, and database management. |
| 4 | **Harsh Popat** | Frontend development – UI components and client-side functionality. |
| 5 | **Hemang Saraiya** | Backend development support, testing, debugging, documentation, and overall project improvements. |
| 6 | **Piyush Pareek** | Database development support, database integration, and data management. |

---

## 🎯 Project Objective

The main objective of QuickQuiz is to simplify classroom assessments by providing an easy-to-use digital platform where teachers can conduct quizzes and students can evaluate their knowledge.

The project demonstrates practical implementation of:

- Client-server architecture.
- Database management using MySQL.
- Backend development using PHP.
- User authentication and authorization.
- JSON API communication.
- Secure data handling.
- Collaborative software development.





