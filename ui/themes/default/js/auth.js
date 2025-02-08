// M-Pesa Payment Authentication
let paymentCheckInterval;

async function doLogin() {
    const loader = document.querySelector('.loading-overlay');
    const errorDiv = document.getElementById('loginError');
    const phone = document.login.phone.value.trim();
    const plan = document.getElementById('selectedPlan').value;
    
    loader.style.display = 'flex';
    errorDiv.classList.add('hidden');

    // Validate inputs
    if (!phone || !plan) {
        showError('Please provide phone number and select a package');
        return false;
    }

    if (!phone.match(/^07[0-9]{8}$/)) {
        showError('Invalid phone number format. Use 07XXXXXXXX');
        return false;
    }

    try {
        // Initiate STK Push
        const response = await initiateSTKPush(phone, plan);
        
        if (response.CheckoutRequestID) {
            // Start polling for payment confirmation
            paymentCheckInterval = setInterval(async () => {
                const paid = await checkPaymentStatus(response.CheckoutRequestID);
                if (paid) {
                    clearInterval(paymentCheckInterval);
                    document.sendin.submit();
                }
            }, 5000); // Check every 5 seconds

            // Timeout after 2 minutes
            setTimeout(() => {
                clearInterval(paymentCheckInterval);
                showError('Payment request timed out. Please try again.');
            }, 120000);
        }
    } catch (error) {
        showError(`Payment failed: ${error.message}`);
    }
    return false;
}

async function initiateSTKPush(phone, plan) {
    const response = await fetch('/api/mpesa/stkpush', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            phone: phone,
            plan: plan
        })
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Payment initiation failed');
    }
    return response.json();
}

async function checkPaymentStatus(checkoutRequestId) {
    const response = await fetch(`/api/mpesa/checkstatus/${checkoutRequestId}`);
    if (!response.ok) throw new Error('Payment status check failed');
    return response.json();
}

function showError(message) {
    clearInterval(paymentCheckInterval);
    const errorDiv = document.getElementById('loginError');
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    document.querySelector('.loading-overlay').style.display = 'none';
}

// Initialize package selection
document.querySelectorAll('.package-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.package-option').forEach(el =>
            el.classList.remove('ring-2', 'ring-blue-500'));
        this.classList.add('ring-2', 'ring-blue-500');
        document.getElementById('selectedPlan').value = this.dataset.plan;
    });
});