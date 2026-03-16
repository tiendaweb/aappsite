<?php

declare(strict_types=1);

function integration_value(array $content, string $path, string $default = ''): string
{
    $segments = explode('.', $path);
    $current = $content;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return is_string($current) ? trim($current) : $default;
}

function build_head_injections(array $content): string
{
    $domain = strtolower(integration_value($content, 'site.domain', ''));
    $facebookPixelId = integration_value($content, 'site.integrations.facebook_pixel_id', '');
    $googleAnalyticsId = strtoupper(integration_value($content, 'site.integrations.google_analytics_id', ''));

    $parts = [];

    if ($domain !== '') {
        $canonical = 'https://' . preg_replace('#^https?://#i', '', $domain);
        $canonical = rtrim($canonical, '/');
        $parts[] = '<link rel="canonical" href="' . htmlspecialchars($canonical . '/', ENT_QUOTES, 'UTF-8') . '">';
        $parts[] = '<meta property="og:url" content="' . htmlspecialchars($canonical . '/', ENT_QUOTES, 'UTF-8') . '">';
    }

    if ($googleAnalyticsId !== '' && preg_match('/^(G|UA)-[A-Z0-9\-]+$/', $googleAnalyticsId) === 1) {
        $gaEscaped = htmlspecialchars($googleAnalyticsId, ENT_QUOTES, 'UTF-8');
        $parts[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $gaEscaped . '"></script>';
        $parts[] = '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . $gaEscaped . '");</script>';
    }

    if ($facebookPixelId !== '' && preg_match('/^[0-9]{8,20}$/', $facebookPixelId) === 1) {
        $pixelEscaped = htmlspecialchars($facebookPixelId, ENT_QUOTES, 'UTF-8');
        $parts[] = '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . $pixelEscaped . '");fbq("track","PageView");</script>';
        $parts[] = '<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . $pixelEscaped . '&ev=PageView&noscript=1"/></noscript>';
    }

    return implode("\n", $parts);
}
