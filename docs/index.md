# omnischolar/inspire

INSPIRE-HEP, the high-energy physics database, for
[omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): an author's profile -
by INSPIRE record, BAI or ORCID - with their positions and INSPIRE's citation summary; their
literature with citation counts, arXiv ids, DOIs and journal references; a paper by its INSPIRE
record, DOI or arXiv id; a search in INSPIRE's own query language. The source calls INSPIRE's
REST API, without a key:

| Operation | Request |
|---|---|
| `author($id)` | `GET https://inspirehep.net/api/authors/<record>`, or `authors?q=ids.value:"<BAI or ORCID>"&size=1`; then `literature/facets?q=a <BAI>&facet_name=citation-summary` |
| `works($author)` | `GET .../literature?q=a <BAI>` (or `a "<name>"`)`&sort=&size=&page=&fields=` |
| `work($id)` | `GET .../literature/<record>`, `.../doi/<doi>` or `.../arxiv/<id>` |
| `search($query)` | `GET .../literature?q=(<text>) and t "<title>" and a "<author>"` |

The base address is `InspireSource::BASE_URI` (`https://inspirehep.net/api/`). There is no
sandbox.

## Installation

```sh
composer require omnischolar/inspire
```

Requires PHP 8.2, `omnisense/omnischolar` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `OmnischolarBundle`, the factory is registered as soon as the package is
installed.

## Credentials

None. INSPIRE allows 15 calls in 5 seconds: the default `throttle` leaves 0.35 s between two.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `throttle` | float | `0.35` | no | seconds between two calls of the source |
| `mailto` | string | `null` | no | added to the `User-Agent` |
| `user_agent` | string | `omnischolar/1.x (+https://github.com/glitchr-omnisense/omnischolar)` | no | |
| `base_uri` | string | `https://inspirehep.net/api/` | no | |

## Capabilities

`capabilities()`: `author`, `works`, `work`, `search`, `metrics`, `citations`, `abstracts`,
`affiliations`, `domains`.

| Operation | Supported | Reads |
|---|---|---|
| `author()` | yes | INSPIRE author record (a number), BAI (`bai:Juan.M.Maldacena.1`, or as written), ORCID |
| `works()` | yes | BAI; an INSPIRE author record or an ORCID (through the author's BAI); a name INSPIRE matches (`Maldacena, Juan`) |
| `work()` | yes | INSPIRE literature record, DOI, arXiv id |
| `search()` | yes | `text` in INSPIRE's query language (`t wormholes and a maldacena`), `title`, `author` |

## Behaviour

**Query.** `domains` → `(arxiv_eprints.categories:hep-th or ...)`; `from`/`to` → `de
<from>-><to>` (1000 and 9999 for an open end); `types` → `document_type:"..."` with INSPIRE's
types for each kind (`article`; `book`, `proceedings`; `book chapter`; `conference paper`;
`article` for preprints too; `thesis`; `report`, `activity report`; `note` for other) - a kind
INSPIRE has none for gives an empty page without a call; `openAccess` is not read. `sort`:
`newest` → `mostrecent`, `oldest` → `leastrecent`, `cited` → `mostcited`, `relevance` →
`bestmatch`. `size` = `limit` 1 to 1000; the cursor is a page number (from 1), the next one given
while INSPIRE links a next page; `Page::$total` is INSPIRE's.

**Works by ORCID or record.** The author's record is read first and its BAI taken: no BAI, an
empty page.

**An author.** The preferred name, given and family names (from the name), name variants and
native names, the INSPIRE record, BAI and ORCID; positions as `Affiliation`s (`education` for the
`PHD`, `MASTER` and `UNDERGRADUATE` ranks, `employment` otherwise; the rank as `role`; start, end,
current as INSPIRE says); INSPIRE's citation summary as `Metrics` (citeable papers, citations,
h-index) when the author has a BAI; the INSPIRE page and the record's URLs; the arXiv categories as
keywords.

**A paper.** Title and subtitle; type (an `article` without a journal nor a DOI is a `preprint`;
the others as their document type); authors with their BAI, ORCID and affiliations, editors by
their role; year (the journal's, else the earliest date) and date; venue (the journal - the
publication before a reprint or an erratum - or arXiv for a preprint); publisher (the imprint);
volume, issue, pages (or the article id); abstract; language; `openAccess` and the arXiv PDF when
there is an arXiv id; the INSPIRE page; citation count; keywords; the arXiv categories as
`domains`; identifiers: INSPIRE record, DOIs (the publication's first), arXiv id, ISBNs.
Collaboration papers carry thousands of authors: all are read.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnischolar\Inspire\InspireSourceFactory;
use Omnischolar\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$inspire = (new InspireSourceFactory(HttpClient::create()))->create(['mailto' => getenv('OMNISCHOLAR_MAILTO') ?: null]);

$a = $inspire->author('bai:Juan.M.Maldacena.1');
echo $a->name, ': ', $a->metrics?->works, ' citeable papers, ', $a->metrics?->citations, ' citations, h-index ', $a->metrics?->hIndex, "\n";
foreach (array_slice($a->affiliations, 0, 2) as $f) {
    echo '  ', $f->organization, ' ', $f->role, ' ', $f->start, $f->isCurrent() ? ' (current)' : '', "\n";
}
foreach ($inspire->works('bai:Juan.M.Maldacena.1', new Query(sort: Query::CITED, limit: 3)) as $work) {
    printf("  %s  %-10s %s - %s cited %d\n", $work->date ?? $work->year, $work->type->value, mb_substr($work->fullTitle(), 0, 80), $work->venue?->name, $work->citations);
}
```

```
Juan M. Maldacena: 197 citeable papers, 97450 citations, h-index 118
  Princeton, Inst. Advanced Study Senior 2001 (current)
  Harvard U. Senior 1997
  1998  article    The Large $N$ limit of superconformal field theories and supergravity - Adv.Theor.Math.Phys. cited 22731
  2000  article    Large N field theories, string theory and gravity - Phys.Rept. cited 6340
  2003  article    Non-Gaussian features of primordial fluctuations in single field inflationary mo - JHEP cited 3355
```

(called live on 2026-10-26 at 14:18 UTC, from the core's harness image, without a `mailto`)

In Symfony:

```yaml
omnischolar:
    sources:
        inspire: { factory: inspire, options: { mailto: '%env(OMNISCHOLAR_MAILTO)%' } }
```

## Errors

| Case | Result |
|---|---|
| an unknown author or paper | `null`; `works()` of an author without a BAI: an empty page |
| an identifier it does not read | `NotSupportedException` |
| another 4xx (a query INSPIRE cannot parse) | `ProviderException` |
| a 429 | `RateLimitedException` |
| a 5xx, a timeout | `UnavailableException` |

## Verified, and not

Tested offline: `Tests/InspireSourceTest.php` (5 tests), on `MockHttpClient` with answers recorded
on 2026-10-04 (Juan Maldacena): the author by BAI and by ORCID, the citation summary, two pages of
literature, hep-th/9711200 by arXiv id. Called live on 2026-10-04 and on 2026-10-26 (the example
above).

Limits of the service: physics only (high-energy and neighbouring fields); literature by ORCID
goes through the author's BAI (searching the ORCID on papers finds fewer).

## Responsibility

An independent, unofficial client, not affiliated with INSPIRE or its partner laboratories.
Whoever runs it complies with INSPIRE's terms ([INSPIRE terms of
use](https://help.inspirehep.net/knowledge-base/terms-of-use/)): see the README's Responsibility
section.
