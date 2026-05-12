document.addEventListener('DOMContentLoaded', () => {
    loadNotificationSettings();
});

function updateName(event) {
    event.preventDefault();
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();

    fetch('../profile/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ firstName, lastName })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Alert.success('تم حفظ الاسم بنجاح.');
                setTimeout(() => location.reload(), 1500);
            } else {
                Alert.error('حدث خطأ: ' + (data.message || 'فشل الحفظ'));
            }
        })
        .catch(err => {
            console.error(err);
            Alert.error('تعذر الاتصال بالخادم.');
        });
}

function updatePicture(event) {
    event.preventDefault();
    const input = document.getElementById('profilePic');
    if (!input.files.length) {
        Alert.warning('يرجى اختيار صورة أولاً.');
        return;
    }

    const formData = new FormData();
    formData.append('profilePic', input.files[0]);

    fetch('../profile/update_profile.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Alert.success('تم تحديث صورة الملف الشخصي.');
                setTimeout(() => location.reload(), 1500);
            } else {
                Alert.error('حدث خطأ: ' + (data.message || 'فشل الرفع'));
            }
        })
        .catch(err => {
            console.error(err);
            Alert.error('تعذر الاتصال بالخادم.');
        });
}

function loadNotificationSettings() {
    const notifyValue = localStorage.getItem('notifyInstant') === 'true';
    const chatValue = localStorage.getItem('notifyChat') === 'true';
    const updatesValue = localStorage.getItem('notifyUpdates') === 'true';
    document.getElementById('notifyToggle').checked = notifyValue;
    document.getElementById('chatToggle').checked = chatValue;
    document.getElementById('updatesToggle').checked = updatesValue;

    document.getElementById('notifyToggle').addEventListener('change', e => {
        localStorage.setItem('notifyInstant', e.target.checked);
    });
    document.getElementById('chatToggle').addEventListener('change', e => {
        localStorage.setItem('notifyChat', e.target.checked);
    });
    document.getElementById('updatesToggle').addEventListener('change', e => {
        localStorage.setItem('notifyUpdates', e.target.checked);
    });
}