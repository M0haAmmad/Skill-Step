// Function to toggle between search bar and category/price filters
function toggleSearch() {
    const searchGroup = document.getElementById('searchBarGroup');
    const filterItems = document.querySelectorAll('.filter-item');
    const toggleWrapper = document.querySelector('.toggle-search-wrapper');
    const searchInput = document.getElementById('searchInput');

    if (searchGroup.style.display === 'none') {
        searchGroup.style.display = 'flex';
        filterItems.forEach(item => item.style.display = 'none');
        toggleWrapper.style.display = 'none';
        document.getElementById('filtersContainer').classList.add('search-active');
        searchInput.focus();
    } else {
        searchGroup.style.display = 'none';
        filterItems.forEach(item => item.style.display = 'flex');
        toggleWrapper.style.display = 'flex';
        document.getElementById('filtersContainer').classList.remove('search-active');
        searchInput.value = '';
        if (typeof filterData === 'function') filterData();
    }
}

let visibleCount = 20;

// Update visible cards based on filters and pagination
function updatePagination() {
    const cat = document.getElementById('catSelect').value;
    const price = document.getElementById('priceSelect').value;
    const searchStr = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.skill-card');

    let matchingCards = [];

    // Filter matching cards based on criteria
    cards.forEach(card => {
        const cardCat = card.getAttribute('data-cat');
        const cardPrice = parseInt(card.getAttribute('data-price')) || 0;
        const cardText = card.innerText.toLowerCase();

        // Category test
        let matchCat = (cat === 'all' || cat === cardCat);

        // Price test
        let matchPrice = true;
        if (price === 'free' && cardPrice !== 0) matchPrice = false;
        if (price === 'low' && (cardPrice === 0 || cardPrice >= 50)) matchPrice = false;
        if (price === 'high' && cardPrice < 50) matchPrice = false;

        // Text Search test
        let matchSearch = true;
        if (searchStr.length > 0 && !cardText.includes(searchStr)) matchSearch = false;

        if (matchCat && matchPrice && matchSearch) {
            matchingCards.push(card);
        } else {
            card.style.display = 'none';
        }
    });

    // Show batch up to visibleCount to support pagination
    matchingCards.forEach((card, index) => {
        if (index < visibleCount) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });

    // Show/Hide 'Show More' Button based on total matching cards
    const showMoreBtn = document.getElementById('showMoreContainer');
    if (matchingCards.length > visibleCount) {
        showMoreBtn.style.display = 'block';
    } else {
        showMoreBtn.style.display = 'none';
    }
}

// Increment pagination count and update view
function showMoreCards() {
    visibleCount += 20;
    updatePagination();
}

// Reset pagination when user updates filters
function filterData() {
    visibleCount = 20; // reset visible count whenever filters change
    updatePagination();
}

// Initialize displaying 20 cards on page load
document.addEventListener('DOMContentLoaded', () => {
    updatePagination();
});

// Profile dropdown toggle
function toggleProfileDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', (event) => {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown && dropdown.classList.contains('active')) {
        const container = document.querySelector('.profile-dropdown-container');
        if (container && !container.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    }
});

let pendingPurchase = null;

function openPurchaseModal(provider, price, xpVal, skillTitle, courseId, btn) {
    pendingPurchase = { provider, price, xpVal, skillTitle, courseId, btn };
    const modal = document.getElementById('purchaseModal');
    modal.querySelector('.modal-title').innerText = `Purchase Course "${skillTitle}"`;
    modal.querySelector('.modal-message').innerHTML = `Do you want to buy this course for <strong>${price} tokens</strong>?<br>You will earn <strong>${xpVal} XP</strong> upon purchase.`;
    modal.classList.add('open');
}

function hidePurchaseModal() {
    const modal = document.getElementById('purchaseModal');
    modal.classList.remove('open');
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast show ${type === 'error' ? 'toast-error' : ''}`;
    toast.innerHTML = `<i class='fa-solid ${type === 'error' ? 'fa-triangle-exclamation' : 'fa-gift'}'></i> <span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => { toast.remove(); }, 500);
    }, 3200);
}

function confirmPurchase() {
    if (!pendingPurchase) return;
    const { provider, price, xpVal, skillTitle, courseId, btn } = pendingPurchase;
    pendingPurchase = null;
    hidePurchaseModal();
    unlockSkill(provider, price, btn, xpVal, skillTitle, courseId);
}
//Check if the user have enough tokens
function unlockSkill(provider, price, btn, xpVal, skillTitle, courseId = null) {
    const tokensEl = document.getElementById('userTokens');
    const currentTokens = parseInt(tokensEl.innerText) || 0;

    if (currentTokens < price && price > 0) {
        showToast('You do not have enough tokens to unlock this!', 'error');
        return;
    }

    if (price === 0) {
        showToast(`Welcome! You have joined the free course by ${provider} and earned ${xpVal} XP.`);
    }

    fetch('update_stats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ price: price, xp: xpVal, skillTitle: skillTitle, courseId: courseId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userTokens').innerText = data.new_tokens;
                if (data.level_data) {
                    document.getElementById('xpText').innerText = Math.round(data.level_data.current_level_xp);
                    if (document.getElementById('nextLevelXP')) {
                        document.getElementById('nextLevelXP').innerText = data.level_data.next_level_required;
                    }
                    document.getElementById('userLevel').innerText = data.level_data.level;
                    document.getElementById('xpBar').style.width = data.level_data.progress_percent + '%';
                } else {
                    // Fallback for legacy
                    document.getElementById('xpText').innerText = data.new_xp % 1000;
                    document.getElementById('userLevel').innerText = data.new_lvl;
                    document.getElementById('xpBar').style.width = ((data.new_xp % 1000) / 1000 * 100) + '%';
                }

                if (courseId) {
                    btn.classList.remove('locked');
                    btn.innerHTML = `<i class='fa-solid fa-play'></i> <span>Watch Course</span>`;
                    btn.onclick = () => { window.location.href = 'course_player.php?id=' + courseId; };
                    window.location.href = 'course_player.php?id=' + courseId;
                    return;
                }

                btn.classList.remove('locked');
                btn.innerHTML = `<i class='fa-solid fa-check'></i> <span>Unlocked</span>`;
                btn.onclick = null;
                showToast(`Course purchased successfully! Thank you, ${provider}`);
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching update:', error);
            showToast('An error occurred while connecting to the server.', 'error');
        });
}

document.addEventListener('click', function (event) {
    const btn = event.target.closest('.unlock-btn');
    if (!btn) return;
    event.preventDefault();

    const provider = btn.dataset.provider || '';
    const price = parseInt(btn.dataset.price) || 0;
    const xpVal = parseInt(btn.dataset.xp) || 0;
    const skillTitle = btn.dataset.skillTitle || '';
    const courseId = btn.dataset.courseId ? parseInt(btn.dataset.courseId) : null;

    if (price > 0) {
        openPurchaseModal(provider, price, xpVal, skillTitle, courseId, btn);
        return;
    }

    unlockSkill(provider, price, btn, xpVal, skillTitle, courseId);
});

document.getElementById('purchaseConfirm')?.addEventListener('click', confirmPurchase);

// Mentor Profile Modal Logic
function openMentorProfile(mentorId) {
    const modal = document.getElementById('mentorProfileModal');
    const content = document.getElementById('mentorModalContent');
    
    // Show modal with loading spinner
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.querySelector('.mentor-modal').style.transform = 'scale(1)';
        modal.querySelector('.mentor-modal').style.opacity = '1';
    }, 10);
    
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
        </div>
    `;

    fetch('mentor_api.php?id=' + mentorId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = `<p style="color: #f43f5e; text-align: center;">Error: ${data.message}</p>`;
                return;
            }
            
            const mentor = data.data;
            const picUrl = mentor.profile_pic ? '../profile/uploads/' + mentor.profile_pic : '../images/avatar1.png';
            
            let coursesHtml = '';
            if(mentor.top_courses.length > 0) {
                coursesHtml = mentor.top_courses.map(c => `
                    <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 12px; display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                        <i class="${c.icon || 'fa-solid fa-book'}" style="color: var(--primary); font-size: 1.2rem;"></i>
                        <span style="flex: 1; color: white; font-weight: 600;">${c.title}</span>
                        <span style="color: var(--accent-gold); font-size: 0.9rem; font-weight: bold;"><i class="fa-solid fa-coins"></i> ${c.price_tokens == 0 ? 'Free' : c.price_tokens}</span>
                    </div>
                `).join('');
            } else {
                coursesHtml = '<p style="color: #94a3b8; font-size: 0.9rem;">No courses available at the moment.</p>';
            }

            content.innerHTML = `
                <div style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
                    <img src="${picUrl}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); box-shadow: 0 0 15px rgba(59,130,246,0.5);" onerror="this.src='../images/avatar1.png'">
                    <div>
                        <h2 style="color: white; margin: 0 0 5px 0; font-size: 1.5rem;">${mentor.full_name} <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1rem;"></i></h2>
                        <div style="color: #94a3b8; font-size: 0.95rem; display: flex; gap: 15px;">
                            <span><i class="fa-solid fa-star" style="color: var(--accent-gold);"></i> Level ${mentor.level}</span>
                            <span><i class="fa-solid fa-trophy" style="color: #fbbf24;"></i> ${mentor.xp} XP</span>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 15px 0;">
                    <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; padding: 15px; text-align: center;">
                        <i class="fa-solid fa-users" style="color: #10b981; font-size: 1.5rem; margin-bottom: 5px;"></i>
                        <div style="color: white; font-size: 1.5rem; font-weight: 800;">${mentor.total_students}</div>
                        <div style="color: #94a3b8; font-size: 0.8rem;">Students</div>
                    </div>
                    <div style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 16px; padding: 15px; text-align: center;">
                        <i class="fa-solid fa-video" style="color: #3b82f6; font-size: 1.5rem; margin-bottom: 5px;"></i>
                        <div style="color: white; font-size: 1.5rem; font-weight: 800;">${mentor.uploaded_courses}</div>
                        <div style="color: #94a3b8; font-size: 0.8rem;">Published</div>
                    </div>
                    <div style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); border-radius: 16px; padding: 15px; text-align: center;">
                        <i class="fa-solid fa-graduation-cap" style="color: #f59e0b; font-size: 1.5rem; margin-bottom: 5px;"></i>
                        <div style="color: white; font-size: 1.5rem; font-weight: 800;">${mentor.purchased_courses}</div>
                        <div style="color: #94a3b8; font-size: 0.8rem;">Purchased</div>
                    </div>
                </div>
                
                <div>
                    <h3 style="color: white; font-size: 1.1rem; margin-bottom: 15px;">Top Courses</h3>
                    ${coursesHtml}
                </div>
            `;
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = `<p style="color: #f43f5e; text-align: center;">An error occurred while loading mentor data.</p>`;
        });
}

function closeMentorProfile() {
    const modal = document.getElementById('mentorProfileModal');
    if(modal) {
        modal.querySelector('.mentor-modal').style.transform = 'scale(0.9)';
        modal.querySelector('.mentor-modal').style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}
