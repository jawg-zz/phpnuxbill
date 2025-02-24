// Login page specific JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Get the destination URL from query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const destinationUrl = urlParams.get('dst') || `${BASE_URL}/home`;

    const phoneInput = document.getElementById('phoneInput');
    const pricingPlans = document.getElementById('pricingPlans');
    const loginForm = document.querySelector('form[name="login"]');

    // Improved phone number validation
    function validatePhoneNumber() {
        let phone = phoneInput.value.replace(/\D/g, '');
        
        // Format to 254XXXXXXXXX
        if (phone.startsWith('0')) {
            phone = '254' + phone.substring(1);
        } else if (phone.startsWith('7')) {
            phone = '254' + phone;
        }
        
        // Strict validation for Kenyan numbers
        const isValid = /^254[17]\d{8}$/.test(phone);
        
        pricingPlans.style.pointerEvents = isValid ? 'auto' : 'none';
        pricingPlans.style.opacity = isValid ? '1' : '0.5';
        
        return isValid ? phone : null;
    }

    // Handle package selection and payment initiation
    document.querySelectorAll('.package-option').forEach(option => {
        option.addEventListener('click', async function() {
            const phoneNumber = validatePhoneNumber();
            if (!phoneNumber) {
                showNotification('Please enter a valid M-Pesa phone number', 'error');
                return;
            }

            // Visual feedback
            document.querySelectorAll('.package-option').forEach(el =>
                el.classList.remove('ring-2', 'ring-blue-500'));
            this.classList.add('ring-2', 'ring-blue-500');

            const planId = this.dataset.plan;
            
            // Show loading overlay
            document.querySelector('.loading-overlay').classList.remove('hidden');

            try {
                const response = await fetch(`${BASE_URL}/order/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        plan_id: planId,
                        phone_number: phoneNumber,
                        payment_gateway: 'mpesa',
                        dst: destinationUrl
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Payment initiation failed');
                }

                if (data.success) {
                    showNotification('Please check your phone for the M-Pesa prompt', 'info');
                    pollPaymentStatus(data.order_id);
                } else {
                    throw new Error(data.message || 'Failed to initiate payment');
                }
            } catch (error) {
                showNotification(error.message, 'error');
                console.error('Payment initiation error:', error);
            } finally {
                document.querySelector('.loading-overlay').classList.add('hidden');
            }
        });
    });

    // Enhanced payment status polling
    function pollPaymentStatus(orderId) {
        let attempts = 0;
        const maxAttempts = 20; // 1 minute (3s * 20)
        const pollInterval = setInterval(async () => {
            attempts++;
            
            try {
                const response = await fetch(`${BASE_URL}/order/status/${orderId}`);
                const data = await response.json();

                switch(data.status) {
                    case 2: // PAID_STATUS
                        clearInterval(pollInterval);
                        showNotification('Payment successful! Connecting...', 'success');
                        
                        // Set credentials for hotspot login
                        document.getElementById('username').value = data.username;
                        document.getElementById('password').value = data.voucher_code;
                        
                        // Handle hotspot login
                        if (CHAP_ID && CHAP_CHALLENGE) {
                            await performHotspotLogin(
                                document.querySelector('form[name="login"]').action,
                                data.username,
                                CHAP_ID,
                                CHAP_CHALLENGE
                            );
                        } else {
                            window.location.href = destinationUrl;
                        }
                        break;
                        
                    case 3: // FAILED_STATUS
                        clearInterval(pollInterval);
                        showNotification('Payment failed. Please try again.', 'error');
                        break;
                        
                    case 4: // CANCELLED_STATUS
                        clearInterval(pollInterval);
                        showNotification('Payment was cancelled. Please try again.', 'warning');
                        break;
                }
            } catch (error) {
                console.error('Payment status check error:', error);
            }

            if (attempts >= maxAttempts) {
                clearInterval(pollInterval);
                showNotification('Payment timeout. Please check your M-Pesa messages or try again.', 'warning');
            }
        }, 3000);
    }

    async function performHotspotLogin(loginUrl, username, chapId, chapChallenge) {
        try {
            // Generate CHAP password using MD5
            const password = document.getElementById('password').value;
            const chapPassword = hexMD5(chapId + password + chapChallenge);
            
            // Set the username field
            document.getElementById('username').value = username;
            
            // Submit the form instead of using fetch
            const loginForm = document.querySelector('form[name="login"]');
            if (loginForm) {
                loginForm.submit();
            } else {
                throw new Error('Login form not found');
            }
        } catch (error) {
            console.error('Hotspot login error:', error);
            showNotification('Login failed. Please try again.', 'error');
        }
    }

    // Improved notification system
    function showNotification(message, type) {
        const loginError = document.getElementById('loginError');
        if (!loginError) return;

        loginError.textContent = message;
        loginError.classList.remove('hidden', 'bg-red-100', 'bg-green-100', 'bg-blue-100', 'bg-yellow-100',
            'text-red-700', 'text-green-700', 'text-blue-700', 'text-yellow-700');
        
        const styles = {
            error: ['bg-red-100', 'text-red-700'],
            success: ['bg-green-100', 'text-green-700'],
            info: ['bg-blue-100', 'text-blue-700'],
            warning: ['bg-yellow-100', 'text-yellow-700']
        };

        loginError.classList.add('mb-4', 'p-3', 'rounded-lg', 'text-sm', ...styles[type]);
    }

    // Phone number input formatting
    phoneInput.addEventListener('input', (e) => {
        let phone = e.target.value.replace(/\D/g, '');
        
        if (phone.startsWith('0')) {
            phone = '254' + phone.substring(1);
        } else if (phone.startsWith('7')) {
            phone = '254' + phone;
        }
        
        e.target.value = phone;
        validatePhoneNumber();
    });
});
