const $ = (selector) => document.querySelector(selector);
let user = null;
let csrfToken = '';
let authMode = 'login';
let editingQuizId = null;
let takeQuizId = null;

async function api(action, payload = {}, protectedAction = false) {
  const headers = { 'Content-Type': 'application/json' };
  if (protectedAction && csrfToken) headers['X-CSRF-Token'] = csrfToken;
  let response;
  try {
    response = await fetch('api.php', { method: 'POST', headers, credentials: 'same-origin', body: JSON.stringify({ action, ...payload }) });
  } catch {
    throw new Error('Can’t reach the PHP API. Run the site through a PHP-enabled server at localhost; opening index.html directly will not work.');
  }
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.error || 'Something went wrong. Please try again.');
  return result;
}
function message(el, text = '', error = false) { el.textContent = text; el.classList.toggle('error', error); }
function element(tag, className, text) { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; }
function showApp() {
  $('#auth-view').classList.add('hidden'); $('#app-view').classList.remove('hidden');
  $('#session-area').replaceChildren(element('span', '', `${user.name} · ${user.role}`));
  const logout = element('button', 'quiet', 'Sign out'); logout.type = 'button'; logout.onclick = async () => { try { await api('logout', {}, true); } finally { user = null; csrfToken = ''; location.reload(); } };
  $('#session-area').append(logout);
  $('#welcome-title').textContent = `Welcome, ${user.name}`;
  $('#welcome-subtitle').textContent = user.role === 'teacher' ? 'Create quizzes and follow your students’ progress.' : 'Pick a quiz and see what you know.';
  $('#teacher-tools').classList.toggle('hidden', user.role !== 'teacher');
  $('#list-eyebrow').textContent = user.role === 'teacher' ? 'YOUR CONTENT' : 'READY WHEN YOU ARE';
  $('#list-title').textContent = user.role === 'teacher' ? 'Your quizzes' : 'Available quizzes';
  loadQuizzes();
}
async function loadQuizzes() {
  const grid = $('#quiz-list'); grid.replaceChildren(); message($('#main-message'));
  try {
    const { quizzes } = await api('list_quizzes');
    if (!quizzes.length) { grid.append(element('p', 'muted', user.role === 'teacher' ? 'No quizzes yet. Create your first quiz above.' : 'No published quizzes yet. Check back soon.')); return; }
    quizzes.forEach((quiz) => {
      const card = element('article', 'quiz-card'); card.append(element('h3', '', quiz.title));
      if (quiz.description) card.append(element('p', '', quiz.description));
      card.append(element('div', 'card-meta', `${quiz.question_count} question${Number(quiz.question_count) === 1 ? '' : 's'}${user.role === 'teacher' ? ` · ${Number(quiz.is_published) ? 'Published' : 'Draft'}` : ''}`));
      const actions = element('div', 'card-actions');
      if (user.role === 'teacher') {
        const edit = element('button', 'secondary', 'Questions'); edit.onclick = () => openEditor(quiz.id, quiz.title);
        const toggle = element('button', 'secondary', Number(quiz.is_published) ? 'Unpublish' : 'Publish'); toggle.onclick = async () => { try { await api('publish_quiz', { quiz_id: Number(quiz.id), published: !Number(quiz.is_published) }, true); loadQuizzes(); } catch (e) { message($('#main-message'), e.message, true); } };
        const results = element('button', 'quiet', 'Results'); results.onclick = () => showQuizResults(quiz.id, quiz.title);
        const del = element('button', 'quiet', 'Delete'); del.onclick = async () => { if (!confirm(`Delete “${quiz.title}” and all its results?`)) return; try { await api('delete_quiz', { quiz_id: Number(quiz.id) }, true); loadQuizzes(); } catch (e) { message($('#main-message'), e.message, true); } };
        actions.append(edit, toggle, results, del);
      } else {
        const take = element('button', 'primary', 'Take quiz'); take.onclick = () => openQuiz(quiz.id); actions.append(take);
      }
      card.append(actions); grid.append(card);
    });
  } catch (e) { message($('#main-message'), e.message, true); }
}
async function openEditor(id, title) {
  editingQuizId = Number(id); $('#author-title').textContent = title; $('#author-panel').classList.remove('hidden'); $('#author-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  $('#question-form').reset(); message($('#editor-message')); await loadQuestions();
}
async function loadQuestions() {
  const list = $('#question-list'); list.replaceChildren();
  try {
    const { questions } = await api('get_quiz', { quiz_id: editingQuizId });
    questions.forEach((question, index) => {
      const row = element('div', 'question-row'); row.append(element('span', '', `${index + 1}. ${question.prompt}`));
      const remove = element('button', 'quiet', 'Remove'); remove.onclick = async () => { try { await api('delete_question', { question_id: Number(question.id) }, true); loadQuestions(); loadQuizzes(); } catch (e) { message($('#editor-message'), e.message, true); } };
      row.append(remove); list.append(row);
    });
    if (!questions.length) list.append(element('p', 'muted', 'Add at least one question to publish this quiz.'));
  } catch (e) { message($('#editor-message'), e.message, true); }
}
async function openQuiz(id) {
  try {
    const { quiz, questions } = await api('get_quiz', { quiz_id: Number(id) }); takeQuizId = Number(id);
    $('#take-title').textContent = quiz.title; $('#take-description').textContent = quiz.description || '';
    const form = $('#attempt-questions'); form.replaceChildren();
    questions.forEach((question, index) => {
      const block = element('section', 'attempt-question'); block.append(element('h3', '', `${index + 1}. ${question.prompt}`));
      question.options.forEach((option, optionIndex) => {
        const label = element('label', 'answer-option'); const radio = document.createElement('input'); radio.type = 'radio'; radio.name = String(question.id); radio.value = String(optionIndex); radio.required = true;
        label.append(radio, document.createTextNode(option)); block.append(label);
      }); form.append(block);
    });
    $('#take-panel').classList.remove('hidden'); $('#take-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (e) { message($('#main-message'), e.message, true); }
}
function renderResults(container, results, teacher = false) {
  container.replaceChildren();
  if (!results.length) { container.append(element('p', 'muted', 'No results to show yet.')); return; }
  results.forEach((item) => {
    const row = element('div', 'result-row');
    const label = teacher ? `${item.name} · ${item.email}` : item.title;
    row.append(element('span', '', `${label} · ${new Date(item.submitted_at.replace(' ', 'T') + 'Z').toLocaleString()}`));
    row.append(element('span', '', `${item.score} / ${item.total}`)); container.append(row);
  });
}
async function showMyResults() {
  $('#results-title').textContent = 'My results'; $('#results-panel').classList.remove('hidden'); $('#results-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  try { const { results } = await api('my_results'); renderResults($('#results-list'), results); } catch (e) { message($('#main-message'), e.message, true); }
}
async function showQuizResults(id, title) {
  $('#results-title').textContent = `${title} · Results`; $('#results-panel').classList.remove('hidden'); $('#results-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  try { const { results } = await api('quiz_results', { quiz_id: Number(id) }); renderResults($('#results-list'), results, true); } catch (e) { message($('#main-message'), e.message, true); }
}
function setAuthMode(mode) {
  authMode = mode; const registering = mode === 'register';
  $('#name-wrap').classList.toggle('hidden', !registering); $('#name').required = registering;
  $('#login-tab').classList.toggle('active', !registering); $('#register-tab').classList.toggle('active', registering);
  $('#auth-submit').textContent = registering ? 'Create account' : 'Sign in'; $('#password').autocomplete = registering ? 'new-password' : 'current-password'; message($('#auth-message'));
}

$('#login-tab').onclick = () => setAuthMode('login'); $('#register-tab').onclick = () => setAuthMode('register');
$('#auth-form').onsubmit = async (event) => {
  event.preventDefault(); const payload = { email: $('#email').value, password: $('#password').value };
  if (authMode === 'register') payload.name = $('#name').value;
  try { const result = await api(authMode === 'register' ? 'register' : 'login', payload); user = result.user; csrfToken = result.csrf_token; showApp(); }
  catch (e) { message($('#auth-message'), e.message, true); }
};
$('#quiz-form').onsubmit = async (event) => {
  event.preventDefault(); const title = $('#quiz-title').value; try { const result = await api('save_quiz', { title, description: $('#quiz-description').value }, true); $('#quiz-form').reset(); await loadQuizzes(); openEditor(result.quiz_id, title); }
  catch (e) { message($('#main-message'), e.message, true); }
};
$('#question-form').onsubmit = async (event) => {
  event.preventDefault(); const optionValues = [...document.querySelectorAll('.option-input')].map((input) => input.value.trim());
  const firstEmpty = optionValues.indexOf(''); if (firstEmpty !== -1 && optionValues.slice(firstEmpty + 1).some(Boolean)) { message($('#editor-message'), 'Fill the answer options in order.', true); return; }
  const options = optionValues.filter(Boolean);
  const correct = Number($('#correct-index').value); if (correct >= options.length) { message($('#editor-message'), 'Choose a correct answer that has an option.', true); return; }
  try { await api('save_question', { quiz_id: editingQuizId, prompt: $('#question-prompt').value, options, correct_index: correct }, true); $('#question-form').reset(); await loadQuestions(); await loadQuizzes(); message($('#editor-message'), 'Question added.'); }
  catch (e) { message($('#editor-message'), e.message, true); }
};
$('#attempt-form').onsubmit = async (event) => {
  event.preventDefault(); const answers = {};
  document.querySelectorAll('.attempt-question').forEach((block) => { const selected = block.querySelector('input:checked'); if (selected) answers[selected.name] = Number(selected.value); });
  try { const result = await api('submit_attempt', { quiz_id: takeQuizId, answers }, true); message($('#attempt-message'), `You scored ${result.score} / ${result.total} (${result.percentage}%).`); $('#attempt-form').querySelector('button[type="submit"]').disabled = true; }
  catch (e) { message($('#attempt-message'), e.message, true); }
};
$('#results-button').onclick = showMyResults; $('#refresh-button').onclick = loadQuizzes;
$('#close-editor').onclick = () => $('#author-panel').classList.add('hidden'); $('#close-results').onclick = () => $('#results-panel').classList.add('hidden'); $('#close-take').onclick = () => $('#take-panel').classList.add('hidden');

(async function restoreSession() {
  try { const result = await api('me'); user = result.user; csrfToken = result.csrf_token; showApp(); } catch { /* Signed out; keep the auth screen visible. */ }
})();
