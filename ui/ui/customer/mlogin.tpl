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
                <p class="text-sm text-gray-600">Connected IP: {$smarty.get['nux-ip']}</p>
            </div>

            <!-- Login Form with Phone Input -->
            <form name="login" action="{$_GET['link-login']}" method="post" $(if chap-id) onsubmit="return doLogin()" $(endif) class="space-y-4">
                <div id="loginError" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm"></div>
                
                <!-- Mikrotik required hidden fields -->
                <input type="hidden" name="dst" value="{$_GET['link-orig']}">
                <input type="hidden" name="username" id="username">
                <input type="hidden" name="password" id="password">
                <input type="hidden" name="popup" value="true">
                <input type="hidden" name="chap-id" value="$(chap-id)">
                <input type="hidden" name="chap-challenge" value="$(chap-challenge)">

                <!-- Single Phone Input Field -->
                <div class="mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <i class="fas fa-mobile-alt text-gray-500 text-base"></i>
                            M-Pesa Phone Number
                        </label>
                        <div class="relative">
                            <input type="tel"
                                   id="phoneInput"
                                   name="phone"
                                   pattern="[0-9]{10}"
                                   placeholder="07XX XXX XXX"
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 peer"
                                   required>
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 peer-focus:text-blue-600">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1.5 ml-1">Enter your M-Pesa registered phone number</p>
                    </div>
                </div>

                <!-- Pricing Plans -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6" id="pricingPlans" style="pointer-events: none; opacity: 0.5;">
                    <div class="package-option p-3 border-2 border-indigo-200 rounded-lg bg-indigo-50 hover:border-indigo-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="1h">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-indigo-900">Ksh 20</span>
                            </div>
                            <h3 class="text-lg font-bold text-indigo-900 text-center">1 Hour</h3>
                            <p class="text-sm font-medium text-indigo-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>

                    <div class="package-option p-3 border-2 border-purple-200 rounded-lg bg-purple-50 hover:border-purple-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="3h">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-purple-900">Ksh 30</span>
                            </div>
                            <h3 class="text-lg font-bold text-purple-900 text-center">3 Hours</h3>
                            <p class="text-sm font-medium text-purple-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>

                    <div class="package-option p-3 border-2 border-pink-200 rounded-lg bg-pink-50 hover:border-pink-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="6h">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-pink-900">Ksh 50</span>
                            </div>
                            <h3 class="text-lg font-bold text-pink-900 text-center">6 Hours</h3>
                            <p class="text-sm font-medium text-pink-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>

                    <div class="package-option p-3 border-2 border-blue-200 rounded-lg bg-blue-50 hover:border-blue-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="daily">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-blue-900">Ksh 99</span>
                            </div>
                            <h3 class="text-lg font-bold text-blue-900 text-center">Daily</h3>
                            <p class="text-sm font-medium text-blue-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>

                    <div class="package-option p-3 border-2 border-green-200 rounded-lg bg-green-50 hover:border-green-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="weekly">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-green-900">Ksh 599</span>
                            </div>
                            <h3 class="text-lg font-bold text-green-900 text-center">Weekly</h3>
                            <p class="text-sm font-medium text-green-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>

                    <div class="package-option p-3 border-2 border-purple-200 rounded-lg bg-purple-50 hover:border-purple-300 transition-colors duration-200 h-full cursor-pointer hover:shadow-md"
                         data-plan="monthly">
                        <div class="space-y-2">
                            <div class="text-center">
                                <span class="text-2xl font-black text-purple-900">Ksh 1,999</span>
                            </div>
                            <h3 class="text-lg font-bold text-purple-900 text-center">Monthly</h3>
                            <p class="text-sm font-medium text-purple-700 text-center">Unlimited High speed</p>
                        </div>
                    </div>
                </div>

                <!-- Device Info -->
                <div class="mt-6 pt-4 border-t border-gray-200 text-sm text-gray-600">
                    <div class="flex justify-between mb-2">
                        <span>MAC Address:</span>
                        <span class="font-mono">{$smarty.get['nux-mac']}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>IP Address:</span>
                        <span class="font-mono">{$smarty.get['nux-ip']}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="loading-overlay hidden">
        <div>
            <div class="loading-spinner mx-auto"></div>
            <p>Processing M-Pesa payment...</p>
            <p class="text-sm mt-2">Please check your phone for the STK push notification</p>
        </div>
    </div>

    <!-- Core libraries -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Authentication libraries -->
    <script>
        // Check if CHAP parameters exist in URL
        const urlParams = new URLSearchParams(window.location.search);
        const chapKey = urlParams.get('nux-key');
        if (chapKey) {
            const script = document.createElement('script');
            script.src = "{$app_url}/ui/ui/scripts/md5.js";
            script.type = 'text/javascript';
            document.head.appendChild(script);
        }
    </script>

    <!-- Application scripts -->
    <script src="{$app_url}/ui/ui/customer/js/login.js"></script>

    <!-- Initialize variables -->
    <script>
        const BASE_URL = '{$app_url}';
        const CHAP_ID = '$(chap-id)';
        const CHAP_CHALLENGE = '$(chap-challenge)';
    </script>
</body>
</html>
