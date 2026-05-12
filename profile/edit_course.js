function submitEditCourse(e) {
    e.preventDefault();
    const skillId = document.getElementById('editSkillId').value;
    const title = document.getElementById('editCourseTitle').value;
    const cat = document.getElementById('editCourseCategory').value;
    const icon = document.getElementById('editCourseIcon').value;
    const price = document.getElementById('editCoursePrice').value;
    const xp = document.getElementById('editCourseXP').value;
    const freeLessons = document.getElementById('editCourseFreeLessons').value;
    const supportType = document.getElementById('editCourseSupport').value;
    const desc = document.getElementById('editCourseDescription').value;

    const formData = new FormData();
    formData.append('action', 'edit_skill');
    formData.append('skill_id', skillId);
    formData.append('title', title);
    formData.append('category', cat);
    formData.append('icon', icon);
    formData.append('price', price);
    formData.append('xp', xp);
    formData.append('free_lessons_count', freeLessons);
    formData.append('support_type', supportType);
    formData.append('description', desc);

    // Add New Lessons from Builder
    pendingLessons.forEach((lesson) => {
        formData.append('videos[]', lesson.file);
        formData.append('lesson_titles[]', lesson.title);
    });

    const btn = document.querySelector('#editCourseForm button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Processing changes...";
    btn.disabled = true;

    fetch('update_profile.php', { method: 'POST', body: formData })
        .then(r => {
            if (r.status === 413) throw new Error("The file size is too large for the server.");
            return r.json();
        })
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (data.success) {
                Alert.success('Course updated successfully!');
                setTimeout(() => window.location.href = 'profile.php', 1500);
            } else {
                Alert.error('Error: ' + (data.message || 'An unexpected error occurred.'));
            }
        })
        .catch(err => {
            console.error(err);
            btn.innerHTML = originalText;
            btn.disabled = false;
            Alert.error('Error: ' + err.message);
        });
}

function deleteSingleVideo(videoId, skillId) {
    Alert.confirm("Are you sure you want to permanently delete this video?", () => {
        proceedDeleteVideo(videoId, skillId);
    });
}

function proceedDeleteVideo(videoId, skillId) {
    const fd = new FormData();
    fd.append('action', 'delete_single_video');
    fd.append('video_id', videoId);
    fd.append('skill_id', skillId);

    fetch('update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const vi = document.getElementById('vidItem_' + videoId);
                if (vi) { vi.style.opacity = '0'; setTimeout(() => vi.remove(), 300); }
                Alert.success('Video deleted successfully');
            } else {
                Alert.error('Error deleting video: ' + data.message);
            }
        })
        .catch(e => {
            console.error(e);
            Alert.error('Error connecting to server.');
        });
}

// Lesson Builder Logic for Edit Page
let pendingLessons = [];

function handleLessonFileSelect(input) {
    const info = document.getElementById('fileSelectedInfo');
    if (input.files.length > 0) {
        info.innerText = `📎 Selected File: ${input.files[0].name}`;
        info.style.display = 'block';
    }
}

function addNewLessonToList() {
    const fileInput = document.getElementById('lessonFile');
    const titleInput = document.getElementById('lessonTitleInput');
    const list = document.getElementById('lessonsList');

    if (!fileInput.files.length) {
        Alert.warning('Please select a video file first');
        return;
    }
    if (!titleInput.value.trim()) {
        Alert.warning('Please enter a title for the lesson');
        return;
    }

    const file = fileInput.files[0];
    const title = titleInput.value.trim();

    pendingLessons.push({ file, title });

    const item = document.createElement('div');
    item.className = 'glass-card';
    item.style = "display:flex; justify-content:space-between; align-items:center; padding:12px 15px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); margin-bottom:10px;";
    item.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-play" style="color:var(--primary);"></i>
            <span style="font-weight:bold; color:white;">${title}</span>
            <span style="font-size:0.8rem; color:var(--accent-teal); opacity:0.8;">(New Video: ${file.name})</span>
        </div>
        <button type="button" class="remove-lesson-btn" style="background:none; border:none; color:#f43f5e; cursor:pointer; font-size:1.1rem; padding:5px;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    const removeBtn = item.querySelector('.remove-lesson-btn');
    removeBtn.onclick = () => {
        const idx = pendingLessons.findIndex(l => l.file === file && l.title === title);
        if (idx > -1) pendingLessons.splice(idx, 1);
        item.remove();
    };

    list.appendChild(item);

    // Reset inputs
    fileInput.value = '';
    titleInput.value = '';
    document.getElementById('fileSelectedInfo').style.display = 'none';
}

function addQuizToCourse(courseId) {
    Alert.confirm("Do you want to add a quiz to this course?", () => {
        proceedAddQuiz(courseId);
    });
}

function proceedAddQuiz(courseId) {
    const fd = new FormData();
    fd.append('action', 'add_quiz');
    fd.append('course_id', courseId);

    fetch('update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Alert.success('Quiz added successfully!');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Alert.error('Error: ' + (data.message || 'An unexpected error occurred.'));
            }
        })
        .catch(e => {
            console.error(e);
            Alert.error('Error: ' + e.message);
        });
}

function deleteQuiz(courseId) {
    Alert.confirm("Are you sure you want to delete this quiz permanently? All questions and attempts will be deleted.", () => {
        proceedDeleteQuiz(courseId);
    });
}

function proceedDeleteQuiz(courseId) {
    const fd = new FormData();
    fd.append('action', 'delete_quiz');
    fd.append('course_id', courseId);

    fetch('update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Alert.success('Quiz deleted successfully!');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Alert.error('Error: ' + (data.message || 'An unexpected error occurred.'));
            }
        })
        .catch(e => {
            console.error(e);
            Alert.error('Error: ' + e.message);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('editCourseForm');
    if (form) {
        form.addEventListener('submit', submitEditCourse);
    }
    const saveBtn = document.getElementById('saveCourseBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            if (e.target.type !== 'submit') return;
            submitEditCourse(e);
        });
    }
});