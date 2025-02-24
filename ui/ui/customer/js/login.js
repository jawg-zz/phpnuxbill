// Login page specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phoneInput');
    const pricingPlans = document.getElementById('pricingPlans');
    
    // Phone number validation
    phoneInput.addEventListener('input', function() {
        const isValid = validatePhoneNumber();
        pricingPlans.style.pointerEvents = isValid ? 'auto' : 'none';
        pricingPlans.style.opacity = isValid ? '1' : '0.5';
    });

    function validatePhoneNumber() {
        const phone = phoneInput.value.trim();
        // Validate for Kenyan phone number format
        return /^(?:254|\+254|0)?(7[0-9]{8})$/.test(phone);
    }

    // Format phone number to 254XXXXXXXXX
    function formatPhoneNumber(phone) {
        phone = phone.replace(/[\s+-]/g, '');
        if (phone.startsWith('0')) {
            phone = '254' + phone.slice(1);
        } else if (!phone.startsWith('254')) {
            phone = '254' + phone;
        }
        return phone;
    }

    // Handle package selection
    document.querySelectorAll('.package-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            
            const phoneNumber = phoneInput.value.trim();
            if (!validatePhoneNumber()) {
                showNotification('Please enter a valid M-Pesa phone number', 'error');
                return;
            }

            // Visual feedback
            document.querySelectorAll('.package-option').forEach(el =>
                el.classList.remove('ring-2', 'ring-blue-500'));
            this.classList.add('ring-2', 'ring-blue-500');

            const planId = this.dataset.plan;
            const formattedPhone = formatPhoneNumber(phoneNumber);

            // Show loading overlay
            const loadingOverlay = document.querySelector('.loading-overlay');
            loadingOverlay.classList.remove('hidden');

            // Create hidden form for mpesa transaction
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'system/paymentgateway/mpesa.php';

            // Add necessary fields
            const fields = {
                'action': 'create_transaction',
                'plan_id': planId,
                'phone_number': formattedPhone,
                'link_login': document.getElementsByName('link-login')[0]?.value || '',
                'link_orig': document.getElementsByName('link-orig')[0]?.value || '',
                'mac': document.getElementsByName('mac')[0]?.value || '',
                'ip': document.getElementsByName('ip')[0]?.value || ''
            };

            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            // Add form to document and submit
            document.body.appendChild(form);
            form.submit();
        });
    });

    // Check if we need to start polling (after STK push)
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');
    if (orderId) {
        pollPaymentStatus(orderId);
    }

    // Poll payment status
    function pollPaymentStatus(orderId) {
        const maxAttempts = 30; // 5 minutes (10 seconds * 30)
        let attempts = 0;

        const checkStatus = () => {
            // Create hidden form for status check
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'system/paymentgateway/mpesa.php';

            const fields = {
                'action': 'get_status',
                'order_id': orderId
            };

            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        };

        const poll = () => {
            attempts++;
            if (attempts < maxAttempts) {
                checkStatus();
                setTimeout(poll, 10000); // Check every 10 seconds
            } else {
                showNotification('Payment timeout. Please try again.', 'error');
                document.querySelector('.loading-overlay').classList.add('hidden');
            }
        };

        poll();
    }

    // Notification system
    function showNotification(message, type = 'info') {
        const loginError = document.getElementById('loginError');
        if (!loginError) return;

        loginError.textContent = message;
        loginError.classList.remove('hidden');

        // Style based on type
        loginError.className = 'mb-4 p-3 rounded-lg text-sm ' + 
            (type === 'error' ? 'bg-red-100 text-red-700' :
             type === 'success' ? 'bg-green-100 text-green-700' :
             'bg-blue-100 text-blue-700');
    }
});
