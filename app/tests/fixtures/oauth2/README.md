These keys exist only for automated tests and must never be used anywhere else.

Paths in `phpunit.xml.dist` are project-relative; loaders resolve them with
`Director::getAbsFile()` (BASE_PATH), not the process working directory.

Regenerate when needed:

```sh
openssl genrsa -out test-private.pem 2048
openssl rsa -in test-private.pem -pubout -out test-public.pem
```

Unit tests that need isolation still write temporary PEMs under `sys_get_temp_dir()`.
