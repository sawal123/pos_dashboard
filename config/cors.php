<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| PREM-D03C — browser/WebView clients (POS Mobile) read the private Cloud
| backup download's integrity headers from JavaScript. Browsers only make
| response headers script-readable when the name is listed in
| `Access-Control-Expose-Headers`, so both custom headers must be declared
| here or the mobile fail-closed verification can never pass.
|
| Only `exposed_headers` is declared. Every other option is intentionally left
| to the current Laravel framework defaults (`paths`, `allowed_methods`,
| `allowed_origins`, `allowed_headers`, `max_age`, `supports_credentials`) so
| this fix does not widen the existing origin or credentials policy.
|
| Exposing a header only makes a response header that the server already sent
| to an authorized caller readable by script. It does not relax the download
| authorization stack, and no credential, token, storage path or other secret
| is exposed.
|
*/

return [
    'exposed_headers' => [
        'X-Checksum-Sha256',
        'X-Backup-Schema-Version',
    ],
];
