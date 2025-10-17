<?php

declare(strict_types=1);

namespace App\Service\Scraper;

use App\DTO\Scraper\ScrapedFeedDTO;
use App\Enum\FeedSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ElMundoScraper implements ScraperInterface
{
    private readonly FeedSource $source;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
        $this->source = FeedSource::EL_MUNDO;
    }

    public function scrape(int $limit = 5): array
    {
        try {
            $this->logger->info('Begin scraping feeds', [
                'source' => $this->getSourceFlag(),
                'limit' => $limit,
            ]);

            $response = $this->httpClient->request('GET', $this->getBaseUrl(), [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9',
                ],
                'timeout' => 30,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new \Exception('Error HTTP: '.$response->getStatusCode());
            }

            $html = $response->getContent();
            $feeds = $this->parseHtml($html, $limit);

            $this->logger->info('Scraping feeds successful', [
                'source' => $this->getSourceFlag(),
                'feeds_found' => count($feeds),
            ]);


            return $feeds;

        } catch (\Exception $e) {
            $this->logger->error('Error scraping feeds', [
                'source' => $this->getSourceFlag(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Error scrapeando '.$this->getSourceName().': '.$e->getMessage(), 0, $e);
        }
    }

    private function parseHtml(string $html, int $limit): array
    {
        $feeds = [];

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->encoding = 'UTF-8';
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $queries = [
            '//article//header/a',
            '//article//h3/a',
            "//div[contains(@class, 'noticia')]//h2/a",
            "//h2[contains(@class, 'titular')]/a",
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    /** @var \DOMElement  $node */
                    if (count($feeds) >= $limit) {
                        break 2;
                    }

                    $title = trim($node->textContent);
                    $url = $node->getAttribute('href');

                    if (empty($title) || empty($url)) {
                        continue;
                    }

                    if (!str_starts_with($url, 'http')) {
                        if (str_starts_with($url, '/')) {
                            $url = $this->getBaseUrl().$url;
                        } else {
                            $url = $this->getBaseUrl().'/'.$url;
                        }
                    }

                    $isDuplicate = false;
                    foreach ($feeds as $existingFeeds) {
                        if ($existingFeeds->getUrl() === $url) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    if ($isDuplicate) {
                        continue;
                    }


                    $imageUrl = $this->extractImage($node);

                    $feeds[] = new ScrapedFeedDTO(
                        title: $title,
                        url: $url,
                        source: $this->getSourceFlag(),
                        imageUrl: $imageUrl
                    );
                }
            }
        }

        if (empty($feeds)) {
            $this->logger->warning('No feed found', ['source' => $this->getFlag()]);
        }

        return $feeds;
    }

    private function extractImage(\DOMNode|\DOMNameSpaceNode $node): ?string
    {
        $parent = $node->parentNode;
        while ($parent && 'article' !== $parent->nodeName) {
            $parent = $parent->parentNode;
            if (!$parent || $parent === $parent->ownerDocument) {
                break;
            }
        }

        if ($parent && 'article' === $parent->nodeName && null !== $parent->ownerDocument) {
            $xpath = new \DOMXPath($parent->ownerDocument);
            $imgNodes = $xpath->query('.//img', $parent);

            if ($imgNodes && $imgNodes->length > 0) {
                /** @var \DOMElement  $img */
                $img = $imgNodes->item(0);
                $src = $img->getAttribute('src') ?:
                       $img->getAttribute('data-src') ?:
                       $img->getAttribute('data-lazy-src');

                if ($src) {
                    return $this->normalizeImageUrl($src);
                }
            }
        }

        return null;
    }

    private function normalizeImageUrl(string $src): string
    {
        if (str_starts_with($src, 'http')) {
            return $src;
        }

        if (str_starts_with($src, '//')) {
            return 'https:'.$src;
        }

        if (str_starts_with($src, '/')) {
            return $this->getBaseUrl().$src;
        }

        return $src;
    }

    public function getSourceName(): string
    {
        return $this->source->getName();
    }

    public function getSourceFlag(): string
    {
        return $this->source->getFlag();
    }

    public function getBaseUrl(): string
    {
        return $this->source->getBaseUrl();
    }
}
