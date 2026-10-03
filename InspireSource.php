<?php

namespace Omnischolar\Inspire;

use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Model\Affiliation;
use Omnischolar\Model\Author;
use Omnischolar\Model\Contributor;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Identifiers;
use Omnischolar\Model\Metrics;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\Venue;
use Omnischolar\Model\Work;
use Omnischolar\Model\WorkType;
use Omnischolar\Source\Capability;
use Omnischolar\Source\HttpSource;
use Omnischolar\Source\Page;
use Omnischolar\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * INSPIRE-HEP (inspirehep.net/api, no key): high-energy physics. An
 * author's profile - by INSPIRE record, BAI ("Juan.M.Maldacena.1") or
 * ORCID - with their positions and INSPIRE's citation summary (citations,
 * h-index over the citeable papers); their literature with citation
 * counts, arXiv ids, DOIs and journal references; a paper by its INSPIRE
 * record, DOI or arXiv id; a search in INSPIRE's own query language.
 *
 * INSPIRE allows 15 calls in 5 seconds.
 */
final class InspireSource extends HttpSource
{
    public const BASE_URI = 'https://inspirehep.net/api/';

    /** The most records INSPIRE serves per page. */
    public const MAX_SIZE = 1000;

    private const FIELDS = 'control_number,titles,authors.full_name,authors.ids,authors.affiliations.value,authors.inspire_roles,document_type,publication_info,dois,arxiv_eprints,isbns,abstracts,citation_count,imprints,keywords,inspire_categories,earliest_date,preprint_date,languages,thesis_info,urls';

    /** INSPIRE's document types for each kind. */
    private const TYPES = [
        'article' => ['article'],
        'book' => ['book', 'proceedings'],
        'chapter' => ['book chapter'],
        'conference' => ['conference paper'],
        'preprint' => ['article'],
        'thesis' => ['thesis'],
        'report' => ['report', 'activity report'],
        'dataset' => [],
        'software' => [],
        'review' => [],
        'editorial' => [],
        'other' => ['note'],
    ];

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'inspire';
    }

    public function capabilities(): array
    {
        return [Capability::AUTHOR, Capability::WORKS, Capability::WORK, Capability::SEARCH, Capability::METRICS, Capability::CITATIONS, Capability::ABSTRACTS, Capability::AFFILIATIONS, Capability::DOMAINS];
    }

    public function author(Identifier|string $author): ?Author
    {
        $record = $this->authorRecord($author);
        if (null === $record) {
            return null;
        }
        $m = $record['metadata'];
        $name = $m['name'] ?? [];
        $parsed = Contributor::fromName((string) ($name['value'] ?? ''));
        $identifiers = [Identifier::tryOf(Scheme::INSPIRE, (string) ($m['control_number'] ?? ''))];
        $bai = null;
        foreach ($m['ids'] ?? [] as $id) {
            $identifiers[] = match ($id['schema'] ?? null) {
                'ORCID' => Identifier::tryOf(Scheme::ORCID, $id['value']),
                'INSPIRE BAI' => Identifier::tryOf(Scheme::INSPIRE_BAI, $bai = (string) $id['value']),
                default => null,
            };
        }
        $affiliations = [];
        foreach ($m['positions'] ?? [] as $position) {
            $rank = $position['rank'] ?? null;
            $affiliations[] = new Affiliation(
                organization: (string) ($position['institution'] ?? ''),
                kind: \in_array($rank, ['PHD', 'MASTER', 'UNDERGRADUATE'], true) ? Affiliation::EDUCATION : Affiliation::EMPLOYMENT,
                role: null !== $rank ? ucfirst(strtolower(str_replace('_', ' ', (string) $rank))) : null,
                start: $position['start_date'] ?? null,
                end: $position['end_date'] ?? null,
                current: $position['current'] ?? null,
            );
        }

        return new Author(
            name: (string) ($name['preferred_name'] ?? $parsed->name),
            given: $parsed->given,
            family: $parsed->family,
            identifiers: new Identifiers($identifiers),
            alternativeNames: array_values(array_map('strval', [...($name['name_variants'] ?? []), ...($name['native_names'] ?? [])])),
            affiliations: $affiliations,
            metrics: null !== $bai ? $this->metrics($bai) : null,
            urls: array_values(array_filter([
                'https://inspirehep.net/authors/'.$m['control_number'],
                ...array_map(static fn (array $u) => $u['value'] ?? null, $m['urls'] ?? []),
            ])),
            keywords: array_values(array_map('strval', $m['arxiv_categories'] ?? [])),
            source: 'inspire',
        );
    }

    /**
     * An author's literature: by BAI ("Juan.M.Maldacena.1"), INSPIRE author
     * record or ORCID (both through the BAI), or a name INSPIRE matches
     * ("Maldacena, Juan"). The cursor is a page number.
     */
    public function works(Identifier|string $author, ?Query $query = null): Page
    {
        $id = self::identify($author, Scheme::INSPIRE_BAI, Scheme::ORCID, Scheme::INSPIRE);
        if (null === $id && $author instanceof Identifier) {
            throw NotSupportedException::identifier('inspire', (string) $author, 'list the works of');
        }
        if (null !== $id && Scheme::INSPIRE_BAI !== $id->scheme) {
            $record = $this->authorRecord($id);
            $bai = null;
            foreach ($record['metadata']['ids'] ?? [] as $ref) {
                if ('INSPIRE BAI' === ($ref['schema'] ?? null)) {
                    $bai = (string) $ref['value'];
                }
            }
            if (null === $bai) {
                return new Page([], null, 0);
            }
            $id = Identifier::of(Scheme::INSPIRE_BAI, $bai);
        }
        $term = null !== $id ? 'a '.$id->value : 'a "'.str_replace('"', '', (string) $author).'"';

        return $this->list($query ?? new Query(), [$term]);
    }

    public function work(Identifier|string $id): ?Work
    {
        $identifier = self::identify($id, Scheme::INSPIRE, Scheme::DOI, Scheme::ARXIV) ?? throw NotSupportedException::identifier('inspire', (string) $id);
        $path = match ($identifier->scheme) {
            Scheme::INSPIRE => 'literature/'.$identifier->value,
            Scheme::DOI => 'doi/'.$identifier->value,
            default => 'arxiv/'.$identifier->value,
        };
        $record = $this->getJson($path, ['fields' => self::FIELDS]);

        return isset($record['metadata']) ? $this->toWork($record['metadata']) : null;
    }

    /** A search: Query::$text is INSPIRE's own query language ("t wormholes and a maldacena"), the rest is added to it. */
    public function search(Query $query): Page
    {
        $terms = [];
        if (null !== $query->text && '' !== trim($query->text)) {
            $terms[] = '('.$query->text.')';
        }
        if (null !== $query->title) {
            $terms[] = 't "'.str_replace('"', '', $query->title).'"';
        }
        if (null !== $query->author) {
            $terms[] = 'a "'.str_replace('"', '', $query->author).'"';
        }

        return $this->list($query, $terms);
    }

    /** @param list<string> $terms */
    private function list(Query $query, array $terms): Page
    {
        if ($query->domains) {
            $terms[] = '('.implode(' or ', array_map(static fn (string $d) => 'arxiv_eprints.categories:'.$d, $query->domains)).')';
        }
        if (null !== $query->from || null !== $query->to) {
            $terms[] = \sprintf('de %s->%s', $query->from ?? 1000, $query->to ?? 9999);
        }
        if ($query->types) {
            $types = array_unique(array_merge(...array_map(static fn (WorkType $t) => self::TYPES[$t->value], $query->types)));
            if (!$types) {
                return new Page([], null, 0);
            }
            $terms[] = '('.implode(' or ', array_map(static fn (string $t) => 'document_type:"'.$t.'"', $types)).')';
        }
        $page = max(1, (int) ($query->cursor ?? 1));
        $size = max(1, min(self::MAX_SIZE, $query->limit));
        $data = $this->getJson('literature', [
            'q' => implode(' and ', $terms),
            'sort' => match ($query->sort) {
                Query::OLDEST => 'leastrecent',
                Query::CITED => 'mostcited',
                Query::RELEVANCE => 'bestmatch',
                default => 'mostrecent',
            },
            'size' => $size,
            'page' => $page,
            'fields' => self::FIELDS,
        ]) ?? [];
        $works = array_map(fn (array $hit) => $this->toWork($hit['metadata']), $data['hits']['hits'] ?? []);
        $total = $data['hits']['total'] ?? null;

        return new Page($works, $works && isset($data['links']['next']) ? (string) ($page + 1) : null, $total);
    }

    /** @return array<string, mixed>|null */
    private function authorRecord(Identifier|string $author): ?array
    {
        $id = self::identify($author, Scheme::INSPIRE, Scheme::INSPIRE_BAI, Scheme::ORCID) ?? throw NotSupportedException::identifier('inspire', (string) $author);
        if (Scheme::INSPIRE === $id->scheme) {
            return $this->getJson('authors/'.$id->value);
        }

        return $this->getJson('authors', ['q' => 'ids.value:"'.$id->value.'"', 'size' => 1])['hits']['hits'][0] ?? null;
    }

    /** INSPIRE's citation summary of an author's citeable papers. */
    private function metrics(string $bai): ?Metrics
    {
        $summary = $this->getJson('literature/facets', ['q' => 'a '.$bai, 'facet_name' => 'citation-summary'])['aggregations']['citation_summary'] ?? null;
        if (null === $summary) {
            return null;
        }

        return new Metrics(
            works: $summary['doc_count'] ?? null,
            citations: isset($summary['citations']['buckets']['all']['citations_count']['value']) ? (int) $summary['citations']['buckets']['all']['citations_count']['value'] : null,
            hIndex: $summary['h-index']['value']['all'] ?? null,
            source: 'inspire',
        );
    }

    /** @param array<string, mixed> $m */
    private function toWork(array $m): Work
    {
        $documentType = (string) ($m['document_type'][0] ?? 'article');
        // The publication itself before a reprint or an erratum.
        $publication = null;
        $infos = $m['publication_info'] ?? [];
        usort($infos, static fn (array $a, array $b) => [\in_array($a['material'] ?? 'publication', ['publication', 'version'], true), isset($a['journal_title'])] <=> [\in_array($b['material'] ?? 'publication', ['publication', 'version'], true), isset($b['journal_title'])]);
        foreach (array_reverse($infos) as $info) {
            if (isset($info['journal_title']) || isset($info['year'])) {
                $publication = $info;
                break;
            }
        }
        $type = match ($documentType) {
            'article' => null === $publication && !isset($m['dois']) ? WorkType::PREPRINT : WorkType::ARTICLE,
            'book', 'proceedings' => WorkType::BOOK,
            'book chapter' => WorkType::CHAPTER,
            'conference paper' => WorkType::CONFERENCE,
            'thesis' => WorkType::THESIS,
            'report', 'activity report' => WorkType::REPORT,
            default => WorkType::OTHER,
        };

        $authors = [];
        foreach ($m['authors'] ?? [] as $a) {
            $parsed = Contributor::fromName((string) ($a['full_name'] ?? ''));
            $ids = [];
            foreach ($a['ids'] ?? [] as $id) {
                $ids[] = match ($id['schema'] ?? null) {
                    'INSPIRE BAI' => Identifier::tryOf(Scheme::INSPIRE_BAI, $id['value']),
                    'ORCID' => Identifier::tryOf(Scheme::ORCID, $id['value']),
                    default => null,
                };
            }
            $roles = $a['inspire_roles'] ?? ['author'];
            $authors[] = new Contributor($parsed->name, $parsed->given, $parsed->family, new Identifiers($ids), \in_array('editor', $roles, true) ? Contributor::EDITOR : Contributor::AUTHOR, array_values(array_map(static fn (array $f) => (string) $f['value'], $a['affiliations'] ?? [])));
        }

        $identifiers = [Identifier::tryOf(Scheme::INSPIRE, (string) ($m['control_number'] ?? ''))];
        $dois = $m['dois'] ?? [];
        usort($dois, static fn (array $a, array $b) => ('publication' === ($b['material'] ?? null)) <=> ('publication' === ($a['material'] ?? null)));
        foreach ($dois as $doi) {
            $identifiers[] = Identifier::tryOf(Scheme::DOI, $doi['value'] ?? null);
        }
        $categories = [];
        foreach ($m['arxiv_eprints'] ?? [] as $eprint) {
            $identifiers[] = Identifier::tryOf(Scheme::ARXIV, $eprint['value'] ?? null);
            array_push($categories, ...($eprint['categories'] ?? []));
        }
        foreach ($m['isbns'] ?? [] as $isbn) {
            $identifiers[] = Identifier::tryOf(Scheme::ISBN, $isbn['value'] ?? null);
        }
        $arxiv = $m['arxiv_eprints'][0]['value'] ?? null;

        $date = $m['earliest_date'] ?? $m['preprint_date'] ?? null;
        $year = isset($publication['year']) ? (int) $publication['year'] : (null !== $date ? (int) substr((string) $date, 0, 4) : null);
        $pages = null;
        if (isset($publication['page_start'])) {
            $pages = $publication['page_start'].(isset($publication['page_end']) ? '-'.$publication['page_end'] : '');
        } elseif (isset($publication['artid'])) {
            $pages = (string) $publication['artid'];
        }
        $imprint = $m['imprints'][0] ?? [];

        return new Work(
            title: self::text($m['titles'][0]['title'] ?? null) ?? '',
            type: $type,
            authors: $authors,
            year: $year,
            date: null !== $date && (null === $year || str_starts_with((string) $date, (string) $year)) ? (string) $date : (null !== $year ? (string) $year : null),
            subtitle: self::text($m['titles'][0]['subtitle'] ?? null),
            venue: isset($publication['journal_title']) ? new Venue((string) $publication['journal_title'], Venue::JOURNAL) : (null !== $arxiv && WorkType::PREPRINT === $type ? new Venue('arXiv', Venue::REPOSITORY) : null),
            publisher: $imprint['publisher'] ?? null,
            volume: $publication['journal_volume'] ?? null,
            issue: $publication['journal_issue'] ?? null,
            pages: $pages,
            abstract: self::text($m['abstracts'][0]['value'] ?? null),
            language: $m['languages'][0] ?? null,
            openAccess: null !== $arxiv ? true : null,
            url: 'https://inspirehep.net/literature/'.$m['control_number'],
            pdfUrl: null !== $arxiv ? 'https://arxiv.org/pdf/'.$arxiv : null,
            citations: $m['citation_count'] ?? null,
            keywords: array_values(array_map(static fn (array $k) => (string) $k['value'], $m['keywords'] ?? [])),
            domains: array_values(array_unique($categories)),
            identifiers: new Identifiers($identifiers),
            source: 'inspire',
        );
    }
}
