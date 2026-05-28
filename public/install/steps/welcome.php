<div class="text-center py-8">
    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome to DTR System</h2>
    <p class="text-gray-600 mb-6 max-w-md mx-auto">
        This wizard will guide you through the installation process. 
        Make sure XAMPP is running with Apache and MySQL services active.
    </p>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left max-w-md mx-auto mb-6">
        <p class="text-sm font-semibold text-blue-800 mb-2">Before you begin, ensure:</p>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>✓ XAMPP is installed and running</li>
            <li>✓ Apache and MySQL services are started</li>
            <li>✓ PHP 8.2 or higher is available</li>
            <li>✓ Composer dependencies are installed (<code class="bg-blue-100 px-1 rounded">composer install</code>)</li>
            <li>✓ Frontend is built (<code class="bg-blue-100 px-1 rounded">npm run build</code>)</li>
        </ul>
    </div>

    <a href="?step=requirements" 
       class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
        Start Installation
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>
