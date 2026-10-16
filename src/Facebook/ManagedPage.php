<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Facebook;

/**
 * A Facebook Page the logged-in admin manages, with the Instagram professional
 * account linked to it, if any.
 */
final class ManagedPage
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $accessToken,
        public readonly ?string $pictureUrl = null,
        public readonly ?string $instagramId = null,
        public readonly ?string $instagramUsername = null,
        public readonly ?string $instagramName = null,
        public readonly ?string $instagramPictureUrl = null,
    ) {
    }

    public function hasInstagram(): bool
    {
        return $this->instagramId !== null && $this->instagramId !== '';
    }
}
