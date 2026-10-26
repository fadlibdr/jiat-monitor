<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Support\CompanyContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the active company from session('active_company_id') and refuses (403) when
 * the user has no company_user row for it. Switching company = switching context,
 * never a new right (HLD §7). Holding roles pick a company the same way for drill-down
 * only if they hold a company_user row there.
 */
class SetActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companyId = $request->hasSession() ? $request->session()->get('active_company_id') : null;

        abort_if($user === null || $companyId === null, 403, 'Perusahaan aktif belum dipilih.');

        $member = DB::table('tn_company_user')
            ->where('user_id', $user->getKey())
            ->where('company_id', (int) $companyId)
            ->exists();

        abort_unless($member, 403, 'Anda tidak terdaftar pada perusahaan ini.');

        return CompanyContext::run((int) $companyId, fn () => $next($request));
    }
}
