# omnischolar/inspire

INSPIRE-HEP for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): high-energy
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

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://github.com/inspirehep/rest-api-doc on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnischolar/blob/1.x/docs/policies.md)).

- Limits: 15 every 5 seconds per client-ip.
- Licence: CC0-1.0; commercial use: unknown.
- Credit: not required: Moskovic, M. (2021). The INSPIRE REST API. Zenodo. https://doi.org/10.5281/zenodo.5788550 (https://github.com/inspirehep/rest-api-doc).
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by INSPIRE or its partner laboratories (CERN, DESY, Fermilab, IHEP, SLAC). It reaches
their service on behalf of whoever runs it, and it does not redistribute their data beyond the short
excerpts recorded as test fixtures. Its MIT licence covers this package's code only and grants no
right to the service or to its data.

Whoever installs and runs this package is responsible for complying with INSPIRE's terms ([INSPIRE
terms of use](https://help.inspirehep.net/knowledge-base/terms-of-use/)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
