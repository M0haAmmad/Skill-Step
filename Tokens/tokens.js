// Calculate $ value based on token input
function calculateDollar() {
    const tokenInput = document.getElementById('calcTokenInput');
    const dollarInput = document.getElementById('calcDollarInput');
    
    let tokens = parseInt(tokenInput.value);
    if (isNaN(tokens) || tokens < 0) {
        tokens = 0;
    }

    // 1 token = 0.02 Dollar
    const dollars = (tokens * 0.02).toFixed(2);
    dollarInput.value = dollars;
}

// Calculate token amount based on $ input
function calculateToken() {
    const tokenInput = document.getElementById('calcTokenInput');
    const dollarInput = document.getElementById('calcDollarInput');
    
    let dollars = parseFloat(dollarInput.value);
    if (isNaN(dollars) || dollars < 0) {
        dollars = 0;
    }

    // $1 = 50 tokens (since 1 token = 0.02)
    const tokens = Math.floor(dollars / 0.02);
    tokenInput.value = tokens;
}

// Update the price text entirely on the Buy button based on select
function updateBuyPrice() {
    const select = document.getElementById('buyPackage');
    const selectedOption = select.options[select.selectedIndex];
    const priceStr = selectedOption.getAttribute('data-price');
    document.getElementById('buyPriceDisplay').innerText = priceStr;
}

// Live warning if user enters exchange amount > current balance
function updateExchangeWarning(maxTokens) {
    const input = document.getElementById('exchangeAmount');
    const warning = document.getElementById('exchangeWarning');
    const btn = document.querySelector('.exchange-btn');
    const val = parseInt(input.value);

    if (val > maxTokens) {
        warning.style.display = 'block';
        warning.innerText = 'Insufficient balance. The maximum available for cashout is ' + maxTokens + ' tokens.';
        btn.disabled = true;
        btn.style.opacity = '0.5';
        input.style.borderColor = '#ef4444';
    } else if (val < 500) {
        warning.style.display = 'block';
        warning.innerText = 'The minimum cashout amount is 500 tokens.';
        btn.disabled = true;
        btn.style.opacity = '0.5';
        input.style.borderColor = '#ef4444';
    } else {
        warning.style.display = 'none';
        btn.disabled = false;
        btn.style.opacity = '1';
        input.style.borderColor = 'var(--primary)'; // Reset to primary/neutral
    }
}

// Intercept form submissions
function fakeSubmit(event, type) {
    event.preventDefault();
    
    const toast = document.getElementById('actionToast');
    
    if (type === 'buy') {
        const select = document.getElementById('buyPackage');
        const tokensToBuy = parseInt(select.value);
        toast.innerHTML = `<i class='fa-solid fa-circle-check'></i> <span>Great! You have successfully purchased ${tokensToBuy} tokens. (Simulator)</span>`;
        toast.className = 'toast show';
        toast.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
        
    } else if (type === 'exchange') {
        const amount = document.getElementById('exchangeAmount').value;
        const paypalEmail = document.querySelector('input[type="email"]').value; // Assuming the exchange email input
        const btn = document.querySelector('.exchange-btn');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        const formData = new FormData();
        formData.append('amount', amount);
        formData.append('paypal_email', paypalEmail);

        fetch('request_cashout.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toast.innerHTML = `<i class='fa-solid fa-check-circle'></i> <span>${data.message}</span>`;
                toast.className = 'toast show';
                toast.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                
                // Update UI balance
                const balanceDisplay = document.getElementById('tokenAmountText');
                const balanceCard = document.getElementById('currentBalanceDisplay');
                if (balanceDisplay) balanceDisplay.innerText = data.new_balance;
                if (balanceCard) balanceCard.setAttribute('data-tokens', data.new_balance);
                
                // Reset form
                document.getElementById('exchangeAmount').value = 500;
            } else {
                toast.innerHTML = `<i class='fa-solid fa-exclamation-circle'></i> <span>${data.message}</span>`;
                toast.className = 'toast show';
                toast.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            }
            
            btn.disabled = false;
            btn.innerHTML = 'Confirm Cashout';
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = 'Confirm Cashout';
        });
    }
}

// Toggle payment form visibility
function togglePaymentForm() {
    const method = document.getElementById('paymentMethod').value;
    document.getElementById('visaDetails').style.display = 'none';
    document.getElementById('paypalDetails').style.display = 'none';
    document.getElementById('appleDetails').style.display = 'none';
    
    // Remote 'required' so hidden forms don't block submission
    const allInputs = document.querySelectorAll('.payment-details-box input');
    allInputs.forEach(input => input.removeAttribute('required'));

    if (method === 'visa') {
        document.getElementById('visaDetails').style.display = 'block';
        document.querySelectorAll('#visaDetails input').forEach(i => i.setAttribute('required', 'true'));
    } else if (method === 'paypal') {
        document.getElementById('paypalDetails').style.display = 'block';
        document.querySelectorAll('#paypalDetails input').forEach(i => i.setAttribute('required', 'true'));
    } else {
        document.getElementById('appleDetails').style.display = 'block';
    }
}

// Init calculation and forms
document.addEventListener('DOMContentLoaded', () => {
    calculateDollar();
    togglePaymentForm();
});
