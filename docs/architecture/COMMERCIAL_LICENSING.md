# Commercial licensing trust boundary

HiddenLeaf uses asymmetric RSA/SHA-256 signatures. The signing authority and customer BusinessOS have deliberately different responsibilities.

## License authority deployment

The authority is a separately configured deployment with `LICENSE_SERVER_ENABLED=true`. It owns:

- the `LICENSE_SERVER_PRIVATE_KEY` signing key;
- products, editions, licenses, domains, installations, activations, revocation, suspension, expiry, limits, and entitlement issuance;
- the public activation, deactivation, online validation, and entitlement protocol endpoints;
- issuance of signed offline entitlement tokens.

The private key must be injected from an external secret manager or a locked filesystem path. It must never be committed, included in a build artifact, copied to a customer environment, logged, stored in settings, or returned by an API.

## Customer BusinessOS deployment

Normal installations keep `LICENSE_SERVER_ENABLED=false` and therefore do not register license-authority routes. They own:

- `LICENSING_PUBLIC_KEY`, used only for signature verification;
- offline token verification, domain binding, expiry and grace handling;
- locally cached entitlement state required to operate during an allowed offline period.

`LICENSE_SERVER_PRIVATE_KEY` is not required for installation, startup, updates, module verification, or normal BusinessOS operation. Calling a signing operation without authority configuration fails closed.

## Token guarantees

- The accepted algorithm is fixed to `RS256`; algorithm substitution is rejected.
- The signed payload is checked for `nbf`, expiry, grace, domain, license state, and activation state.
- Online activation looks up a persisted license and enforces product, domain, status, activation-limit, and installation rules before signing.
- Unknown, expired outside grace, revoked, suspended, mismatched, or over-limit licenses fail closed.
- Module and updater packages have independent public-key verification and SHA-256/path-safety checks.

## Test keys

`tests/Fixtures/license-private.pem` and its matching public key are test-only fixtures. Production configuration never loads them. Tests may also generate or inject ephemeral pairs directly through the service constructor/config repository. CI explicitly enables license-server mode only to exercise authority workflows.

## Operational checklist

1. Keep customer `.env` at `LICENSE_SERVER_ENABLED=false`.
2. Configure only `LICENSING_PUBLIC_KEY` on customer systems.
3. Store the authority private key outside the repository and deployment package.
4. Rotate keys through `LICENSING_KEY_ID` and a controlled customer public-key rollout.
5. Audit activation, deactivation, revocation, suspension, and validation failures without recording keys or bearer tokens.
