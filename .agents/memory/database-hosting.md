---
name: SQLite and MySQL hosting
description: Project-specific database portability and AshTech webhook fallback requirements.
---

The project must continue to use SQLite in Replit while supporting a conventional PHP/MySQL host. Preserve existing hosted database data and use additive schema changes only.

**Why:** The user explicitly asked for the app to work both here and on their existing SQL host; the old hosted version was already working before the AshTech API update.

**How to apply:** Keep SQLite as the default when no MySQL configuration is present. Read hosted MySQL settings from environment variables or a private, ignored local configuration file. Do not import the full schema over an existing hosted database.

The older hosted payment integration did not have an AshTech webhook secret.

**Why:** The user confirmed the old setup had no webhook secret and asked that its absence not disrupt payments.

**How to apply:** Never accept unsigned webhooks as proof of payment. Allow payment initiation and server-side status verification to work without the webhook secret, and explain that automatic webhook delivery requires configuring one.
