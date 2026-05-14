// ChargePoint - Main JS

document.addEventListener('DOMContentLoaded', function() {
    initBannerSlider();
    initCountryPhoneFields();
    initPaymentPolling();
    initCopyButtons();
    initMobileMenu();
    autoHideAlerts();
});

// ===== BANNER SLIDER =====
function initBannerSlider() {
    const slides = document.querySelectorAll('.banner-slide');
    const dots = document.querySelectorAll('.banner-dot');
    if (!slides.length) return;
    let current = 0;

    function goTo(idx) {
        slides[current].classList.remove('active');
        if (dots[current]) dots[current].classList.remove('active');
        current = idx;
        slides[current].classList.add('active');
        if (dots[current]) dots[current].classList.add('active');
    }

    dots.forEach((dot, i) => dot.addEventListener('click', () => goTo(i)));

    setInterval(() => goTo((current + 1) % slides.length), 4000);
}

// ===== COUNTRY / PHONE FIELDS =====
function initCountryPhoneFields() {
    const countrySelect = document.getElementById('country_code');
    const operatorSelect = document.getElementById('operator');
    const phoneInput = document.getElementById('phone');
    const currencyField = document.getElementById('currency');

    if (!countrySelect || !operatorSelect) return;

    countrySelect.addEventListener('change', function() {
        const code = this.value;
        const option = this.options[this.selectedIndex];
        const operators = JSON.parse(option.dataset.operators || '[]');
        const currency = option.dataset.currency || 'XOF';
        const dialCode = option.dataset.dial || '';

        operatorSelect.innerHTML = '<option value="">Sélectionner l\'opérateur</option>';
        operators.forEach(op => {
            const opt = document.createElement('option');
            opt.value = op;
            opt.textContent = op;
            operatorSelect.appendChild(opt);
        });
        if (currencyField) currencyField.value = currency;
        if (phoneInput && dialCode) phoneInput.placeholder = dialCode + 'XXXXXXXX';
    });
}

// ===== PAYMENT POLLING =====
function initPaymentPolling() {
    const transactionId = document.getElementById('transaction_id')?.value;
    if (!transactionId) return;

    let pollInterval;
    let timeoutTimer;
    let seconds = 8 * 60;

    const countdownEl = document.getElementById('countdown');
    const statusEl = document.getElementById('payment_status_text');
    const spinnerEl = document.getElementById('payment_spinner');

    function updateCountdown() {
        if (!countdownEl) return;
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        countdownEl.textContent = m + ':' + s;
        if (seconds > 0) seconds--;
    }

    const countdownInterval = setInterval(updateCountdown, 1000);

    async function poll() {
        try {
            const resp = await fetch('/api/payment_status.php?id=' + encodeURIComponent(transactionId));
            const data = await resp.json();

            if (data.status === 'success') {
                clearAll();
                if (statusEl) {
                    statusEl.textContent = '✅ Paiement validé !';
                    statusEl.className = 'payment-status-text status-success';
                }
                if (spinnerEl) spinnerEl.style.display = 'none';
                setTimeout(() => { window.location.href = '/dashboard.php?deposit=success'; }, 1500);
            } else if (data.status === 'failed') {
                clearAll();
                if (statusEl) {
                    statusEl.textContent = '❌ Paiement échoué.';
                    statusEl.className = 'payment-status-text status-failed';
                }
                if (spinnerEl) spinnerEl.style.display = 'none';
                showRetry();
            }

            // Handle OTP required
            if (data.otp_required) {
                handleOTPRequired(data);
            }
        } catch(e) {}
    }

    pollInterval = setInterval(poll, 3000);

    timeoutTimer = setTimeout(() => {
        clearAll();
        if (statusEl) {
            statusEl.textContent = '⏰ Délai expiré. Transaction non confirmée.';
            statusEl.className = 'payment-status-text status-failed';
        }
        if (spinnerEl) spinnerEl.style.display = 'none';
        showRetry();
    }, 8 * 60 * 1000);

    function clearAll() {
        clearInterval(pollInterval);
        clearInterval(countdownInterval);
        clearTimeout(timeoutTimer);
    }

    function showRetry() {
        const retryBtn = document.getElementById('retry_btn');
        if (retryBtn) retryBtn.style.display = 'block';
    }
}

function handleOTPRequired(data) {
    const otpSection = document.getElementById('otp_section');
    if (!otpSection) return;
    otpSection.style.display = 'block';
    const ussdCode = data.ussd_code;
    const ussdInfo = document.getElementById('ussd_code_info');
    if (ussdCode && ussdInfo) {
        ussdInfo.innerHTML = `<strong>Composez le code USSD :</strong> <code>${ussdCode}</code><br>puis entrez le code OTP reçu.`;
    } else if (ussdInfo) {
        ussdInfo.innerHTML = `<strong>Un SMS avec votre code OTP a été envoyé.</strong><br>Entrez le code reçu ci-dessous.`;
    }
}

// ===== COPY BUTTONS =====
function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.dataset.copy || document.getElementById(this.dataset.copyTarget)?.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                const orig = this.textContent;
                this.textContent = '✓ Copié !';
                setTimeout(() => { this.textContent = orig; }, 2000);
            });
        });
    });
}

// ===== MOBILE MENU =====
function initMobileMenu() {
    const burger = document.getElementById('burger_menu');
    const navLinks = document.getElementById('nav_links');
    if (burger && navLinks) {
        burger.addEventListener('click', () => {
            navLinks.classList.toggle('show-mobile');
        });
    }
}

// ===== AUTO HIDE ALERTS =====
function autoHideAlerts() {
    document.querySelectorAll('.alert-auto').forEach(el => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });
}

// ===== DEPOSIT FORM =====
function submitDepositForm(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('[type=submit]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Traitement en cours...';

    fetch('/api/initiate_payment.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.wave_url) {
                showWavePayment(data.wave_url, data.transaction_id);
            } else if (data.otp_required) {
                showOTPScreen(data);
            } else {
                showPaymentWaiting(data.transaction_id, data.amount, data.phone, data.operator);
            }
        } else {
            showFormError(data.message || 'Erreur lors de l\'initiation du paiement.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Continuer';
        }
    })
    .catch(() => {
        showFormError('Erreur réseau. Veuillez réessayer.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Continuer';
    });
}

function showPaymentWaiting(transactionId, amount, phone, operator) {
    const overlay = document.getElementById('payment_overlay');
    if (!overlay) return;
    document.getElementById('transaction_id').value = transactionId;
    document.getElementById('pay_amount').textContent = amount;
    document.getElementById('pay_phone').textContent = phone;
    document.getElementById('pay_operator').textContent = operator;
    overlay.style.display = 'flex';
    initPaymentPolling();
}

function showWavePayment(waveUrl, transactionId) {
    const overlay = document.getElementById('wave_overlay');
    if (overlay) {
        document.getElementById('wave_pay_btn').href = waveUrl;
        document.getElementById('transaction_id').value = transactionId;
        overlay.style.display = 'flex';
        initPaymentPolling();
    } else {
        window.open(waveUrl, '_blank');
    }
}

function showOTPScreen(data) {
    const otpOverlay = document.getElementById('otp_overlay');
    if (otpOverlay) {
        document.getElementById('otp_transaction_data').value = JSON.stringify(data);
        if (data.ussd_code) {
            document.getElementById('otp_ussd_info').textContent = `Composez ${data.ussd_code} pour recevoir votre OTP`;
        } else {
            document.getElementById('otp_sms_info').style.display = 'block';
        }
        otpOverlay.style.display = 'flex';
    }
}

function showFormError(msg) {
    let alertEl = document.getElementById('form_error');
    if (!alertEl) {
        alertEl = document.createElement('div');
        alertEl.id = 'form_error';
        alertEl.className = 'alert alert-danger';
        document.querySelector('.deposit-form')?.prepend(alertEl);
    }
    alertEl.textContent = msg;
    alertEl.style.display = 'block';
}

// ===== OTP SUBMIT =====
function submitOTP(event) {
    event.preventDefault();
    const form = event.target;
    fetch('/api/submit_otp.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('otp_overlay').style.display = 'none';
            showPaymentWaiting(data.transaction_id, data.amount, data.phone, data.operator);
        } else {
            document.getElementById('otp_error').textContent = data.message;
            document.getElementById('otp_error').style.display = 'block';
        }
    });
}

// ===== SHARE REFERRAL =====
function shareReferral() {
    const link = document.getElementById('referral_link')?.value;
    if (navigator.share) {
        navigator.share({ title: 'ChargePoint - Investissement', text: 'Rejoignez ChargePoint et commencez à investir !', url: link });
    } else {
        navigator.clipboard.writeText(link);
        alert('Lien copié !');
    }
}

// ===== CONFIRM DIALOGS =====
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}
