# Quiz-App Database Documentation

## 1. Project Overview

The Quiz-App database stores and manages the information required by a web-based quiz application.

It supports two types of users:

- **Teachers:** Create, manage, publish quizzes and view student results.
- **Students:** View published quizzes, attempt quizzes and check their results.

The database is developed and tested independently before being connected to the PHP backend.

## 2. Database Structure

The database contains five main tables:

1. `users`
2. `quizzes`
3. `questions`
4. `attempts`
5. `attempt_answers`

### 2.1 Users Table

Stores information about registered users.

| Column | Data type | Description |
|---|---|---|
| id | INT UNSIGNED | Primary key, auto-increment |
| name | VARCHAR(100) | User's name |
| email | VARCHAR(254) | Unique email address |
| password_hash | VARCHAR(255) | Hashed password |
| role | ENUM | Student or teacher |
| created_at | TIMESTAMP | Account creation time |

**Important constraints:**
- `id` is the primary key.
- `email` must be unique.
- `role` can be either `student` or `teacher`.

### 2.2 Quizzes Table

Stores quizzes created by teachers.

| Column | Data type | Description |
|---|---|---|
| id | INT UNSIGNED | Primary key |
| owner_id | INT UNSIGNED | ID of the teacher who owns the quiz |
| title | VARCHAR(160) | Quiz title |
| description | VARCHAR(1000) | Quiz description |
| is_published | TINYINT(1) | Publication status |
| created_at | TIMESTAMP | Creation time |
| updated_at | TIMESTAMP | Last modification time |

**Important constraints:**
- `id` is the primary key.
- `owner_id` is a foreign key referencing `users(id)`.
- Deleting a user cascades to their quizzes.

### 2.3 Questions Table

Stores questions belonging to individual quizzes.

| Column | Data type | Description |
|---|---|---|
| id | INT UNSIGNED | Primary key |
| quiz_id | INT UNSIGNED | Associated quiz ID |
| prompt | VARCHAR(1000) | Question text |
| options_json | JSON | Answer options |
| correct_index | TINYINT UNSIGNED | Index of the correct option |
| sort_order | INT UNSIGNED | Question display order |

**Important constraints:**
- `id` is the primary key.
- `quiz_id` references `quizzes(id)`.
- Deleting a quiz cascades to its questions.
- Answer options are stored in JSON format.

### 2.4 Attempts Table

Stores each student's quiz submission and overall result.

| Column | Data type | Description |
|---|---|---|
| id | INT UNSIGNED | Primary key |
| quiz_id | INT UNSIGNED | Attempted quiz ID |
| user_id | INT UNSIGNED | Student ID |
| score | SMALLINT UNSIGNED | Marks obtained |
| total | SMALLINT UNSIGNED | Total possible marks |
| submitted_at | TIMESTAMP | Submission time |

**Important constraints:**
- `id` is the primary key.
- `quiz_id` references `quizzes(id)`.
- `user_id` references `users(id)`.
- A unique constraint on `(quiz_id, user_id)` prevents multiple attempts by the same student for the same quiz.

### 2.5 Attempt_Answers Table

Stores the individual answers submitted by students.

| Column | Data type | Description |
|---|---|---|
| id | INT UNSIGNED | Primary key |
| attempt_id | INT UNSIGNED | Associated attempt |
| question_id | INT UNSIGNED | Associated question |
| selected_index | TINYINT UNSIGNED | Selected option index |
| is_correct | TINYINT(1) | Whether the answer was correct |

**Important constraints:**
- `id` is the primary key.
- `attempt_id` references `attempts(id)`.
- `question_id` references `questions(id)`.
- A unique constraint on `(attempt_id, question_id)` prevents duplicate answers for the same question in an attempt.

## 3. Database Relationships

The database follows these relationships:

- One user can own multiple quizzes.
- One quiz can contain multiple questions.
- One student can have multiple attempts across different quizzes.
- One quiz can have multiple student attempts.
- One attempt can contain multiple submitted answers.
- One question can appear in answers belonging to different attempts.

### Relationship Diagram

```text
USERS
  |
  | 1 : Many (teacher ownership)
  |
  v
QUIZZES
  |
  | 1 : Many
  |
  v
QUESTIONS


USERS
  |
  | 1 : Many (student attempts)
  |
  v
ATTEMPTS
  |
  | 1 : Many
  |
  v
ATTEMPT_ANSWERS
  ^
  |
  | Many : 1
  |
QUESTIONS
```

## 4. Primary Keys and Foreign Keys

Primary keys uniquely identify records in each table.

Foreign keys maintain relationships between related tables and prevent references to nonexistent parent records.

The foreign-key relationships are:

| Child table | Foreign-key column | Parent table |
|---|---|---|
| quizzes | owner_id | users |
| questions | quiz_id | quizzes |
| attempts | quiz_id | quizzes |
| attempts | user_id | users |
| attempt_answers | attempt_id | attempts |
| attempt_answers | question_id | questions |

The tables use the InnoDB storage engine to support foreign keys and transactions.

## 5. Indexing and Constraints

Indexes improve the performance of frequently executed queries.

The database includes:

- Unique index on `users.email`.
- Index on quiz publication status and update time.
- Index on quiz owner and update time.
- Index on question quiz ID and display order.
- Unique index on `(quiz_id, user_id)` in attempts.
- Indexes on quiz submission time and student submission time.
- Unique index on `(attempt_id, question_id)` in attempt_answers.

These indexes support user authentication, quiz retrieval, question retrieval and result queries

---

## Performance Indexes
To optimize database queries during active quiz sessions and analytics generation, the following indexes are configured:
- `idx_attempts_user_id`: Optimizes lookups for student attempt history.
- `idx_attempts_quiz_id`: Accelerates aggregate calculations for quiz results.
- `idx_attempt_answers_attempt_id`: Speeds up query retrieval for detailed attempt breakdowns.
- `idx_attempt_answers_question_id`: Enables efficient per-question statistics queries.

---

## Seed Data (`database/seed.sql`)
Pre-populated sample data is included for immediate testing:
- **Sample Teacher:** `teacher@example.com`
- **Sample Student:** `student@example.com`
- **Sample Quiz:** General Computer Science Quiz (2 questions).

### Execution Command:
```bash
C:\xampp\mysql\bin\mysql.exe -u root quiz_app < database/seed.sql
