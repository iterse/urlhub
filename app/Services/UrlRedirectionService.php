<?php

namespace App\Services;

use App\Models\Url;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Jenssegers\Agent\Agent;

class UrlRedirectionService
{
    /**
     * @var Agent|null
     */
    private $agent;

    /**
     * @var \App\Services\UrlService
     */
    protected $urlSrvc;

    /**
     * UrlRedirectionService constructor.
     *
     * @param Agent|null $agent
     * @param UrlService $urlSrvc
     */
    public function __construct(Agent $agent = null, UrlService $urlSrvc)
    {
        $this->agent = $agent ?? new Agent();
        $this->urlSrvc = $urlSrvc;
    }

    /**
     * Handle the HTTP redirect and return the redirect response.
     *
     * Redirect client to an existing short URL (no check performed) and
     * execute tasks update clicks for short URL.
     *
     * @param Url $url
     * @return RedirectResponse
     */
    public function handleHttpRedirect(Url $url)
    {
        $url->increment('clicks');
        $this->storeVisitStat(
            $url,
            $this->urlSrvc->ipToCountry(
                $this->urlSrvc->anonymizeIp(request()->ip())
            )
        );

        $headers = [
            'Cache-Control' => sprintf('private,max-age=%s', uHub('redirect_cache_lifetime')),
        ];

        return redirect()->away($url->long_url, uHub('redirect_status_code'), $headers);
    }

    /**
     * Create visit statistics and store it in the database.
     *
     * @param Url   $url
     * @param array $countries
     */
    private function storeVisitStat(Url $url, array $countries)
    {
        Visit::create([
            'url_id'           => $url->id,
            'referer'          => request()->server('HTTP_REFERER') ?? null,
            'ip'               => $this->urlSrvc->anonymizeIp(request()->ip()),
            'device'           => $this->agent->device(),
            'platform'         => $this->agent->platform(),
            'platform_version' => $this->agent->version($this->agent->platform()),
            'browser'          => $this->agent->browser(),
            'browser_version'  => $this->agent->version($this->agent->browser()),
            'country'          => $countries['countryCode'],
            'country_full'     => $countries['countryName'],
        ]);
    }
}
