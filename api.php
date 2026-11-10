<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Setup required: copy config.example.php to config.php and configure the database.']);
    exit;
}
$config = require $configPath;

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function input(): array {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 100000) respond(['error' => 'Request is too large.'], 400);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) respond(['error' => 'Invalid JSON body.'], 400);
    return $data;
}
function needUser(): array {
    if (empty($_SESSION['user'])) respond(['error' => 'Please sign in.'], 401);
    return $_SESSION['user'];
}
function needTeacher(): array {
    $user = needUser();
    if ($user['role'] !== 'teacher') respond(['error' => 'Teacher access required.'], 403);
    return $user;
}
function needCsrf(): void {
    $given = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !is_string($given) || !hash_equals($_SESSION['csrf'], $given)) {
        respond(['error' => 'Session check failed. Refresh and try again.'], 403);
    }
}
function cleanText(mixed $value, int $max, string $label, bool $allowEmpty = false): string {
    if (!is_string($value)) respond(['error' => "$label must be text."], 400);
    $value = trim($value);
    $length = preg_match_all('/./u', $value);
    if ((!$allowEmpty && $value === '') || $length === false || $length > $max) respond(['error' => "$label is required and must be at most $max characters."], 400);
    return $value;
}
function quizForOwner(PDO $db, int $quizId, int $ownerId): array {
    $q = $db->prepare('SELECT * FROM quizzes WHERE id = ? AND owner_id = ?');
    $q->execute([$quizId, $ownerId]);
    $quiz = $q->fetch();
    if (!$quiz) respond(['error' => 'Quiz not found.'], 404);
    return $quiz;
}
function questionOptions(mixed $options, mixed $correct): array {
    if (!is_array($options) || count($options) < 2 || count($options) > 6) respond(['error' => 'Provide 2 to 6 answer options.'], 400);
    $options = array_map(fn($v) => cleanText($v, 250, 'Option'), $options);
    if (count(array_unique(array_map('strtolower', $options))) !== count($options)) respond(['error' => 'Answer options must be unique.'], 400);
    if (!is_int($correct) || $correct < 0 || $correct >= count($options)) respond(['error' => 'Select a valid correct answer.'], 400);
    return [$options, $correct];
}

try {
    $db = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $data = input();
    $action = $data['action'] ?? '';
    if (!is_string($action)) respond(['error' => 'Action is required.'], 400);

    if ($action === 'register') {
        $name = cleanText($data['name'] ?? null, 100, 'Name');
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = $data['password'] ?? null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) respond(['error' => 'Enter a valid email address.'], 400);
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 200) respond(['error' => 'Password must be 8 to 200 characters.'], 400);
        $teachers = array_filter(array_map(fn($v) => strtolower(trim($v)), explode(',', (string)($config['teacher_emails'] ?? ''))));
        $role = in_array($email, $teachers, true) ? 'teacher' : 'student';
        try {
            $q = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $q->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') respond(['error' => 'An account with that email already exists.'], 409);
            throw $e;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$db->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => $role];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        respond(['user' => $_SESSION['user'], 'csrf_token' => $_SESSION['csrf']], 201);
    }
    if ($action === 'login') {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = $data['password'] ?? '';
        $q = $db->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
        $q->execute([$email]);
        $row = $q->fetch();
        if (!$row || !is_string($password) || !password_verify($password, $row['password_hash'])) respond(['error' => 'Email or password is incorrect.'], 401);
        session_regenerate_id(true);
        unset($row['password_hash']);
        $row['id'] = (int)$row['id'];
        $_SESSION['user'] = $row;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        respond(['user' => $row, 'csrf_token' => $_SESSION['csrf']]);
    }
    if ($action === 'me') {
        $user = needUser();
        respond(['user' => $user, 'csrf_token' => $_SESSION['csrf'] ?? '']);
    }
    if ($action === 'logout') {
        needUser(); needCsrf();
        $_SESSION = [];
        session_destroy();
        respond(['message' => 'Signed out.']);
    }

    $user = needUser();
    if (in_array($action, ['save_quiz','save_question','delete_question','delete_quiz','publish_quiz'], true)) needCsrf();

    if ($action === 'list_quizzes') {
        if ($user['role'] === 'teacher') {
            $q = $db->prepare('SELECT q.id, q.title, q.description, q.is_published, q.updated_at, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id=q.id) question_count FROM quizzes q WHERE q.owner_id=? ORDER BY q.updated_at DESC');
            $q->execute([$user['id']]);
        } else {
            $q = $db->query('SELECT q.id, q.title, q.description, q.updated_at, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id=q.id) question_count FROM quizzes q WHERE q.is_published=1 ORDER BY q.updated_at DESC');
        }
        respond(['quizzes' => $q->fetchAll()]);
    }
    if ($action === 'get_quiz') {
        $id = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) respond(['error' => 'Valid quiz_id required.'], 400);
        if ($user['role'] === 'teacher') $quiz = quizForOwner($db, (int)$id, (int)$user['id']);
        else {
            $q = $db->prepare('SELECT * FROM quizzes WHERE id=? AND is_published=1'); $q->execute([$id]); $quiz = $q->fetch();
            if (!$quiz) respond(['error' => 'Quiz not found.'], 404);
        }
        $q = $db->prepare('SELECT id, prompt, options_json, sort_order FROM questions WHERE quiz_id=? ORDER BY sort_order, id'); $q->execute([$id]);
        $questions = array_map(function($row) { $row['options'] = json_decode($row['options_json'], true) ?: []; unset($row['options_json']); return $row; }, $q->fetchAll());
        unset($quiz['owner_id']);
        respond(['quiz' => $quiz, 'questions' => $questions]);
    }
    if ($action === 'save_quiz') {
        $teacher = needTeacher();
        $title = cleanText($data['title'] ?? null, 160, 'Title');
        $description = cleanText($data['description'] ?? '', 1000, 'Description', true);
        $id = false;
        if (array_key_exists('quiz_id', $data)) {
            $id = filter_var($data['quiz_id'], FILTER_VALIDATE_INT);
            if (!$id) respond(['error' => 'Valid quiz_id required.'], 400);
        }
        if ($id) {
            $quiz = quizForOwner($db, (int)$id, (int)$teacher['id']);
            if ($quiz['is_published']) respond(['error' => 'Unpublish the quiz before editing it.'], 409);
            $q = $db->prepare('UPDATE quizzes SET title=?, description=? WHERE id=?'); $q->execute([$title,$description,$id]);
            respond(['quiz_id' => (int)$id, 'message' => 'Quiz saved.']);
        }
        $q = $db->prepare('INSERT INTO quizzes (owner_id,title,description) VALUES (?,?,?)'); $q->execute([$teacher['id'],$title,$description]);
        respond(['quiz_id' => (int)$db->lastInsertId(), 'message' => 'Quiz created.'], 201);
    }
    if ($action === 'save_question') {
        $teacher = needTeacher();
        $quizId = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quizId) respond(['error' => 'Valid quiz_id required.'], 400);
        $quiz = quizForOwner($db, (int)$quizId, (int)$teacher['id']);
        if ($quiz['is_published']) respond(['error' => 'Unpublish the quiz before editing it.'], 409);
        $prompt = cleanText($data['prompt'] ?? null, 1000, 'Question');
        [$options,$correct] = questionOptions($data['options'] ?? null, $data['correct_index'] ?? null);
        $questionId = false;
        if (array_key_exists('question_id', $data)) {
            $questionId = filter_var($data['question_id'], FILTER_VALIDATE_INT);
            if (!$questionId) respond(['error' => 'Valid question_id required.'], 400);
        }
        if ($questionId) {
            $exists = $db->prepare('SELECT id FROM questions WHERE id=? AND quiz_id=?');
            $exists->execute([$questionId,$quizId]);
            if (!$exists->fetch()) respond(['error' => 'Question not found.'], 404);
            $q = $db->prepare('UPDATE questions SET prompt=?, options_json=?, correct_index=? WHERE id=? AND quiz_id=?');
            $q->execute([$prompt,json_encode($options, JSON_UNESCAPED_UNICODE),$correct,$questionId,$quizId]);
        } else {
            $q = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM questions WHERE quiz_id=?'); $q->execute([$quizId]); $order = (int)$q->fetchColumn();
            $q = $db->prepare('INSERT INTO questions (quiz_id,prompt,options_json,correct_index,sort_order) VALUES (?,?,?,?,?)');
            $q->execute([$quizId,$prompt,json_encode($options, JSON_UNESCAPED_UNICODE),$correct,$order]);
            $questionId = (int)$db->lastInsertId();
        }
        respond(['question_id' => (int)$questionId, 'message' => 'Question saved.']);
    }
    if ($action === 'delete_question') {
        $teacher = needTeacher(); $id = filter_var($data['question_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) respond(['error'=>'Valid question_id required.'],400);
        $q = $db->prepare('SELECT q.quiz_id, z.is_published FROM questions q JOIN quizzes z ON z.id=q.quiz_id WHERE q.id=? AND z.owner_id=?'); $q->execute([$id,$teacher['id']]); $row=$q->fetch();
        if (!$row) respond(['error'=>'Question not found.'],404); if ($row['is_published']) respond(['error'=>'Unpublish the quiz before editing it.'],409);
        $db->prepare('DELETE FROM questions WHERE id=?')->execute([$id]); respond(['message'=>'Question deleted.']);
    }
    if ($action === 'delete_quiz') {
        $teacher = needTeacher(); $id = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) respond(['error'=>'Valid quiz_id required.'],400); quizForOwner($db,(int)$id,(int)$teacher['id']);
        $db->prepare('DELETE FROM quizzes WHERE id=?')->execute([$id]); respond(['message'=>'Quiz deleted.']);
    }
    if ($action === 'publish_quiz') {
        $teacher = needTeacher(); $id = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !is_bool($data['published'] ?? null)) respond(['error'=>'quiz_id and published boolean required.'],400);
        $quiz = quizForOwner($db,(int)$id,(int)$teacher['id']);
        if ($data['published'] && !$quiz['is_published']) {
            $q=$db->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id=?'); $q->execute([$id]);
            if ((int)$q->fetchColumn() < 1) respond(['error'=>'Add at least one question before publishing.'],409);
        }
        $db->prepare('UPDATE quizzes SET is_published=? WHERE id=?')->execute([$data['published']?1:0,$id]);
        respond(['message'=>$data['published']?'Quiz published.':'Quiz unpublished.']);
    }
    if ($action === 'submit_attempt') {
        if ($user['role'] !== 'student') respond(['error'=>'Student account required.'],403);
        needCsrf();
        $id = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !is_array($data['answers'] ?? null)) respond(['error'=>'Quiz and answers are required.'],400);
        $db->beginTransaction();
        $q=$db->prepare('SELECT id FROM quizzes WHERE id=? AND is_published=1 FOR UPDATE'); $q->execute([$id]);
        if (!$q->fetch()) { $db->rollBack(); respond(['error'=>'Quiz not found.'],404); }
        $q=$db->prepare('SELECT id,correct_index,options_json FROM questions WHERE quiz_id=? ORDER BY sort_order,id'); $q->execute([$id]); $questions=$q->fetchAll();
        if (!$questions || count($data['answers']) !== count($questions)) { $db->rollBack(); respond(['error'=>'Answer every question exactly once.'],400); }
        $byId=[]; foreach ($questions as $question) $byId[(string)$question['id']]=$question;
        foreach ($data['answers'] as $questionId=>$selected) {
            if (!isset($byId[(string)$questionId]) || !is_int($selected) || $selected < 0 || $selected >= count(json_decode($byId[(string)$questionId]['options_json'],true))) { $db->rollBack(); respond(['error'=>'An answer is invalid.'],400); }
        }
        $score=0; foreach ($questions as $question) if ($data['answers'][(string)$question['id']] === (int)$question['correct_index']) $score++;
        try {
            $q=$db->prepare('INSERT INTO attempts (quiz_id,user_id,score,total) VALUES (?,?,?,?)'); $q->execute([$id,$user['id'],$score,count($questions)]); $attemptId=(int)$db->lastInsertId();
            $answer=$db->prepare('INSERT INTO attempt_answers (attempt_id,question_id,selected_index,is_correct) VALUES (?,?,?,?)');
            foreach ($questions as $question) $answer->execute([$attemptId,$question['id'],$data['answers'][(string)$question['id']],$data['answers'][(string)$question['id']] === (int)$question['correct_index'] ? 1 : 0]);
            $db->commit();
        } catch (PDOException $e) { if ($db->inTransaction()) $db->rollBack(); if ($e->getCode()==='23000') respond(['error'=>'You have already submitted this quiz.'],409); throw $e; }
        respond(['attempt_id'=>$attemptId,'score'=>$score,'total'=>count($questions),'percentage'=>(int)round(100*$score/count($questions))],201);
    }
    if ($action === 'my_results') {
        $q=$db->prepare('SELECT a.id attempt_id,a.score,a.total,a.submitted_at,q.id quiz_id,q.title FROM attempts a JOIN quizzes q ON q.id=a.quiz_id WHERE a.user_id=? ORDER BY a.submitted_at DESC');
        $q->execute([$user['id']]); respond(['results'=>$q->fetchAll()]);
    }
    if ($action === 'quiz_results') {
        $teacher=needTeacher(); $id=filter_var($data['quiz_id'] ?? null,FILTER_VALIDATE_INT); if (!$id) respond(['error'=>'Valid quiz_id required.'],400);
        quizForOwner($db,(int)$id,(int)$teacher['id']);
        $q=$db->prepare('SELECT a.id attempt_id,u.name,u.email,a.score,a.total,a.submitted_at FROM attempts a JOIN users u ON u.id=a.user_id WHERE a.quiz_id=? ORDER BY a.submitted_at DESC');
        $q->execute([$id]); respond(['results'=>$q->fetchAll()]);
    }
    respond(['error'=>'Unknown action.'],400);
} catch (Throwable $e) {
    error_log((string)$e);
    respond(['error'=>'Server error. Check PHP and database configuration.'],500);
}
