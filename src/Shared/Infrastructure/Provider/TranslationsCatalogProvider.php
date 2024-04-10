<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Provider;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationsCatalogProvider
{
    private const TRANSLATIONS_CATALOG_URL = 'https://raw.githubusercontent.com/PrestaShop/TranslationFiles/master/%s/%s/%s.%s.xlf';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    public function getTranslationsCatalog(string $locale, string $domain, int $PSversion = 9): array
    {
        $domain = str_replace('.', '', $domain);

        return $this->cache->get(
            't9n.'.$locale.'.'.$domain,
            function (ItemInterface $item) use ($locale, $domain, $PSversion) {
                $item->expiresAfter(self::CACHE_TTL);
                $xlfFileContent = $this->downloadTranslationsCatalog($locale, $domain, $PSversion);

                return $this->formatTranslationsCatalog($xlfFileContent);
            }
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function downloadTranslationsCatalog(string $locale, string $domain, int $PSversion): string
    {
        $url = sprintf(self::TRANSLATIONS_CATALOG_URL, $PSversion, $locale, $domain, $locale);
        $response = $this->httpClient->request('GET', $url);

        return $response->getContent();
    }

    /**
     * @return array<int, string>
     */
    private function formatTranslationsCatalog(string $xlfFileContent): array
    {
        if (preg_match_all('/<source>(<!\[CDATA\[)?(.*?)(\]\]>)?<\/source>/', $xlfFileContent, $matches)) {
            return array_unique($matches[2]);
        }

        return [];
    }
}
