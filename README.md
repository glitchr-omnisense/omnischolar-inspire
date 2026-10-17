# omnischolar/inspire

INSPIRE-HEP for [omniscient/omnischolar](https://github.com/glitchr-studio/omnischolar): high-energy
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
