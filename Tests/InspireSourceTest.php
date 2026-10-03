<?php

namespace Omnischolar\Inspire\Tests;

use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Inspire\InspireSourceFactory;
use Omnischolar\Model\Affiliation;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\Work;
use Omnischolar\Model\WorkType;
use Omnischolar\Source\Query;
use Omnischolar\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from inspirehep.net/api on 2026-10-04 (Juan Maldacena):
 * author-bai.json (authors?q=ids.value:"Juan.M.Maldacena.1"), author-orcid.json
 * (authors?q=ids.value:"0000-0002-9127-1687"), citation-summary.json
 * (literature/facets?q=a Juan.M.Maldacena.1&facet_name=citation-summary),
 * works-page1.json and works-page2.json (literature?q=a Juan.M.Maldacena.1,
 * 10 a page, most recent first), work-arxiv.json (arxiv/hep-th/9711200).
 */
final class InspireSourceTest extends TestCase
{
    /** @var list<array{string, array<string, string>}> */
    private array $calls = [];

    private function source(): SourceInterface
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$path, $query];
            $fixture = match (true) {
                '/api/authors' === $path && 'ids.value:"Juan.M.Maldacena.1"' === $query['q'] => 'author-bai.json',
                '/api/authors' === $path && 'ids.value:"0000-0002-9127-1687"' === $query['q'] => 'author-orcid.json',
                '/api/literature/facets' === $path => 'citation-summary.json',
                '/api/literature' === $path && str_starts_with($query['q'], 'a Juan.M.Maldacena.1') => '2' === $query['page'] ? 'works-page2.json' : 'works-page1.json',
                '/api/arxiv/hep-th/9711200' === $path => 'work-arxiv.json',
                default => null,
            };

            return $fixture ? new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture)) : new MockResponse('{"status":404,"message":"PID does not exist."}', ['http_code' => 404]);
        });

        return (new InspireSourceFactory($http))->create(['throttle' => 0]);
    }

    public function testTheProfileWithItsPositionsAndCitationSummary(): void
    {
        $author = $this->source()->author('bai:Juan.M.Maldacena.1');

        self::assertSame('Juan M. Maldacena', $author->name);
        self::assertSame(['Juan Martin', 'Maldacena'], [$author->given, $author->family]);
        self::assertSame('0000-0002-9127-1687', $author->orcid());
        self::assertSame('999108', $author->identifiers->value(Scheme::INSPIRE));
        self::assertSame('Juan.M.Maldacena.1', $author->identifiers->value(Scheme::INSPIRE_BAI));
        self::assertSame(118, $author->metrics->hIndex);
        self::assertSame(96872, $author->metrics->citations);
        self::assertSame(197, $author->metrics->works, 'the citeable papers');
        self::assertSame(['hep-th', 'gr-qc'], $author->keywords);

        $ias = $author->affiliations[0];
        self::assertSame('Princeton, Inst. Advanced Study', $ias->organization);
        self::assertSame(Affiliation::EMPLOYMENT, $ias->kind);
        self::assertSame('Senior', $ias->role);
        self::assertTrue($ias->isCurrent());
        self::assertSame(['Princeton U.', 'Cuyo U.'], array_map(static fn (Affiliation $a) => $a->organization, $author->affiliations(Affiliation::EDUCATION)));
    }

    public function testTheLiteraturePageByPage(): void
    {
        $source = $this->source();
        $first = $source->works('Juan.M.Maldacena.1', new Query(limit: 10));

        self::assertSame(213, $first->total);
        self::assertCount(10, $first);
        self::assertSame('2', $first->next);
        self::assertSame('a Juan.M.Maldacena.1', $this->calls[0][1]['q']);
        self::assertSame('mostrecent', $this->calls[0][1]['sort']);

        $preprint = $first->works[0];
        self::assertSame('Logarithmic gravity from a very slow roll limit of inflation', $preprint->title);
        self::assertSame(WorkType::PREPRINT, $preprint->type, 'no journal yet');
        self::assertSame('2609.38052', $preprint->id(Scheme::ARXIV));
        self::assertSame(['hep-th', 'gr-qc'], $preprint->domains);

        $published = $this->titled($first->works, 'The no boundary density matrix');
        self::assertSame(WorkType::ARTICLE, $published->type);
        self::assertSame('JHEP', $published->venue->name);
        self::assertSame('10.1007/jhep02(2025)124', $published->doi());
        self::assertSame(43, $published->citations);
        self::assertSame(2025, $published->year, 'the year of the journal');
        self::assertNotNull($published->id(Scheme::ARXIV));

        $second = $source->works('Juan.M.Maldacena.1', new Query(limit: 10, cursor: $first->next));
        self::assertCount(10, $second);
        self::assertSame('2', $this->calls[1][1]['page']);
        self::assertSame(WorkType::CHAPTER, $this->titled($second->works, 'The AdS/CFT Correspondence')->type);
    }

    public function testAnOrcidGoesThroughTheBai(): void
    {
        $page = $this->source()->works(Identifier::orcid('0000-0002-9127-1687'), new Query(limit: 10, from: 2020, to: 2024, types: [WorkType::ARTICLE], domains: ['gr-qc']));

        self::assertCount(10, $page);
        self::assertSame('/api/authors', $this->calls[0][0]);
        self::assertSame('a Juan.M.Maldacena.1 and (arxiv_eprints.categories:gr-qc) and de 2020->2024 and (document_type:"article")', $this->calls[1][1]['q']);
    }

    public function testThePublicationBeforeTheReprint(): void
    {
        $work = $this->source()->work('hep-th/9711200');

        self::assertSame('The Large $N$ limit of superconformal field theories and supergravity', $work->title);
        self::assertSame('Adv.Theor.Math.Phys.', $work->venue->name);
        self::assertSame(['2', '231-252', 1998], [$work->volume, $work->pages, $work->year]);
        self::assertSame('10.4310/atmp.1998.v2.n2.a1', $work->doi(), 'the publication\'s DOI first');
        self::assertContains('10.1023/a:1026654312961', $work->identifiers->values(Scheme::DOI));
        self::assertSame(22640, $work->citations);
        self::assertSame('https://arxiv.org/pdf/hep-th/9711200', $work->pdfUrl);
        self::assertSame('/api/arxiv/hep-th/9711200', $this->calls[0][0]);
    }

    public function testUnknownAndUnsupported(): void
    {
        self::assertNull($this->source()->work(Identifier::inspire('1')));
        $this->expectException(NotSupportedException::class);
        $this->source()->work(Identifier::isbn('9781412996600'));
    }

    /** @param list<Work> $works */
    private function titled(array $works, string $title): Work
    {
        foreach ($works as $work) {
            if ($title === $work->title) {
                return $work;
            }
        }
        self::fail($title.' not found');
    }
}
