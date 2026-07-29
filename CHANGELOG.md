# Changelog

## 1.2.1

- Moved System Logs, Request Logs, and Audit Trails to Plugin → Platform.
- Updated dashboard links to the new canonical Plugin URLs.
- Kept old Settings URLs redirecting through Platform's canonical resolver.

## 1.2.0

- Added request activity, request error rate, recent server errors, and recent audit dashboard widgets.

## 1.1.1 - 2026-07-29

- Replaced all `nuewire::` Livewire runtime aliases with portable flat aliases for Livewire 4 compatibility.

## 1.1.0 - 2026-07-29

- Moved System Logs, Request Logs, and Audit Trails to Settings → Platform.
- Added namespaced page IDs and Platform 2 contextual-navigation metadata.

All notable changes to `nuewire/logs` are documented here.

## 1.0.0 - 2026-07-29

- Added Audit Trails, Request Logs, and System Logs platform pages.
- Added Activitylog v4/v5 compatibility and the sanitized `AuditLogger` wrapper.
- Added configurable global request logging with request IDs and sensitive-data redaction.
- Added bounded Laravel system-log discovery, filtering, tailing, and ACL-protected clearing.
- Added installer and retention-pruning commands.
- Added Indonesian and English translations, package tests, and integration documentation.
