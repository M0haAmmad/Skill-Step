// Upload Profile Picture dynamically using fetch and FormData
function uploadProfilePic() {
    const input = document.getElementById('profilePicInput');
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('profilePic', file);

    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update preview image
            document.getElementById('profileImagePreview').src = 'uploads/' + data.fileName;
            Alert.success('Updated successfully!');
        } else {
            Alert.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Alert.error('Server connection error occurred.');
    });
}

// Update User Name dynamically
function updateName() {
    const firstName = document.getElementById('firstNameInput').value.trim();
    const lastName = document.getElementById('lastNameInput').value.trim();

    fetch('update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ firstName: firstName, lastName: lastName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Alert.success('Name updated successfully: ' + data.newName);
        } else {
            Alert.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Alert.error('Server connection error occurred.');
    });
}

// Generate simple 30-day heatmap calendar
document.addEventListener('DOMContentLoaded', () => {
    const heatmapContainer = document.getElementById('heatmapContainer');
    const loginHistoryDataStr = document.getElementById('loginHistoryData').innerText;
    let loginHistory = [];
    
    try {
        loginHistory = JSON.parse(loginHistoryDataStr);
    } catch(e) {
        console.error("Could not parse login history.");
    }

    // Generate last 30 days
    const today = new Date();
    
    // Create elements (we'll display them inline so order doesn't aggressively matter, but right-to-left looks nice for recent to the left in arabic design, or traditional right)
    for (let i = 29; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(today.getDate() - i);
        
        // Format YYYY-MM-DD to match PHP default
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const dateStr = `${year}-${month}-${day}`;

        const box = document.createElement('div');
        box.classList.add('heatmap-day');
        box.setAttribute('data-date', dateStr);
        
        // If this date is in the login history array, mark it active
        if (loginHistory.includes(dateStr)) {
            box.classList.add('active');
        }

        heatmapContainer.appendChild(box);
    }
});

// Trigger a mock backend event representing a user purchasing YOUR course!
function simulateSale() {
    fetch('update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'simulate_sale' })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            document.getElementById('creatorWalletDisplay').innerText = data.new_balance;
            Alert.success('Congratulations 🥳! Your course was sold, and you earned ' + data.earned + ' tokens in your wallet.');
        } else {
            Alert.error('Error: ' + (data.message || 'Connection failed'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Alert.error('An error occurred during the sale simulation.');
    });
}

// Submitting a new User-Created Course
function submitNewCourse(e) {
    e.preventDefault();
    
    const title = document.getElementById('courseTitle').value;
    const cat = document.getElementById('courseCategory').value;
    const icon = document.getElementById('courseIcon').value;
    const price = document.getElementById('coursePrice').value;
    const pxInput = document.getElementById('courseXP');
    const xp = pxInput ? pxInput.value : 100;
    const supportType = document.getElementById('courseSupport').value;
    const freeLessons = document.getElementById('courseFreeLessons') ? document.getElementById('courseFreeLessons').value : 0;
    const desc = document.getElementById('courseDescription').value;

    const formData = new FormData();
    formData.append('action', 'add_skill');
    formData.append('title', title);
    formData.append('category', cat);
    formData.append('icon', icon);
    formData.append('price', price);
    formData.append('xp', xp);
    formData.append('free_lessons_count', freeLessons);
    formData.append('support_type', supportType);
    formData.append('description', desc);
    
    // Collect Lessons (Videos + Titles)
    if (pendingLessons.length === 0) {
        Alert.warning('Please add at least one lesson to the course.');
        return;
    }

    pendingLessons.forEach((lesson) => {
        formData.append('videos[]', lesson.file);
        formData.append('lesson_titles[]', lesson.title);
    });

    // Collect Quiz Data
    const quizData = [];
    const qItems = document.querySelectorAll('.quiz-question-item');
    qItems.forEach((item) => {
        const qText = item.querySelector('.quiz-q-text').value;
        const choices = [];
        const choiceInputs = item.querySelectorAll('.choice-text');
        
        // Find which radio button is checked for this specific question
        const qId = item.id.split('-').pop();
        const correctRadio = item.querySelector(`input[name="correct_${qId}"]:checked`);
        const correctIdx = correctRadio ? correctRadio.value : 0;
        
        choiceInputs.forEach((ci, cIdx) => {
            choices.push({
                text: ci.value || `خيار ${cIdx + 1}`,
                is_correct: cIdx == correctIdx
            });
        });
        
        if(qText.trim() !== "") {
            quizData.push({ question: qText, choices: choices });
        }
    });
    
    if(quizData.length > 0) {
        formData.append('quiz_data', JSON.stringify(quizData));
    }

    const btn = document.querySelector('#createCourseForm button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Uploading...";
    btn.disabled = true;

    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        if (!r.ok) {
            if (r.status === 413) {
                throw new Error("Video file is too large for the server (413 Payload Too Large).");
            }
            const text = await r.text();
            throw new Error(`Server Error (${r.status}): ${text.substring(0, 100)}`);
        }
        return r.json();
    })
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        if(data.success) {
            showUploadSuccessModal(data);
        } else {
            Alert.error('Publishing failed: ' + (data.message || 'An unexpected error occurred.'));
        }
    })
    .catch(err => {
        console.error(err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        Alert.error('Server connection error occurred: ' + err.message);
    });
}

// Function to delete a course
function deleteCourse(skillId) {
    Alert.confirm("Are you sure you want to permanently delete this course and all associated videos? This action cannot be undone.", () => {
        proceedDeleteCourse(skillId);
    });
}

function proceedDeleteCourse(skillId) {
    const formData = new FormData();
    formData.append('action', 'delete_skill');
    formData.append('skill_id', skillId);
    
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const card = document.getElementById('my-course-' + skillId);
            if(card) {
                card.style.transition = '0.3s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
            showDeleteSuccessModal();
        } else {
            Alert.error('Error during deletion: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        Alert.error('An error occurred during deletion.');
    });
}

let questionCount = 0;
function addQuizQuestion() {
    questionCount++;
    const container = document.getElementById('quizQuestionsContainer');
    const qDiv = document.createElement('div');
    qDiv.className = 'glass-card quiz-question-item';
    qDiv.id = `q-item-${questionCount}`;
    qDiv.style = "padding:20px; border:1px solid rgba(255,255,255,0.1); margin-bottom:15px; background: rgba(0,0,0,0.2);";
    
    qDiv.innerHTML = `
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:10px;">
            <label style="color:#f59e0b; font-weight:bold; font-size:1.1rem;"><i class="fa-solid fa-circle-question"></i> Question ${questionCount}</label>
            <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:rgba(244, 63, 94, 0.1); border:none; color:#f43f5e; cursor:pointer; width:30px; height:30px; border-radius:8px; transition:0.2s;"><i class="fa-solid fa-trash"></i></button>
        </div>
        <input type="text" class="quiz-q-text" placeholder="What is the question you want to ask?" style="width:100%; margin-bottom:20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px; color:white;" required>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
            <div style="display:flex; gap:10px; align-items:center; background:rgba(255,255,255,0.03); padding:10px; border-radius:10px;">
                <input type="radio" name="correct_${questionCount}" value="0" checked style="accent-color:#10b981; width:18px; height:18px;">
                <input type="text" class="choice-text" placeholder="First Option (Correct?)" style="flex:1; background:none; border:none; border-bottom:1px solid rgba(255,255,255,0.1); color:white; padding:5px;">
            </div>
            <div style="display:flex; gap:10px; align-items:center; background:rgba(255,255,255,0.03); padding:10px; border-radius:10px;">
                <input type="radio" name="correct_${questionCount}" value="1" style="accent-color:#10b981; width:18px; height:18px;">
                <input type="text" class="choice-text" placeholder="Second Option" style="flex:1; background:none; border:none; border-bottom:1px solid rgba(255,255,255,0.1); color:white; padding:5px;">
            </div>
            <div style="display:flex; gap:10px; align-items:center; background:rgba(255,255,255,0.03); padding:10px; border-radius:10px;">
                <input type="radio" name="correct_${questionCount}" value="2" style="accent-color:#10b981; width:18px; height:18px;">
                <input type="text" class="choice-text" placeholder="Third Option" style="flex:1; background:none; border:none; border-bottom:1px solid rgba(255,255,255,0.1); color:white; padding:5px;">
            </div>
            <div style="display:flex; gap:10px; align-items:center; background:rgba(255,255,255,0.03); padding:10px; border-radius:10px;">
                <input type="radio" name="correct_${questionCount}" value="3" style="accent-color:#10b981; width:18px; height:18px;">
                <input type="text" class="choice-text" placeholder="Fourth Option" style="flex:1; background:none; border:none; border-bottom:1px solid rgba(255,255,255,0.1); color:white; padding:5px;">
            </div>
        </div>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:10px; text-align:left;">* Select the radio button next to the correct option</p>
    `;
    container.appendChild(qDiv);
    qDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function markNotifRead(notifId, element) {
    if (element.classList.contains('unread')) {
        const formData = new FormData();
        formData.append('action', 'mark_notif_read');
        formData.append('notif_id', notifId);

        fetch('update_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                element.classList.remove('unread');
                element.style.background = 'rgba(255,255,255,0.03)';
                element.style.borderColor = 'rgba(255,255,255,0.05)';
                const dot = element.querySelector('div[style*="background:var(--primary)"]');
                if (dot) dot.remove();
                
                // Optional: Update the badge count on the avatar if you want it to be real-time
                const badge = document.querySelector('.notif-badge');
                if(badge) {
                    let count = parseInt(badge.innerText);
                    if(count > 1) badge.innerText = count - 1;
                    else badge.remove();
                }
            }
        })
        .catch(err => console.error('Error marking notification as read:', err));
    }
}

// Advanced Lesson Builder Logic
let pendingLessons = [];

function handleLessonFileSelect(input) {
    const info = document.getElementById('fileSelectedInfo');
    if (input.files.length > 0) {
        info.innerText = `📎 Selected file: ${input.files[0].name}`;
        info.style.display = 'block';
    }
}

function addNewLessonToList() {
    const fileInput = document.getElementById('lessonFile');
    const titleInput = document.getElementById('lessonTitleInput');
    const list = document.getElementById('lessonsList');
    const noMsg = document.getElementById('noLessonsMsg');

    if (!fileInput.files.length) {
        Alert.warning('Please select a video file first');
        return;
    }
    if (!titleInput.value.trim()) {
        Alert.warning('Please enter a name for the lesson');
        return;
    }

    const file = fileInput.files[0];
    const title = titleInput.value.trim();

    pendingLessons.push({ file, title });

    if (noMsg) noMsg.style.display = 'none';

    const item = document.createElement('div');
    item.className = 'glass-card';
    item.style = "display:flex; justify-content:space-between; align-items:center; padding:12px 15px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); margin-bottom:10px;";
    item.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-play" style="color:var(--primary);"></i>
            <span style="font-weight:bold; color:white;">${title}</span>
            <span style="font-size:0.8rem; color:var(--text-muted); opacity:0.7;">(${file.name})</span>
        </div>
        <button type="button" class="remove-lesson-btn" style="background:none; border:none; color:#f43f5e; cursor:pointer; font-size:1.1rem; padding:5px;">
            <i class="fa-solid fa-trash"></i>
        </button>
    `;
    
    // Add click listener for removal
    const removeBtn = item.querySelector('.remove-lesson-btn');
    removeBtn.onclick = () => {
        const idx = pendingLessons.findIndex(l => l.file === file && l.title === title);
        if (idx > -1) pendingLessons.splice(idx, 1);
        item.remove();
        if (pendingLessons.length === 0 && noMsg) noMsg.style.display = 'block';
    };

    list.appendChild(item);

    // Reset inputs
    fileInput.value = '';
    titleInput.value = '';
    document.getElementById('fileSelectedInfo').style.display = 'none';
}

function showUploadSuccessModal(data) {
    const modalId = 'uploadSuccessModal';
    let existing = document.getElementById(modalId);
    if(existing) existing.remove();
    
    const overlay = document.createElement('div');
    overlay.id = modalId;
    overlay.style.cssText = `
        position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
        display: flex; align-items: center; justify-content: center; z-index: 9999;
        opacity: 0; transition: opacity 0.4s ease;
    `;
    
    let rewardHtml = '';
    if (data.reward) {
        rewardHtml = `
            <div style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 15px; margin-top: 20px;">
                <p style="color: #fbbf24; font-weight: bold; margin-bottom: 5px;">🎁 Publishing Reward</p>
                <div style="display: flex; justify-content: center; gap: 20px;">
                    <span style="color: white; font-weight:bold;"><i class="fa-solid fa-coins" style="color: #f59e0b;"></i> ${data.reward.tokens} Tokens</span>
                    <span style="color: white; font-weight:bold;"><i class="fa-solid fa-star" style="color: #f59e0b;"></i> ${data.reward.xp} XP</span>
                </div>
            </div>
        `;
    }

    overlay.innerHTML = `
        <div style="background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(30,41,59,0.98)); border: 1px solid rgba(16,185,129,0.4); border-radius: 28px; padding: 50px 40px; width: 90%; max-width: 460px; text-align: center; box-shadow: 0 30px 80px rgba(0,0,0,0.6), 0 0 60px rgba(16,185,129,0.15); transform: scale(0.85) translateY(30px); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);" id="uploadSuccessCard">
            <div style="width: 90px; height: 90px; background: linear-gradient(135deg, rgba(16,185,129,0.25), rgba(5,150,105,0.08)); border: 2px solid rgba(16,185,129,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 3rem; color: #10b981; box-shadow: 0 0 30px rgba(16,185,129,0.3);">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 style="font-size: 2rem; font-weight: 900; color: white; margin-bottom: 10px;">Published Successfully!</h2>
            <p style="color: #94a3b8; font-size: 1.1rem; line-height: 1.6;">Your course has been uploaded and shared with the community.</p>
            ${rewardHtml}
            <button onclick="window.location.reload()" style="margin-top: 30px; background: #10b981; color: white; border: none; padding: 15px 30px; border-radius: 12px; font-size: 1.15rem; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);">Continue</button>
        </div>
    `;

    document.body.appendChild(overlay);
    
    // Generate Confetti
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'];
    for(let i=0; i<50; i++) {
        let conf = document.createElement('div');
        conf.style.cssText = `
            position: absolute;
            top: -10px; left: ${Math.random() * 100}%;
            width: ${Math.random() * 10 + 5}px; height: ${Math.random() * 10 + 5}px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
            z-index: -1;
            animation: fall ${Math.random() * 3 + 2}s linear forwards;
            animation-delay: ${Math.random() * 1}s;
        `;
        overlay.querySelector('#uploadSuccessCard').appendChild(conf);
    }
    
    if(!document.getElementById('confettiKeyframes')) {
        let style = document.createElement('style');
        style.id = 'confettiKeyframes';
        style.innerHTML = `@keyframes fall { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(600px) rotate(720deg); opacity: 0; } }`;
        document.head.appendChild(style);
    }

    // Animate in
    setTimeout(() => {
        overlay.style.opacity = '1';
        document.getElementById('uploadSuccessCard').style.transform = 'scale(1) translateY(0)';
    }, 10);
}

function showDeleteSuccessModal() {
    const modalId = 'deleteSuccessModal';
    let existing = document.getElementById(modalId);
    if(existing) existing.remove();
    
    const overlay = document.createElement('div');
    overlay.id = modalId;
    overlay.style.cssText = `
        position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
        display: flex; align-items: center; justify-content: center; z-index: 9999;
        opacity: 0; transition: opacity 0.4s ease;
    `;
    
    overlay.innerHTML = `
        <div style="background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(30,41,59,0.98)); border: 1px solid rgba(244,63,94,0.4); border-radius: 28px; padding: 50px 40px; width: 90%; max-width: 460px; text-align: center; box-shadow: 0 30px 80px rgba(0,0,0,0.6), 0 0 60px rgba(244,63,94,0.15); transform: scale(0.85) translateY(30px); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);" id="deleteSuccessCard">
            <div style="width: 90px; height: 90px; background: linear-gradient(135deg, rgba(244,63,94,0.25), rgba(225,29,72,0.08)); border: 2px solid rgba(244,63,94,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 3rem; color: #f43f5e; box-shadow: 0 0 30px rgba(244,63,94,0.3);">
                <i class="fa-solid fa-trash-check"></i>
            </div>
            <h2 style="font-size: 2rem; font-weight: 900; color: white; margin-bottom: 10px;">Deleted Successfully</h2>
            <p style="color: #94a3b8; font-size: 1.1rem; line-height: 1.6;">The course and all associated lessons have been permanently deleted from the platform.</p>
            <button onclick="document.getElementById('${modalId}').remove()" style="margin-top: 30px; background: #f43f5e; color: white; border: none; padding: 15px 30px; border-radius: 12px; font-size: 1.15rem; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 10px 20px rgba(244, 63, 94, 0.3);">Close</button>
        </div>
    `;

    document.body.appendChild(overlay);
    
    setTimeout(() => {
        overlay.style.opacity = '1';
        document.getElementById('deleteSuccessCard').style.transform = 'scale(1) translateY(0)';
    }, 10);
}

function deleteAllNotifs() {
    Alert.confirm('Are you sure you want to delete all notifications?', () => {
        fetch('delete_all_notifications.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    Alert.error("Error: " + data.message);
                }
            })
            .catch(e => console.error(e));
    });
}
