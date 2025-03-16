/**
 * Enhanced M-PESA payment status checker
 */
class MpesaPaymentChecker {
    constructor(options) {
        this.transactionId = options.transactionId;
        this.checkUrl = options.checkUrl || appUrl + '/callback/check-payment-status';
        this.redirectUrl = options.redirectUrl || null;
        this.checkInterval = options.checkInterval || 5000; // 5 seconds by default
        this.maxAttempts = options.maxAttempts || 60; // 60 attempts (5 minutes) by default
        this.attemptCount = 0;
        this.checkTimer = null;
        this.timeoutDate = null;
        this.timerInterval = null;
        
        // UI Elements
        this.statusContainer = options.statusContainer || '.mpesa-payment-status';
        this.statusBadge = options.statusBadge || '#status-badge';
        this.paymentMessage = options.paymentMessage || '#payment-message';
        this.paymentTimer = options.paymentTimer || '#payment-timer';
        this.paymentSpinner = options.paymentSpinner || '#payment-spinner';
        this.successActions = options.successActions || '#success-actions';
        this.failureActions = options.failureActions || '#failure-actions';
        this.failureMessage = options.failureMessage || '#failure-message';
        
        // Step elements
        this.stepInitiated = options.stepInitiated || '#step-initiated';
        this.stepApproval = options.stepApproval || '#step-approval';
        this.stepProcessing = options.stepProcessing || '#step-processing';
        this.stepCompleted = options.stepCompleted || '#step-completed';
        
        // Button elements
        this.checkStatusBtn = options.checkStatusBtn || '#check-status-btn';
        this.retryPaymentBtn = options.retryPaymentBtn || '#retry-payment-btn';
        this.otherOptionsBtn = options.otherOptionsBtn || '#other-options-btn';
        
        // Initialize
        this.initialize();
    }

    /**
     * Initialize the payment checker
     */
    initialize() {
        // Set expiry time 5 minutes from now for the timer display
        this.timeoutDate = new Date(new Date().getTime() + 5 * 60 * 1000);
        
        // Start the timer display
        this.startTimer();
        
        // Start checking for payment status
        this.start();
        
        // Attach event handler for manual check
        $(this.checkStatusBtn).on('click', (e) => {
            e.preventDefault();
            this.checkStatus();
        });
    }
    
    /**
     * Start polling for payment status
     */
    start() {
        // Clear any existing timer
        this.stop();
        
        // Start with initial check
        this.checkStatus();
        
        // Set up polling interval
        this.checkTimer = setInterval(() => {
            this.checkStatus();
        }, this.checkInterval);
    }
    
    /**
     * Stop polling
     */
    stop() {
        if (this.checkTimer) {
            clearInterval(this.checkTimer);
            this.checkTimer = null;
        }
    }
    
    /**
     * Start countdown timer display
     */
    startTimer() {
        // Update immediately
        this.updateTimerDisplay();
        
        // Update every second
        this.timerInterval = setInterval(() => {
            this.updateTimerDisplay();
        }, 1000);
    }
    
    /**
     * Stop timer display
     */
    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }
    
    /**
     * Update timer display
     */
    updateTimerDisplay() {
        const now = new Date();
        const timeLeft = this.timeoutDate - now;
        
        if (timeLeft <= 0) {
            $(this.paymentTimer).text(Lang.T('Time expired'));
            this.stopTimer();
            return;
        }
        
        const minutes = Math.floor(timeLeft / (60 * 1000));
        const seconds = Math.floor((timeLeft % (60 * 1000)) / 1000);
        
        $(this.paymentTimer).text(Lang.T('Expires in') + ': ' + 
            minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
    }

    /**
     * Check payment status from server
     */
    checkStatus() {
        this.attemptCount++;
        
        // Stop checking if we've reached max attempts
        if (this.attemptCount > this.maxAttempts) {
            this.stop();
            this.stopTimer();
            this.updateUIForStatus('expired');
            return;
        }

        // Update UI to show checking
        this.setStepActive('processing');
        
        // Make AJAX request to check status
        $.ajax({
            url: this.checkUrl,
            method: 'GET',
            data: { id: this.transactionId },
            dataType: 'json',
            success: (response) => {
                if (!response.success) {
                    this.updateUIForStatus('error', response.message || 'Error checking payment status');
                    return;
                }

                // Handle successful check
                const status = response.status;
                const message = response.message;

                this.updateUIForStatus(status, message);
                
                // If payment is completed or failed, stop checking
                if (status === 'paid' || status === 'expired' || status === 'failed') {
                    this.stop();
                    this.stopTimer();
                    
                    // If payment is successful and we have a redirect URL, redirect after a delay
                    if (status === 'paid' && this.redirectUrl) {
                        setTimeout(() => {
                            window.location.href = this.redirectUrl;
                        }, 3000);
                    }
                }
            },
            error: () => {
                this.updateUIForStatus('error', 'Error connecting to server. Please try again.');
            }
        });
    }
    
    /**
     * Set the active step in the progress indicator
     */
    setStepActive(step) {
        // Reset all steps
        $(this.stepInitiated + ', ' + this.stepApproval + ', ' + this.stepProcessing + ', ' + this.stepCompleted)
            .removeClass('active completed failed');
        
        // Mark steps as completed based on current step
        switch(step) {
            case 'completed':
                $(this.stepInitiated + ', ' + this.stepApproval + ', ' + this.stepProcessing)
                    .addClass('completed');
                $(this.stepCompleted).addClass('completed');
                break;
            case 'processing':
                $(this.stepInitiated + ', ' + this.stepApproval).addClass('completed');
                $(this.stepProcessing).addClass('active');
                break;
            case 'approval':
                $(this.stepInitiated).addClass('completed');
                $(this.stepApproval).addClass('active');
                break;
            case 'failed':
            case 'expired':
                $(this.stepInitiated).addClass('completed');
                $(this.stepApproval).addClass('failed');
                break;
            default:
                $(this.stepInitiated).addClass('active');
        }
    }

    /**
     * Update the UI based on payment status
     */
    updateUIForStatus(status, message = null) {
        // Update container class
        $(this.statusContainer).removeClass('mpesa-status-checking mpesa-status-pending mpesa-status-paid mpesa-status-failed mpesa-status-expired mpesa-status-error');
        $(this.statusContainer).addClass('mpesa-status-' + status);
        
        // Update status badge
        let badgeClass = 'badge-primary';
        let badgeText = Lang.T('Processing');
        
        switch(status) {
            case 'paid':
                badgeClass = 'badge-success';
                badgeText = Lang.T('Paid');
                break;
            case 'pending':
                badgeClass = 'badge-warning';
                badgeText = Lang.T('Pending');
                break;
            case 'expired':
                badgeClass = 'badge-danger';
                badgeText = Lang.T('Expired');
                break;
            case 'failed':
                badgeClass = 'badge-danger';
                badgeText = Lang.T('Failed');
                break;
            case 'error':
                badgeClass = 'badge-danger';
                badgeText = Lang.T('Error');
                break;
        }
        
        $(this.statusBadge).html('<span class="badge ' + badgeClass + '">' + badgeText + '</span>');
        
        // Update message
        if (message) {
            $(this.paymentMessage).text(message);
        } else {
            // Default messages based on status
            switch(status) {
                case 'paid':
                    $(this.paymentMessage).text(Lang.T('Payment successful! Your package is being activated.'));
                    break;
                case 'pending':
                    $(this.paymentMessage).text(Lang.T('Waiting for your payment approval'));
                    break;
                case 'expired':
                    $(this.paymentMessage).text(Lang.T('Payment time expired. Please try again.'));
                    break;
                case 'failed':
                    $(this.paymentMessage).text(Lang.T('Payment failed. Please try again.'));
                    break;
                case 'error':
                    $(this.paymentMessage).text(Lang.T('Error checking payment status.'));
                    break;
                default:
                    $(this.paymentMessage).text(Lang.T('Checking payment status...'));
            }
        }
        
        // Update step indicator
        switch(status) {
            case 'paid':
                this.setStepActive('completed');
                break;
            case 'pending':
                this.setStepActive('approval');
                break;
            case 'processing':
                this.setStepActive('processing');
                break;
            case 'expired':
            case 'failed':
                this.setStepActive('failed');
                break;
            default:
                this.setStepActive('approval');
        }
        
        // Show/hide spinner
        if (status === 'paid' || status === 'expired' || status === 'failed') {
            $(this.paymentSpinner).hide();
        } else {
            $(this.paymentSpinner).show();
        }
        
        // Show/hide success/failure actions
        if (status === 'paid') {
            $(this.successActions).fadeIn();
            $(this.statusContainer).fadeOut(1000);
        } else if (status === 'expired' || status === 'failed') {
            $(this.failureActions).fadeIn();
            if (this.failureMessage) {
                $(this.failureMessage).text(status === 'expired' ? 
                    Lang.T('Payment Time Expired') : Lang.T('Payment Failed'));
            }
        } else {
            $(this.successActions).hide();
            $(this.failureActions).hide();
        }
    }
} 