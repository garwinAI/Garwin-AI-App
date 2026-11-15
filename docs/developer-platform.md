# Garwin Developer Platform Guide

## Status

This guide defines the minimum package contract for Garwin apps, widgets, and themes. The Developer App currently provides documentation only. Package upload, automated validation, review, installation, updates, and the isolated runtime are not enabled yet. Do not treat a package as installable until those services are released.

## Product types

Every package has exactly one type:

- **App**: a complete workspace tool with its own view and navigation entry.
- **Widget**: a small, wide, or full dashboard panel with an optional settings form.
- **Theme**: a set of approved design tokens that changes colors, type, and surface styling.

Version 1 packages are ZIP archives with `manifest.json` at the archive root. Keep the package self-contained; do not include a PHP, Node, Python, shell, or database server.

```text
focus-widget/
  manifest.json
  README.md
  LICENSE
  index.html
  assets/
    widget.css
    widget.js
```

The smallest valid package contains `manifest.json`, `README.md`, `LICENSE`, and every file declared in `manifest.json`. App and widget entry points are static browser files. A theme uses `theme.json` as its entry point.

## Required manifest

All manifest keys below are required unless identified as optional. IDs are permanent reverse-domain identifiers controlled by the developer. Use semantic versions and increment the version for every published change.

```json
{
  "schemaVersion": 1,
  "id": "com.example.focus-widget",
  "type": "widget",
  "name": "Focus Widget",
  "version": "1.0.0",
  "description": "A concise focus timer for your dashboard.",
  "minimumPlatformVersion": "1.0.0",
  "entrypoint": "index.html",
  "files": [
    "index.html",
    "assets/widget.css",
    "assets/widget.js"
  ],
  "permissions": [],
  "configuration": [],
  "widget": {
    "defaultSize": "small",
    "allowedSizes": ["small", "wide", "full"]
  }
}
```

Use only package-relative paths. `files` is the complete allowlist of package files; reject archives containing undeclared files, absolute paths, `..` path segments, symlinks, or case-colliding paths. The `widget` block is required only for widgets. Apps and themes must not include it.

## Type-specific rules

### Apps

- Use a responsive static web entry point. The platform owns the surrounding navigation and account chrome.
- Apps execute in a cross-origin sandboxed frame with scripts enabled only when the package requires them. The frame must not receive Garwin session cookies, local storage, filesystem paths, or database credentials.
- Direct network access is denied by default. Apps request narrowly scoped platform capabilities through a validated message bridge; the server authorizes every request for the signed-in user.
- Apps cannot include or execute server-side code. A service that needs a backend must use an explicitly approved Garwin API or a separately operated service reviewed for that product.

### Widgets

- Widgets use the same isolated browser runtime as apps and render only in the dashboard slots offered by the platform.
- `defaultSize` must be one of `small`, `wide`, or `full`. `allowedSizes` must contain one or more of those values.
- Keep each widget focused, responsive, keyboard accessible, and usable when its optional permissions are denied.
- Widgets cannot read another user's data or change the dashboard layout outside the host controls.

### Themes

- Themes are declarative JSON tokens only. Arbitrary CSS, JavaScript, HTML, font code, and remote assets are not permitted in version 1.
- Use `theme.json` as the entry point and change only tokens supported by the platform.
- Include readable foreground/background contrast, a visible focus treatment, and a preview image made from package-local assets.

```json
{
  "schemaVersion": 1,
  "tokens": {
    "color.brand": "#245fba",
    "color.page": "#f4f5f7",
    "color.surface": "#ffffff",
    "color.text": "#252a32",
    "color.muted": "#626b78",
    "color.border": "#dfe3e8",
    "radius.card": "12px"
  }
}
```

The accepted theme token names and value formats are controlled by the platform, not by a package. Unknown tokens and CSS expressions are rejected.

## Configuration and permissions

Declare only settings the product needs. Each setting has a unique `key`, a user-facing `label`, a supported `type`, and an optional `required`, `default`, and `options` value.

Supported settings in version 1 are `text`, `number`, `email`, `url`, `password`, `select`, `checkbox`, and `color`. Define validation bounds for numbers and explicit allowed values for selects. Do not put secrets or user data in package files or defaults. Password settings are stored encrypted and are never returned to ordinary pages after saving.

Packages start with no permissions. Ask for the minimum capability required and explain it in the product listing. Initial capability names are:

- `workspace.profile.read`
- `calendar.events.read`
- `storage.files.read`
- `storage.files.write`
- `notifications.create`

Permission grants are per user and per product. The installer shows the requested capabilities before consent. Changing a published version to request new capabilities requires fresh consent. Unsupported capability names fail validation.

## Build, validate, and submit

Before submission:

- Keep the package root flat enough to find `manifest.json` immediately.
- Check that the manifest is valid JSON, uses schema version `1`, has a valid reverse-domain ID and semantic version, and lists every shipped file.
- Confirm that entry points exist, settings and permissions are valid for the package type, and there are no secrets, hidden environment files, or test data in the ZIP.
- Test keyboard operation, narrow screens, empty states, denied permissions, and failure responses.
- Include setup instructions, support contact, privacy behavior, license, and a changelog in `README.md` or the product listing.

The planned package validator must reject traversal, symlinks, duplicate/case-colliding paths, undeclared files, oversized archives, decompression bombs, invalid manifests, unsupported permissions, and executable server-side files. Proposed first-release limits are 25 MB compressed, 100 MB expanded, 1,000 files, and eight directory levels.

## Review, release, and installation

The release pipeline is designed to work in this order:

- Upload the ZIP into quarantine storage outside the public web root.
- Validate the manifest and archive without extracting untrusted paths.
- Scan the files, calculate a SHA-256 digest, and produce a validation report.
- Review the listing, requested permissions, privacy information, and package behavior.
- Approve an immutable version and store its verified digest.
- On installation, verify ownership or free eligibility, compatibility, consent, quota, and package digest before registering the product.
- Record the installed product and keep its version and configuration scoped to that user.

Published versions are immutable. Updates are new versions and must pass validation and review again. Show the changelog and permission changes before updating. Rollback is permitted only to a previously approved version. Uninstalling removes package files but must preserve user-created data unless the user confirms its deletion.

Paid AppZone items use Stripe Checkout; a product is not owned until a verified payment webhook records the purchase. Version 1 package pricing is a free or one-time price. Subscription products, developer payouts, and automatic marketplace revenue sharing are future work and must not be represented as active.

## Security rules

- Never execute developer-supplied PHP or other server code in the Garwin application process.
- Never extract an archive before validating every path and enforcing compressed and expanded size limits.
- Keep packages and user configuration outside the public web root. Serve verified static package files from an isolated origin.
- Sandbox app and widget frames without same-origin, top-navigation, popup, form, or unrestricted network privileges.
- Validate the origin, source frame, message schema, capability, user ownership, and request rate for every host bridge message.
- Escape developer listing text on output. Treat package metadata, screenshots, filenames, and all client messages as untrusted input.
- Do not log secrets, personal configuration values, or private user content.

## Current Developer App

Open **Developer App** from the workspace navigation or install **Garwin Developer** from AppZone. The page is the package guide and contract; it does not yet accept applications or ZIP uploads. Publishing, package execution, installation, product analytics, and developer payouts remain disabled until their review, storage, and sandbox services are implemented.
