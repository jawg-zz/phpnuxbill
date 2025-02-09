{include file="sections/header.tpl"}

<div class="max-w-4xl mx-auto px-4 py-8">
  <div class="mb-8">
    <h1 class="text-2xl font-semibold text-gray-900">M-Pesa Integration Settings</h1>
    <p class="mt-2 text-gray-600">Configure your M-Pesa payment gateway credentials and settings</p>
  </div>

  <form method="post" action="" class="bg-white rounded-lg shadow-sm border border-gray-200" id="mpesaForm" novalidate>
    <div class="p-6">
      <!-- Consumer Key -->
      <div class="mb-6">
        <label for="mpesa_consumer_key" class="block text-sm font-medium text-gray-700 mb-2">
          Consumer Key <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input 
            type="text" 
            id="mpesa_consumer_key" 
            name="mpesa_consumer_key" 
            value="{$config['mpesa_consumer_key']}"
            class="block w-full px-4 py-3 rounded-md border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            placeholder="Enter your consumer key"
            required
            data-error="Consumer Key is required"
          >
          <div class="hidden text-red-500 text-sm mt-1" id="mpesa_consumer_key_error"></div>
        </div>
      </div>

      <!-- Consumer Secret -->
      <div class="mb-6">
        <label for="mpesa_consumer_secret" class="block text-sm font-medium text-gray-700 mb-2">
          Consumer Secret <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input 
            type="password" 
            id="mpesa_consumer_secret" 
            name="mpesa_consumer_secret" 
            value="{$config['mpesa_consumer_secret']}"
            class="block w-full px-4 py-3 rounded-md border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            placeholder="Enter your consumer secret"
            required
            data-error="Consumer Secret is required"
          >
          <button 
            type="button"
            onclick="togglePassword('mpesa_consumer_secret')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          <div class="hidden text-red-500 text-sm mt-1" id="mpesa_consumer_secret_error"></div>
        </div>
      </div>

      <!-- Shortcode -->
      <div class="mb-6">
        <label for="mpesa_shortcode" class="block text-sm font-medium text-gray-700 mb-2">
          Shortcode <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input 
            type="text" 
            id="mpesa_shortcode" 
            name="mpesa_shortcode" 
            value="{$config['mpesa_shortcode']}"
            class="block w-full px-4 py-3 rounded-md border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            placeholder="Enter your M-Pesa shortcode"
            required
            pattern="[0-9]+"
            data-error="Shortcode is required and must contain only numbers"
          >
          <div class="hidden text-red-500 text-sm mt-1" id="mpesa_shortcode_error"></div>
        </div>
      </div>

      <!-- Passkey -->
      <div class="mb-6">
        <label for="mpesa_passkey" class="block text-sm font-medium text-gray-700 mb-2">
          Passkey <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input 
            type="password" 
            id="mpesa_passkey" 
            name="mpesa_passkey" 
            value="{$config['mpesa_passkey']}"
            class="block w-full px-4 py-3 rounded-md border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            placeholder="Enter your passkey"
            required
            data-error="Passkey is required"
          >
          <button 
            type="button"
            onclick="togglePassword('mpesa_passkey')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          <div class="hidden text-red-500 text-sm mt-1" id="mpesa_passkey_error"></div>
        </div>
      </div>
    </div>

    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
      <button 
        type="submit" 
        name="_save" 
        value="mpesa"
        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
      >
        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {Lang::T('Save Changes')}
      </button>
    </div>
  </form>
</div>

<script>
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  input.type = input.type === 'password' ? 'text' : 'password';
}

// Form validation
document.getElementById('mpesaForm').addEventListener('submit', function(event) {
  event.preventDefault();
  
  // Reset all error messages
  document.querySelectorAll('[id$="_error"]').forEach(el => {
    el.classList.add('hidden');
    el.textContent = '';
  });
  
  // Reset all input borders
  document.querySelectorAll('input').forEach(input => {
    input.classList.remove('border-red-500');
  });

  let isValid = true;
  const inputs = this.querySelectorAll('input[required]');
  
  inputs.forEach(input => {
    const errorElement = document.getElementById(`${input.id}_error`);
    
    // Check if empty
    if (!input.value.trim()) {
      isValid = false;
      input.classList.add('border-red-500');
      errorElement.textContent = input.dataset.error;
      errorElement.classList.remove('hidden');
    }
    
    // Check shortcode pattern
    if (input.id === 'mpesa_shortcode' && input.value.trim()) {
      if (!/^[0-9]+$/.test(input.value)) {
        isValid = false;
        input.classList.add('border-red-500');
        errorElement.textContent = 'Shortcode must contain only numbers';
        errorElement.classList.remove('hidden');
      }
    }
  });

  // If form is valid, submit it
  if (isValid) {
    this.submit();
  } else {
    // Scroll to the first error
    const firstError = document.querySelector('.border-red-500');
    if (firstError) {
      firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
});

// Real-time validation
document.querySelectorAll('input[required]').forEach(input => {
  input.addEventListener('input', function() {
    const errorElement = document.getElementById(`${this.id}_error`);
    
    // Clear error state
    this.classList.remove('border-red-500');
    errorElement.classList.add('hidden');
    errorElement.textContent = '';
    
    // Validate shortcode format
    if (this.id === 'mpesa_shortcode' && this.value.trim()) {
      if (!/^[0-9]+$/.test(this.value)) {
        this.classList.add('border-red-500');
        errorElement.textContent = 'Shortcode must contain only numbers';
        errorElement.classList.remove('hidden');
      }
    }
  });
});
</script>

{include file="sections/footer.tpl"}