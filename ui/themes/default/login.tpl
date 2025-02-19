<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spidmax WiFi - Connect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .loading-overlay {
            background: rgba(255, 255, 255, 0.9) url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234361ee"%3E%3Cpath d="M12 2A10 10 0 1 0 2 12a10 10 0 0 0 10-10zm0 3a7 7 0 1 1-14 0 7 7 0 0 1 14 0zm4 8h-8v-4h8v4z"/%3E%3C/svg%3E') center no-repeat;
            @apply fixed inset-0 hidden backdrop-blur-sm z-[1000];
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 to-indigo-900 min-h-screen p-4 flex items-center justify-center">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white/95 backdrop-blur-lg rounded-2xl shadow-xl p-6 sm:p-8 transition-all hover:shadow-2xl duration-300">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-3 mb-2">
                    <i class="fas fa-spider text-3xl text-orange-500"></i>
                    <h1 class="text-2xl font-bold text-gray-800">Spidmax WiFi</h1>
                </div>
                <p class="text-sm text-gray-600">Connected IP: {$ip}</p>
            </div>

            <!-- Phone Number Input -->
            <div class="mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-mobile-alt text-gray-500 text-base"></i>
                        Phone Number (Username)
                    </label>
                    <div class="relative">
                        <input type="tel"
                               id="phoneInput"
                               name="username"
                               pattern="[0-9]{10}"
                               placeholder="07XX XXX XXX"
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 peer"
                               required>
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 peer-focus:text-blue-600">
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5 ml-1">Enter your phone number - this will be your username</p>
                </div>
            </div>

            <!-- Pricing Plans -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6" id="pricingPlans" style="pointer-events: none; opacity: 0.5;">
                {foreach $plans as $plan}
                <div class="package-option p-3 border-2 {$plan.border_color} rounded-lg {$plan.bg_color} hover:border-{$plan.hover_border} transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                     data-plan="{$plan.id}">
                    <div class="space-y-2">
                        <div class="text-center">
                            <span class="text-2xl font-black {$plan.text_color}">Ksh {$plan.price}</span>
                        </div>
                        <h3 class="text-lg font-bold {$plan.text_color} text-center">{$plan.duration}</h3>
                        <p class="text-sm font-medium {$plan.desc_color} text-center">{$plan.description}</p>
                    </div>
                </div>
                {/foreach}
            </div>

            <!-- MikroTik Hotspot Login Form -->
            <form name="login" action="/login" method="post" class="space-y-4" onsubmit="return handleHotspotSubmit(event)">
                <div id="loginError" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm"></div>
                <input type="hidden" name="dst" value="{$redirect_url}">
                <input type="hidden" name="popup" value="true">
                <input type="hidden" name="mac" value="{$mac}">
                <input type="hidden" name="ip" value="{$ip}">

                <!-- Voucher/Trial Section -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="space-y-3">
                        {if $trial_enabled}
                        <a href="{$trial_url}" 
                           class="block text-center text-sm text-blue-600 hover:text-blue-800 w-full">
                            <i class="fas fa-gift mr-2"></i>Free Trial (30 minutes)
                        </a>
                        {/if}
                        <button type="button" 
                                onclick="document.getElementById('voucherForm').classList.toggle('hidden')" 
                                class="text-sm text-gray-600 hover:text-gray-800 w-full text-center">
                            <i class="fas fa-ticket-alt mr-2"></i>Use Voucher Code
                        </button>
                    </div>

                    <form id="voucherForm" class="hidden mt-4 flex space-x-2">
                        <input type="text"
                               name="voucher"
                               placeholder="Enter voucher code"
                               class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                               required>
                        <button type="submit"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2.5 rounded-lg transition-all duration-200 hover:scale-[1.02]">
                            Redeem
                        </button>
                    </form>
                </div>
            </form>

            <!-- Device Info -->
            <div class="mt-6 pt-4 border-t border-gray-200 text-sm text-gray-600">
                <div class="flex justify-between mb-2">
                    <span>MAC Address:</span>
                    <span class="font-mono">{$mac}</span>
                </div>
                <div class="flex justify-between">
                    <span>IP Address:</span>
                    <span class="font-mono">{$ip}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay"></div>
    <script>
        // JavaScript logic here
    </script>
</body>
</html>