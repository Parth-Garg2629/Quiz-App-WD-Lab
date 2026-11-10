# QuickQuiz

QuickQuiz is a lightweight classroom quiz app. Teachers can create and publish multiple-choice quizzes, and students can take published quizzes and review their scores.

## Features

- Student and teacher accounts with role-based access.
- Teacher access is assigned to email addresses configured in `teacher_emails`.
- Create quizzes and add questions with 2–6 answer options.
- Publish and unpublish quizzes; students can only see published quizzes.
- Automatically score quiz submissions and show student and teacher results.
- Store quiz attempts and per-question answers in MySQL.
- Use PHP sessions and CSRF tokens for protected actions.

## Technology

- PHP 8.1 or later, with PDO MySQL enabled.
- MySQL or a compatible MariaDB server.
- HTML, CSS, and vanilla JavaScript.
- Apache is optional if you use PHP's built-in development server.

## Project structure

```text
.
├── api.php                  # JSON API and server-side application logic
├── app.js                   # Browser UI behavior and API requests
├── config.example.php       # Example local database configuration
├── database/
│   ├── schema.sql           # Database and table definitions
│   └── seed.sql             # Currently empty; no sample records are inserted
├── docs/
│   └── database-design.md   # Database tables and relationships
├── index.html               # Application page
└── styles.css               # Application styles
```

## Run locally on Windows with XAMPP

1. Install XAMPP and start **MySQL** in the XAMPP Control Panel. Apache is optional when using PHP's built-in server.
2. Put the project in XAMPP's web root, for example:

   ```text
   C:\xampp\htdocs\Quiz-App-WD-Lab
   ```

3. Create a local config file by copying the example from the project directory:

   ```powershell
   Copy-Item config.example.php config.php
   ```

4. Edit `config.php` with your local MySQL connection details and the email address that should receive the teacher role:

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

   The example values assume a local XAMPP MySQL setup with an empty root password. Change them if your installation uses different credentials. To allow multiple teacher accounts, use a comma-separated list of email addresses.

5. Create the database tables using either method:

   **phpMyAdmin:** Open `http://localhost/phpmyadmin`, select **Import**, choose `database/schema.sql`, and run the import. The SQL file creates the `quiz_app` database and its tables.

   **MySQL command line:** From Command Prompt, run:

   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\Quiz-App-WD-Lab\database\schema.sql
   ```

   Add `-p` after `-u root` if MySQL requires a password; the client will prompt for it.

6. Start the PHP development server from the project directory:

   ```powershell
   Set-Location C:\xampp\htdocs\Quiz-App-WD-Lab
   C:\xampp\php\php.exe -S localhost:8000
   ```

   Alternatively, with Apache running, open `http://localhost/Quiz-App-WD-Lab`.

7. Open `http://localhost:8000` if using PHP's development server, or the Apache URL above. Do not open `index.html` directly from the file system; the browser needs the PHP API.

## First use

1. Create an account with an email listed in `teacher_emails`. It will be registered as a teacher.
2. Create another account with an email not on the list. It will be registered as a student.
3. As the teacher, create a quiz, add at least one question, then publish it.
4. Sign in as the student, take the published quiz, and submit answers.
5. Check **My results** as the student and **Results** on the quiz card as the teacher.

There are no built-in accounts or default passwords. Register accounts through the app. A user's role is assigned when their account is registered, so configure the teacher email before creating that account.

## Testing

There is currently no automated test suite. Use the following manual checklist in a browser:

### Teacher checks

- Register using an allowlisted email and confirm the teacher tools appear.
- Create a quiz and add a question with two to six distinct options.
- Confirm publishing is rejected if the quiz has no questions.
- Publish a quiz, confirm it is visible to a student, then unpublish it and confirm the student can no longer see it.
- View the quiz results after a student submits an attempt.
- Confirm a teacher cannot edit a quiz while it is published; unpublish it first.

### Student checks

- Register with a non-allowlisted email and confirm only published quizzes are listed.
- Take a quiz and confirm the displayed score matches the selected answers.
- Open **My results** and confirm the submission is listed.
- Try submitting the same quiz again and confirm the app rejects the duplicate attempt.
- Confirm a student cannot access teacher-only quiz management actions.

### Validation and error checks

- Try registering an already-used email and signing in with an incorrect password.
- Confirm the app rejects duplicate answer options and an invalid correct-answer selection.
- Confirm the API rejects malformed answer-option objects instead of storing a structure that the quiz UI cannot render. Valid options must be a sequential list of 2–6 strings.

## API overview

The browser sends JSON `POST` requests to `api.php`, with an `action` field. The API uses the PHP session cookie for authentication and an `X-CSRF-Token` header for protected operations.

| Action | Purpose |
|---|---|
| `register`, `login`, `me`, `logout` | Account registration, authentication, session restoration, and sign out |
| `list_quizzes`, `get_quiz` | List quizzes visible to the current user and load quiz questions |
| `save_quiz`, `save_question`, `delete_question`, `delete_quiz` | Teacher quiz management |
| `publish_quiz` | Publish or unpublish a teacher's quiz |
| `submit_attempt` | Validate and score a student's answers |
| `my_results`, `quiz_results` | Show a student's own results or a teacher's quiz results |

## Database

`database/schema.sql` creates five tables:

- `users` — account details, password hashes, and roles.
- `quizzes` — quiz title, description, owner, and publication status.
- `questions` — prompts, JSON-encoded answer options, correct option index, and display order.
- `attempts` — one student's score for a quiz; a unique key prevents a second attempt at the same quiz.
- `attempt_answers` — selected options and correctness for each submitted question.

Foreign keys and cascading deletes keep related quiz and attempt records consistent. See [docs/database-design.md](docs/database-design.md) for additional schema details.

## Recent fix

- **Answer-option validation:** `api.php` now requires options to be a sequential PHP list, in addition to requiring 2–6 valid, unique options. This rejects malformed JSON objects that could otherwise be stored but could not be rendered correctly by the browser. The PHP syntax check passed after this change.

## Troubleshooting

- **“Setup required”** — copy `config.example.php` to `config.php` and configure the database.
- **Database connection error** — check that MySQL is running and that `config.php` has the correct host, database name, user, and password.
- **“Can’t reach the PHP API”** — serve the app through Apache or PHP's development server; do not open the HTML file directly.
- **Teacher account appears as a student** — add its email to `teacher_emails` before registering it. Changing the allowlist does not change the role of an existing account.
- **No quizzes appear for students** — quizzes must have at least one question and be published by a teacher.

## Security and configuration

- `config.php` contains local database credentials and is excluded from Git. Do not commit credentials or production secrets.
- Passwords are stored as PHP password hashes, not plaintext.
- Quiz edits and submissions require authentication; teacher management actions also check the teacher role.
- This project is intended for local development and learning. Review and harden the deployment configuration before exposing it to the public internet.
