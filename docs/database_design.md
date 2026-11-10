# Database Design Documentation

## Overview
This document describes the schema architecture, relationships, indexing strategies, and seed data for the Quiz Application database (`quiz_app`).

## Schema Architecture & Tables

### 1. `users`
Stores user authentication details and role specifications (`student` / `teacher`).

### 2. `quizzes`
Stores quizzes created by teachers. Foreign Key: `teacher_id` -> `users.id`.

### 3. `questions`
Contains multiple-choice questions belonging to specific quizzes. Foreign Key: `quiz_id` -> `quizzes.id`.

### 4. `attempts`
Tracks quiz attempts by students.
- `id` (INT, PK, AUTO_INCREMENT)
- `user_id` (INT, FK -> `users.id`)
- `quiz_id` (INT, FK -> `quizzes.id`)
- `score` (INT) - Total correct score.
- `total_questions` (INT) - Total question count.
- `started_at` (DATETIME)
- `completed_at` (DATETIME)

### 5. `attempt_answers`
Stores detailed responses for individual questions within an attempt.
- `id` (INT, PK, AUTO_INCREMENT)
- `attempt_id` (INT, FK -> `attempts.id`)
- `question_id` (INT, FK -> `questions.id`)
- `selected_option_id` (INT)
- `is_correct` (TINYINT)

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