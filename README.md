# omnischolar/inspire

INSPIRE-HEP for [glitchr/omnischolar](https://github.com/glitchr-studio/omnischolar): high-energy
physics. An author's profile - by INSPIRE record, BAI or ORCID - with their positions and
INSPIRE's citation summary (citations, h-index); their literature with citation counts, arXiv
ids, DOIs and journal references; a paper by its INSPIRE record, DOI or arXiv id; a search in
INSPIRE's query language. No key.

```php
$inspire = (new InspireSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$inspire->author('bai:Juan.M.Maldacena.1')->metrics->hIndex;
$inspire->works('Juan.M.Maldacena.1', new Query(from: 2020, domains: ['gr-qc']));
$inspire->work('hep-th/9711200');
```

```yaml
omnischolar:
    sources:
        inspire: { factory: inspire, options: { mailto: 'support@glitchr.io' } }   # throttle: 0.35 s
```

See [docs/](docs/index.md).

License: LGPL-3.0-or-later.
