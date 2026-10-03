# omnischolar/inspire

## Installation

```sh
composer require omnischolar/inspire
```

```php
use Omnischolar\Inspire\InspireSourceFactory;

$inspire = (new InspireSourceFactory($httpClient))->create(['mailto' => 'support@glitchr.io']);
```

INSPIRE allows 15 calls in 5 seconds: the default `throttle` leaves 0.35 s between two.

## Calls

| Method | Reads | INSPIRE API |
|---|---|---|
| `author()` | INSPIRE author record, BAI (`bai:Juan.M.Maldacena.1`), ORCID | `authors/{id}`, `authors?q=ids.value:"..."`, then `literature/facets?facet_name=citation-summary` |
| `works()` | BAI; an INSPIRE author record or an ORCID (through the author's BAI); a name | `literature?q=a ...`, paged |
| `work()` | INSPIRE literature record, DOI, arXiv id | `literature/{id}`, `doi/{doi}`, `arxiv/{id}` |
| `search()` | `text` in INSPIRE's query language (`t wormholes and a maldacena`), `title`, `author` | `literature?q=` |

`Query`: `from`/`to` (`de 2020->2024`), `types` (`document_type`), `domains` (arXiv categories),
`sort` (`newest` = `mostrecent`, `oldest` = `leastrecent`, `cited` = `mostcited`, `relevance` =
`bestmatch`), `limit` (1 000 at most), `cursor` (a page number).

## What a paper holds

Title, type (an `article` without a journal nor a DOI is a `preprint`; `book chapter`,
`conference paper`, `thesis`, `report`, `proceedings` and `book` as such), authors with their
BAI, ORCID and affiliations, year (the journal's, else the earliest date) and date, venue (the
journal - the publication before a reprint or an erratum), volume, issue, pages (or the article
id), abstract, citation count, keywords, arXiv categories as domains, the INSPIRE page, the arXiv
PDF; identifiers: INSPIRE record, DOIs (the publication's first), arXiv id, ISBNs.

## What an author holds

The preferred name, given and family names, name variants and native names, INSPIRE record,
BAI, ORCID, positions as `Affiliation` (`employment`, or `education` for PhD, master and
undergraduate ranks; current when INSPIRE says so), INSPIRE's citation summary as `Metrics`
(citeable papers, citations, h-index), URLs, arXiv categories as keywords.

## What INSPIRE does not give

Physics only (high-energy and neighbouring fields). Literature by ORCID goes through the
author's BAI (searching the ORCID on papers finds fewer). Collaboration papers carry thousands
of authors: all are read.

## Tests

Recorded on 2026-10-04 (Juan Maldacena): the author by BAI and by ORCID, the citation summary,
two pages of literature, hep-th/9711200 by arXiv id.
