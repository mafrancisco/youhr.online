<?php

namespace App\Http\Controllers\SaaS;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\User;
use App\Services\SaaS\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class CompanyOnboardingController extends Controller
{
    public function __construct(
        private TenantManager $tenants,
        private SocialiteFactory $socialite,
    ) {}

    // ─── Pages ───────────────────────────────────────────────────────────────

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function showCompanyRegistration(): Response
    {
        return Inertia::render('SaaS/RegisterCompany');
    }

    // ─── Registration Flow ───────────────────────────────────────────────────

    public function startCompanyRegistration(Request $request)
    {
        $data = $request->validate([
            'company_name'  => ['required', 'string', 'max:150'],
            'company_email' => ['required', 'email', 'max:191'],
        ]);

        // Check if company with this email already exists
        if (Company::where('owner_google_email', $data['company_email'])->exists()) {
            return back()->withErrors(['company_email' => 'A company with this email already exists. Please sign in instead.']);
        }

        $request->session()->put('saas.registration', [
            'company_name'  => $data['company_name'],
            'company_email' => $data['company_email'],
        ]);
        $request->session()->forget('saas.login_email');

        // Redirect directly to Google (not through another route)
        return $this->redirectToGoogle();
    }

    // ─── Login with Username & Password ─────────────────────────────────────

    public function loginWithCredentials(Request $request)
    {
        $request->validate([
            'company_code' => ['required', 'string'],
            'username'     => ['required', 'string'],
            'password'     => ['required', 'string'],
        ]);

        // Find company by slug (company code)
        $company = Company::where('slug', Str::lower($request->company_code))
            ->where('status', 'active')
            ->first();

        if (!$company) {
            return back()->withErrors(['company_code' => 'Company not found. Check your company code.']);
        }

        // Check license
        if (!$company->hasActiveLicense()) {
            return back()->withErrors(['company_code' => 'This company has no active license. Contact your administrator.']);
        }

        // Switch to tenant database
        $this->tenants->switchToCompany($company);

        // Find user in tenant database
        $user = User::on($this->tenants->connectionName())
            ->where('username', $request->username)
            ->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['username' => 'Invalid username or password.']);
        }

        // Login
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put($this->tenants->companySessionKey(), $company->id);

        return redirect()->route('dashboard');
    }

    // ─── Login Flow (Google) ─────────────────────────────────────────────────

    public function startCompanyLogin(Request $request)
    {
        $data = $request->validate([
            'company_email' => ['required', 'email', 'max:191'],
        ]);

        $email = Str::lower($data['company_email']);

        // Find company by owner email or by any user with that email
        $company = Company::where('owner_google_email', $email)->where('status', 'active')->first();

        if (!$company) {
            // Try finding a company where any user has this email
            // (We can't query tenant DBs here, so match on owner email only)
            return back()->withErrors(['company_email' => 'No company found with this email. Please register first.']);
        }

        $request->session()->put('saas.login_email', $email);
        $request->session()->put('saas.login_company_id', $company->id);
        $request->session()->forget('saas.registration');

        // Redirect directly to Google
        return $this->redirectToGoogle();
    }

    // ─── Google OAuth ────────────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return redirect()->route('register.company')->withErrors([
                'company_email' => 'Google OAuth is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env.',
            ]);
        }

        $url = $this->socialite->driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect()
            ->getTargetUrl();

        return Inertia::location($url);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = $this->socialite->driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }

        // Check if this is a registration flow
        $registration = $request->session()->get('saas.registration');
        if ($registration) {
            return $this->completeRegistrationFlow($request, $googleUser, $registration);
        }

        // Login flow
        return $this->completeLoginFlow($request, $googleUser);
    }

    // ─── Complete Registration ───────────────────────────────────────────────

    private function completeRegistrationFlow(Request $request, $googleUser, array $registration)
    {
        $companyEmail = $registration['company_email'];

        // Verify the Google account matches the registered email
        if (Str::lower($googleUser->email) !== Str::lower($companyEmail)) {
            return redirect()->route('register.company')->withErrors([
                'company_email' => "Google account ({$googleUser->email}) does not match the company email ({$companyEmail}). Please use the same email.",
            ]);
        }

        // Generate slug from company name
        $slug = Str::slug($registration['company_name']);
        $baseSlug = $slug;
        $counter = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $database = config('saas.tenant_database_prefix', 'tenant_') . $slug;

        // Create company record
        $company = Company::create([
            'name'               => $registration['company_name'],
            'slug'               => $slug,
            'database'           => $database,
            'owner_google_email' => $googleUser->email,
            'status'             => 'active',
        ]);

        // Provision tenant database and create admin user
        $this->tenants->provisionTenant($company, $googleUser->name, $googleUser->email, $googleUser->id);

        // Switch to tenant and log in
        $this->tenants->switchToCompany($company);
        $user = User::on($this->tenants->connectionName())
            ->where('google_email', $googleUser->email)
            ->firstOrFail();

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put($this->tenants->companySessionKey(), $company->id);
        $request->session()->forget(['saas.registration', 'saas.login_email']);

        // Redirect to license activation (system locked until activated)
        return redirect()->route('license.show')
            ->with('success', 'Company registered successfully! Please activate your license to access the system.');
    }

    // ─── Complete Login ──────────────────────────────────────────────────────

    private function completeLoginFlow(Request $request, $googleUser)
    {
        $companyId = $request->session()->get('saas.login_company_id');

        if (!$companyId) {
            return redirect()->route('login')->with('error', 'Login session expired. Please try again.');
        }

        $company = Company::whereKey($companyId)->where('status', 'active')->first();
        if (!$company) {
            return redirect()->route('login')->with('error', 'Company is inactive or does not exist.');
        }

        // Switch to tenant DB
        $this->tenants->switchToCompany($company);

        // Find user in tenant database
        $user = User::on($this->tenants->connectionName())
            ->where(function ($q) use ($googleUser) {
                $q->where('google_email', $googleUser->email)
                  ->orWhere('email', $googleUser->email);
            })
            ->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'company_email' => 'Your Google account is not registered in this company. Contact your administrator.',
            ]);
        }

        // Update Google credentials if first time
        if (empty($user->google_id)) {
            $user->update([
                'google_id'     => $googleUser->id,
                'google_email'  => $googleUser->email,
                'auth_provider' => 'google',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put($this->tenants->companySessionKey(), $company->id);
        $request->session()->forget(['saas.login_email', 'saas.login_company_id']);

        // Check license
        if (!$company->hasActiveLicense()) {
            return redirect()->route('license.show');
        }

        return redirect()->route('dashboard');
    }
}
