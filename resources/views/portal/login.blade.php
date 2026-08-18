<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal - Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-150 dark:border-gray-700 p-8 text-center">
        <div class="w-16 h-16 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-2xl mx-auto mb-6 shadow-md">
            FH
        </div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Client Portal</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Access your active projects, quotes, and invoices securely.</p>
        
        <div class="bg-indigo-50 dark:bg-indigo-900/30 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800/40 text-left mb-6">
            <h3 class="text-sm font-bold text-indigo-800 dark:text-indigo-300 mb-2 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                How to log in?
            </h3>
            <p class="text-xs text-indigo-700 dark:text-indigo-400 leading-relaxed">
                We use secure, passwordless authentication. Ask your freelancer/agency to send you a <strong>Magic Link</strong>. Once you click it, you'll be instantly logged in without needing to remember a password!
            </p>
        </div>
        
    </div>
</body>
</html>
