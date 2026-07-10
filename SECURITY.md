# Security Policy & Threat Model

This plugin is designed to run in clinical IT and enterprise environments. Security and stability are prioritized.

## Threat Model

### 1. Client-Side Input & Privilege Escalation
- **Threat:** Malicious scripts attempting to inject keyboard shortcuts or bypass forms.
- **Mitigation:** The plugin only intercepts keyboard events to invoke `form.requestSubmit()` on existing forms rendered by GLPI. It does not create actions or bypass backend permissions. If the user doesn't have permissions to save, the standard GLPI backend will reject the submission.

### 2. Configuration Integrity
- **Threat:** Unauthenticated configuration changes or SQL injection.
- **Mitigation:** All configuration forms check permissions using `Session::checkRight('config', UPDATE)`. Persistence uses native GLPI database abstraction APIs (`$DB`), bypassing raw SQL string construction entirely. All shortcut definitions are validated server-side against strict structural schemas.

### 3. Cross-Site Request Forgery (CSRF)
- **Threat:** Forging configurations using cross-site requests.
- **Mitigation:** The plugin defines `$PLUGIN_HOOKS['csrf_compliant']['hotkeys'] = true` and verifies the CSRF token on every POST request using `csrf_token()`.

### 4. Privacy & Data Logging
- **Threat:** Capturing sensitive data in tickets or keyboard tracking.
- **Mitigation:** The plugin does not send telemetry, log ticket or task contents, or record ordinary user typing. It only inspects structural keyboard combinations (modifiers + key) and immediately stops propagation if no match is found.

## Reporting a Vulnerability

If you discover a security vulnerability, please do not open a public issue. Email us directly at `japeraba@example.com`.
