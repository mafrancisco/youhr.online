<div class="text-center py-8">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Installation Complete!</h2>
    <p class="text-gray-600 mb-6 max-w-md mx-auto">
        Your DTR System has been successfully installed and configured. 
        You can now log in with your administrator account.
    </p>

    <div class="bg-green-50 border border-green-200 rounded-lg p-4 max-w-sm mx-auto mb-6 text-left">
        <p class="text-sm font-semibold text-green-800 mb-2">What's next:</p>
        <ul class="text-sm text-green-700 space-y-1">
            <li>1. Log in with your admin credentials</li>
            <li>2. Configure system settings (logo, signatory)</li>
            <li>3. Add employee records</li>
            <li>4. Set up schedules</li>
            <li>5. Import attendance data</li>
        </ul>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 max-w-sm mx-auto mb-6">
        <p class="text-xs text-amber-800">
            <strong>Security:</strong> The installer is now locked. To re-run it, delete 
            <code class="bg-amber-100 px-1 rounded">storage/installed.lock</code>
        </p>
    </div>

    <a href="/" 
       class="inline-flex items-center gap-2 px-8 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
        Go to Application →
    </a>
</div>

<script>
// Clear installer session data
sessionStorage.clear();
</script>
