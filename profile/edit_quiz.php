<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

require_once '../Main/db_connection.php';
$user_id = intval($_SESSION['user_id']);
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0)
    die("Invalid Course ID");

// Check if user owns the course
$chq = "SELECT creator_id AS user_id, title FROM courses WHERE course_id = ?";
$chst = mysqli_prepare($conn, $chq);
mysqli_stmt_bind_param($chst, "i", $course_id);
mysqli_stmt_execute($chst);
$course_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chst));

if (!$course_row || ($course_row['user_id'] != $user_id && strpos($_SESSION['roles'], 'admin') === false))
    die("Unauthorized access");

// Get quiz
$quiz_q = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
if (mysqli_num_rows($quiz_q) == 0) {
    mysqli_query($conn, "INSERT INTO quizzes (course_id) VALUES ($course_id)");
    $quiz_id = mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE courses SET has_quiz = 1 WHERE course_id = $course_id");
} else {
    $quiz_id = mysqli_fetch_assoc($quiz_q)['quiz_id'];
}

// Get questions
$questions_q = mysqli_query($conn, "SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY question_id");
$questions = [];
while ($q = mysqli_fetch_assoc($questions_q)) {
    $qid = $q['question_id'];
    $choices_q = mysqli_query($conn, "SELECT * FROM quiz_choices WHERE question_id = $qid ORDER BY choice_id");
    $choices = [];
    while ($c = mysqli_fetch_assoc($choices_q)) {
        $choices[] = $c;
    }
    $q['choices'] = $choices;
    $questions[] = $q;
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz | Skill-Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="../Main/alert-system.css?v=<?php echo time(); ?>">
    <style>
        .question-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .choice-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .correct-choice {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
    </style>
</head>

<body style="min-height: 100vh; overflow-y: auto;">
    <nav>
        <a href="profile.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';"> Skill-Step
        </a>
        <a href="edit_course.php?id=<?php echo $course_id; ?>" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Edit Course
        </a>
    </nav>
    <br><br>
    <div style="max-width: 900px; margin: 0 auto; padding: 20px;">
        <h2 style="margin-bottom:20px; color:var(--text-main);"><i class="fa-solid fa-clipboard-question"></i> Edit Quiz for Course: <?php echo htmlspecialchars($course_row['title']); ?></h2>

        <div class="glass-card" style="padding:30px;">
            <div id="questionsContainer">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item" id="question_<?php echo $question['question_id']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="color: var(--text-main);">Question <?php echo $index + 1; ?>:</h4>
                            <button type="button" onclick="deleteQuestion(<?php echo $question['question_id']; ?>)" style="background: none; border: none; color: #f43f5e; cursor: pointer;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <textarea class="question-text" rows="2" style="width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px;"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                        <div class="choices-container">
                            <?php foreach ($question['choices'] as $choice): ?>
                                <div class="choice-item <?php echo $choice['is_correct'] ? 'correct-choice' : ''; ?>">
                                    <input type="radio" name="correct_<?php echo $question['question_id']; ?>" value="<?php echo $choice['choice_id']; ?>" <?php echo $choice['is_correct'] ? 'checked' : ''; ?> onchange="updateCorrectChoice(<?php echo $question['question_id']; ?>, <?php echo $choice['choice_id']; ?>)">
                                    <input type="text" class="choice-text" value="<?php echo htmlspecialchars($choice['choice_text']); ?>" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 8px; border-radius: 6px;">
                                    <button type="button" onclick="deleteChoice(this, <?php echo $choice['choice_id']; ?>)" style="background: none; border: none; color: #f43f5e; cursor: pointer;">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addChoice(this.closest('.question-item'))" class="btn-action" style="background: rgba(255,255,255,0.1); margin-top: 10px;">
                            <i class="fa-solid fa-plus"></i> Add Choice
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="addQuestion()" class="btn-action" style="background: var(--primary); width: 100%; margin-top: 20px;">
                <i class="fa-solid fa-plus"></i> Add New Question
            </button>

            <button id="saveQuizBtn" type="button" onclick="saveQuiz()" class="btn-save" style="width: 100%; margin-top: 20px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <i class="fa-solid fa-save"></i> Save Changes
            </button>
        </div>
    </div>

    <script src="../Main/alert-system.js?v=<?php echo time(); ?>"></script>
    <script>
        let quizId = <?php echo $quiz_id; ?>;

        function addQuestion() {
            // Add new question UI
            const container = document.getElementById('questionsContainer');
            const questionCount = container.children.length + 1;
            const radioName = `correct_new_${Date.now()}`;
            const newQuestion = document.createElement('div');
            newQuestion.className = 'question-item';
            newQuestion.dataset.radioName = radioName;
            newQuestion.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="color: var(--text-main);">Question ${questionCount}:</h4>
                    <button type="button" onclick="deleteQuestion(this.closest('.question-item'))" style="background: none; border: none; color: #f43f5e; cursor: pointer;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <textarea class="question-text" rows="2" placeholder="Type your question here..." style="width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px;"></textarea>
                <div class="choices-container">
                    <div class="choice-item">
                        <input type="radio" name="${radioName}" checked>
                        <input type="text" class="choice-text" placeholder="First choice (Correct)" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 8px; border-radius: 6px;">
                        <button type="button" onclick="deleteChoice(this)" style="background: none; border: none; color: #f43f5e; cursor: pointer;">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                    </div>
                </div>
                <button type="button" onclick="addChoice(this.closest('.question-item'))" class="btn-action" style="background: rgba(255,255,255,0.1); margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> Add Choice
                </button>
            `;
            container.appendChild(newQuestion);
        }

        function addChoice(questionElement) {
            if (typeof questionElement === 'number') {
                questionElement = document.getElementById('question_' + questionElement);
            }
            if (!questionElement || !questionElement.querySelector) return;

            const radioName = questionElement.dataset.radioName || `correct_${Date.now()}`;
            questionElement.dataset.radioName = radioName;
            const choicesContainer = questionElement.querySelector('.choices-container');
            const choiceItem = document.createElement('div');
            choiceItem.className = 'choice-item';
            choiceItem.innerHTML = `
                <input type="radio" name="${radioName}">
                <input type="text" class="choice-text" placeholder="New choice" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 8px; border-radius: 6px;">
                <button type="button" onclick="deleteChoice(this)" style="background: none; border: none; color: #f43f5e; cursor: pointer;">
                    <i class="fa-solid fa-minus"></i>
                </button>
            `;
            choicesContainer.appendChild(choiceItem);
        }

        function deleteQuestion(questionId) {
            if (typeof questionId === 'number') {
                if (!confirm('Are you sure you want to delete this question?')) return;
                // Delete from database
                fetch('update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_question', question_id: questionId })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        document.getElementById('question_' + questionId).remove();
                        Alert.success('Question deleted');
                    } else {
                        Alert.error('Error: ' + data.message);
                    }
                });
            } else {
                // Remove from UI
                questionId.remove();
            }
        }

        function deleteChoice(choiceBtn, choiceId = null) {
            if (choiceId !== null) {
                Alert.confirm('Are you sure you want to delete this choice?', () => {
                    proceedDeleteChoice(choiceBtn, choiceId);
                });
            } else {
                const item = choiceBtn.closest('.choice-item');
                if (item) item.remove();
            }
        }

        function proceedDeleteChoice(choiceBtn, choiceId) {
                // Delete from database
                fetch('update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_choice', choice_id: choiceId })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        const item = choiceBtn.closest('.choice-item');
                        if (item) item.remove();
                        Alert.success('Choice deleted');
                    } else {
                        Alert.error('Error: ' + data.message);
                    }
                });
        }

        function updateCorrectChoice(questionId, choiceId) {
            // Update correct choice in database
            fetch('update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_correct_choice', question_id: questionId, choice_id: choiceId })
            });
        }

        function saveQuiz() {
            const questions = [];
            let valid = true;

            document.querySelectorAll('.question-item').forEach((qItem, index) => {
                const questionText = qItem.querySelector('.question-text').value.trim();
                if (!questionText) return;

                const choices = [];
                let correctCount = 0;
                qItem.querySelectorAll('.choice-item').forEach(cItem => {
                    const choiceText = cItem.querySelector('.choice-text').value.trim();
                    const radio = cItem.querySelector('input[type="radio"]');
                    if (choiceText) {
                        const isCorrect = radio && radio.checked;
                        choices.push({
                            text: choiceText,
                            is_correct: isCorrect
                        });
                        if (isCorrect) correctCount++;
                    }
                });

                if (choices.length < 2) {
                    valid = false;
                    Alert.warning(`Question ${index + 1} needs at least 2 choices.`);
                    return;
                }
                if (correctCount !== 1) {
                    valid = false;
                    Alert.warning(`Question ${index + 1} must have exactly one correct answer.`);
                    return;
                }

                questions.push({
                    text: questionText,
                    choices: choices
                });
            });

            if (!valid) return;
            if (questions.length === 0) {
                Alert.warning('Please add at least one question before saving.');
                return;
            }

            // Save to database
            fetch('update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_quiz', quiz_id: quizId, questions: questions })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Alert.success('Quiz saved successfully!');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    Alert.error('Error: ' + data.message);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const saveBtn = document.getElementById('saveQuizBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveQuiz);
            }
        });
    </script>
</body>
</html>