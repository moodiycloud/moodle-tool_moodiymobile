# Codex: Preserve the verified global push identity during branding updates

The old signed settings endpoint could overwrite a deployment-owned key during provider callback readback. A first key-preserving candidate still applied a hosted global app name over a valid standalone key. Actual Core-to-Moodle HTTP and independent native config processes reproduced both failures. Earlier green checks cover that historical candidate, not this revised pair contract.

The signed internal-only mode is `preserve_deployment_pair_v1`. It requires all eight existing non-key settings, integer timestamp, existing request HMAC and a nullable `airnotifier_expected_key_sha256` precondition. Core derives that private digest only from current independently verified schema2 delivery evidence. The plugin does not accept an access-key field with this mode or infer identity ownership from key presence.

The full guarded payload is staged in a delegated transaction. A fresh locked read of both global identity fields must match the expected digest and app name before applying the seven other fields or enabling the internal plugin. Otherwise the response is `staged_waiting_for_pair`. Neither identity field is ever written by this mode. Stored decoding, unsigned admin-page input, enablement and branding helpers preserve the guard rather than reinterpreting it as legacy settings. Initial missing-key staging does not enable the plugin or fill a key. The existing eligible provider path and bounded Core follow-up supply delivery; the plugin creates no new dispatch right.

The authenticated acknowledgement contains mode, schema1, exact request digest, state and `pair_write_performed=false`, signed with the existing UUID/sorted-JSON HMAC convention. It attributes this exact response without introducing a new credential or claiming independent remote verification. Existing Core transport accepts HTTP as well as HTTPS; its separate Automator skip-SSL option does not govern this plugin POST.

## Existing full-key paths

Essential/external and standalone requests retain their existing routing. A complete legacy global key/name write now locks the exact pair in consistent order inside its own transaction, including unchanged-key repairs. It refuses an existing outer transaction before any write, rolls back explicitly on failure, and invalidates fixed config scopes after physical commit/rollback. Actual endpoint/admin/provider entrypoints have no outer transaction; partial noncredential utility calls retain their prior behavior. No generic transaction/event framework is added.

The ordinary branding-only helper omits both global identity fields; its purpose is to apply staged branding without replacing the existing identity. Legacy first-key enablement uses the existing staged/default app-name choice in the same complete-pair boundary. No app preference or broker selection is changed.

Cold readers after a sole commit see the new pair; warm PHP requests can retain an older coherent pair. Moodle's general SELECT-to-cache-set API is not linearizable across arbitrary in-flight readers. Physical proof and guarded application therefore use fresh database state, never cache authority. Nested complete-pair refusal prevents uncommitted identity cache writes through the supported utility boundary.

## Validation and rollout

Local native PHPUnit covers valid/malformed payloads, foreign/wrong-name/unproved pairs, authenticated acknowledgements, missing-key staging, all stored helper routes, nested refusal and separate stale processes. Actual HTTP uses Core's signing and response validation. An actual DML trigger failure verifies rollback of every targeted row and a closed transaction. Provider tests include both concurrent legacy/provider orders, unchanged-key repair, cold/warm observations and exact fixture restoration. Full current-head matrix results and actual served runtime qualification remain required; no production or push-notification delivery acceptance is claimed.

Publish the atomic provider and independent Cloud verifier after the merged Core schema2 consumer. Propagate this source plugin through the normal reviewed CI-synced runtime pipeline, verify exact relevant runtime capabilities and protected fields, then activate held Core branding. No direct runtime-copy PR, legacy fallback or customer-plugin bypass is part of this plan. Coordinate rollback across these boundaries rather than silently restoring a competing writer.

Standalone/bundled business selection remains the [explicit owner question](https://github.com/moodiycloud/moodiy/issues/1140#issuecomment-6433478668). This fix preserves coherent pairs under current write rights; it does not impose a one-plan limit, grant standalone provider delivery or decide the preferred physical app.

The CI matrix covers Moodle4.5,5.0,5.1,5.2 and main with PostgreSQL/MariaDB and supported PHP versions. Earlier CI corrections resolve Composer under root/public layouts and use MariaDB11.4 only for main with the official image healthcheck. These change no deployed database.
