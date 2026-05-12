<?php
session_start();
require_once '../Main/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];


// استعلام التوكنز مع LEFT JOIN
$query = "SELECT u.full_name AS User_name, COALESCE(w.token_balance, 0) AS total_tokens, COALESCE(w.lifetime_earned, 0) AS creator_earnings, u.level AS current_Lvl FROM users u LEFT JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    die("User not found.");
}

$db_tokens = $row['total_tokens'];
$creator_earnings = $row['creator_earnings'];
$db_lvl = $row['current_Lvl'];

?>

<!DOCTYPE html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tokens Management | Skill-Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css">
    <link rel="stylesheet" href="tokens.css">
</head>

<body>

    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';">
            Skill-Step
        </a>
        <div style="display:flex; gap: 15px;">
            <a href="../profile/profile.php" class="btn-action"
                style="width:auto; padding: 10px 20px; background: rgba(255,255,255,0.05);">
                <i class="fa-solid fa-user"></i> My Account
            </a>
            <a href="../Main/index.php" class="btn-action" style="width:auto; padding: 10px 20px;">
                <i class="fa-solid fa-arrow-right"></i> Back to Platform
            </a>
        </div>
    </nav>

    <div class="tokens-dashboard">

        <header class="tokens-header">
            <div>
                <h1><i class="fa-solid fa-coins" style="color:var(--accent-gold);"></i> Tokens Wallet</h1>
                <p>Manage and trade your gaming balance and creator earnings.</p>
            </div>
            <div class="current-balance-card" id="currentBalanceDisplay" data-tokens="<?php echo $db_tokens; ?>">
                <h3>Available Balance</h3>
                <div class="balance-amount">
                    <span id="tokenAmountText"><?php echo $db_tokens; ?></span> <i class="fa-solid fa-coins"></i>
                </div>
            </div>
        </header>

        <section class="calculator-section glass-card">
            <h2><i class="fa-solid fa-calculator"></i> Instant Calculator</h2>
            <p>1 Token = $0.02 USD</p>
            <div class="calc-flex">
                <div class="calc-input">
                    <label>Number of Tokens:</label>
                    <input type="number" id="calcTokenInput" min="1" value="100" oninput="calculateDollar()">
                </div>
                <div class="calc-icon">
                    <i class="fa-solid fa-right-left"></i>
                </div>
                <div class="calc-input">
                    <label>Value in USD ($):</label>
                    <input type="number" id="calcDollarInput" class="calc-result-input" min="0.02" step="0.01"
                        value="2.00" oninput="calculateToken()">
                </div>
            </div>
        </section>

        <div class="action-grid">

            <!-- Buy Tokens -->
            <section class="action-card glass-card">
                <h2><i class="fa-solid fa-cart-shopping"></i> Top Up Balance</h2>
                <p>Get more tokens to continue your challenges.</p>
                <form onsubmit="fakeSubmit(event, 'buy')">
                    <div class="form-group">
                        <label>Select Package:</label>
                        <select id="buyPackage" onchange="updateBuyPrice()">
                            <option value="100" data-price="2.50">100 Tokens - $2.50</option>
                            <option value="500" data-price="11.00">500 Tokens - $11.00</option>
                            <option value="1000" data-price="22.00">1000 Tokens - $22.00</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Method:</label>
                        <select id="paymentMethod" onchange="togglePaymentForm()">
                            <option value="visa">Credit Card (Visa / Mastercard)</option>
                            <option value="paypal">PayPal</option>
                            <option value="apple">Apple Pay</option>
                        </select>
                    </div>

                    <!-- Dynamic Payment UI injects here -->
                    <div id="visaDetails" class="payment-details-box">
                        <input type="text" placeholder="Card Number (0000 0000 0000 0000)" required>
                        <div style="display:flex; gap:10px; margin-top:10px;">
                            <input type="text" placeholder="Expiry Date (MM/YY)" required style="flex:1;">
                            <input type="text" placeholder="CVV" required style="flex:1;">
                        </div>
                        <input type="text" placeholder="Name on Card" style="margin-top:10px;" required>
                    </div>

                    <div id="paypalDetails" class="payment-details-box" style="display:none;">
                        <input type="email" placeholder="PayPal Email Address" required>
                    </div>

                    <div id="appleDetails" class="payment-details-box" style="display:none;">
                        <p style="color:var(--text-muted); font-size:0.9rem; text-align:center; padding:10px;">You will be redirected to the secure Apple Pay interface when proceeding.</p>
                    </div>
                    <button type="submit" class="btn-action buy-btn">
                        Buy for $<span id="buyPriceDisplay">2.50</span>
                    </button>
                    <p style="font-size:0.8rem; margin-top:10px; color:var(--text-muted);">* This is a simulator only. No real money will be deducted.</p>
                </form>
            </section>

            <!-- Exchange Tokens -->
            <section class="action-card glass-card" style="position:relative; overflow:hidden;">

                <?php if ($db_lvl < 20): ?>
                    <div class="locked-overlay">
                        <i class="fa-solid fa-lock"></i>
                        <h3>Locked Feature</h3>
                        <p>You must reach level 20 to unlock your cashout feature.</p>
                    </div>
                <?php endif; ?>

                <h2><i class="fa-brands fa-paypal"></i> Cashout Earnings</h2>
                <p>Convert your tokens to cash (minimum 500 tokens).</p>
                <form onsubmit="fakeSubmit(event, 'exchange')" <?php if ($db_lvl < 20)
                    echo 'style="opacity:0.3; pointer-events:none;"'; ?>>
                    <div class="form-group">
                        <label>Tokens to Cashout:</label>
                        <input type="number" id="exchangeAmount" min="500" max="<?php echo $db_tokens; ?>" value="500"
                            oninput="updateExchangeWarning(<?php echo $db_tokens; ?>)">
                        <small id="exchangeWarning" style="color:#ef4444; display:none;">Insufficient balance.</small>
                    </div>
                    <div class="form-group">
                        <label>PayPal Account (Email):</label>
                        <input type="email" placeholder="example@paypal.com"  required>
                    </div>
                    <button type="submit" class="btn-action exchange-btn">
                        Confirm Cashout
                    </button>
                    <p style="font-size:0.8rem; margin-top:10px; color:var(--text-muted);">* The request will be reviewed and the amount deposited within 3 working days.</p>
                </form>
            </section>

        </div>
    </div>

    <!-- Hidden toast -->
    <div id="actionToast" class="toast"></div>

    <script src="tokens.js"></script>
</body>

</html>