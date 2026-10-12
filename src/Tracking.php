<?php

declare(strict_types=1);

// Códigos de acompanhamento herdados do site antigo: Google Tag Manager (Analytics, Ads e
// demais tags são configurados no próprio GTM) e verificação de domínio do Facebook.

function trackingHead(): string
{
    $gtm = trackingGtmId();
    $html = '<meta name="facebook-domain-verification" content="zbc8hhdv9tm49dotfyk5hr39tfhs9g">' . "\n";
    if ($gtm === '') return $html;

    return $html . "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});"
        . "var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;"
        . "j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
        . "})(window,document,'script','dataLayer'," . json_encode($gtm) . ");</script>\n";
}

function trackingBody(): string
{
    $gtm = trackingGtmId();
    if ($gtm === '') return '';

    return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e($gtm)
        . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
}

function trackingGtmId(): string
{
    // Desligado fora de produção para não poluir as estatísticas com acessos de teste.
    if (env('APP_ENV', 'production') !== 'production') return '';
    $id = env('GTM_ID', 'GTM-N5LLN8P');
    return preg_match('/^GTM-[A-Z0-9]+$/', $id) ? $id : '';
}
