<?php

namespace Omnischolar\Inspire;

use Omnischolar\Config;
use Omnischolar\Source\SourceFactory;
use Omnischolar\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * INSPIRE-HEP: high-energy physics literature, authors and citations - no key.
 *
 *   options:
 *     throttle: 0.35                          # seconds between two calls (INSPIRE allows 15 in 5 s)
 *     mailto: 'support@glitchr.io'            # in the User-Agent
 *     base_uri: 'https://inspirehep.net/api/'
 */
final class InspireSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnischolar.factory_name' => 'inspire',
            'omnischolar.required_options' => [],
            'throttle' => 0.35,
            'base_uri' => InspireSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new InspireSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', InspireSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle', 0.35),
        );
    }
}
