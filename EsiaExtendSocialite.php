<?php

namespace Yaremenkosa\Esia;

use SocialiteProviders\Manager\SocialiteWasCalled;

class EsiaExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('esia', Provider::class);
    }
}