# Upgrading from 1.x to 2.0

2.0 is a rewrite for **Sylius 2** (PHP 8.2+, Symfony 6.4/7). It adds Instagram, a server-side Facebook login, conversation history and webhook signature checks. The database migration keeps your connected pages and bot users.

## 1. Requirements

- Sylius `^2.1`, PHP `^8.2`.
- `botman/driver-facebook` is no longer used; remove it from your `composer.json` if you added it yourself.

## 2. Configuration and routes

Replace the old imports.

Before:

```yaml
# config/packages/_sylius.yaml
imports:
    - { resource: '@SyliusBotPlugin/config/config.yml' }

# config/routes.yaml
sylius_bot_plugin_admin:
    resource: "@SyliusBotPlugin/config/admin_routing.yml"
    prefix: /admin
sylius_bot_plugin_sylius_bot:
    resource: "@SyliusBotPlugin/config/routes.yml"
```

After:

```yaml
# config/packages/sylius_bot.yaml
imports:
    - { resource: "@SyliusBotPlugin/config/config.yaml" }

# config/routes/sylius_bot.yaml
sylius_bot:
    resource: "@SyliusBotPlugin/config/routes.yaml"
```

The environment variables keep their names: `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, `FACEBOOK_GRAPH_VERSION`, `FACEBOOK_VERIFICATION`. `APP_URL` and `FACEBOOK_GRAPH_URL` are no longer used: links use the channel's hostname (or the request's host), and the Graph URL is fixed.

**`FACEBOOK_APP_SECRET` is now required by the webhook**: calls whose `X-Hub-Signature-256` does not match it are rejected. Make sure it is the secret of the app that sends the webhooks.

## 3. Database

```bash
bin/console doctrine:migrations:migrate
```

The migration `SyliusBotPlugin\Migrations\Version20261016000000`:

- widens `page_access_token` and `profile_picture` to `TEXT` (Meta's values routinely exceed 255 characters);
- adds `facebook_page_id`, `created_at`, `updated_at` to pages and `bot_id`, `created_at`, `updated_at` to bot users;
- drops the unused `user_id` and `timezone` columns of `sylius_bot_plugin_bot`;
- creates `sylius_bot_plugin_bot_message` for conversation history;
- sets `ON DELETE SET NULL` on the bot user's customer, so deleting a customer is no longer blocked.

Page settings saved by 1.x (`get_started_text`, `channel`, `custom_prompt`) are read as the new `greeting`, `channel_code` and `ai_instructions`.

## 4. Meta app

- Add the OAuth redirect URI `https://<your-store>/admin/bots/facebook/callback` to *Facebook Login › Settings*. The admin no longer uses the Facebook JavaScript SDK.
- The Messenger webhook URL is unchanged (`/webhook/messenger`). For Instagram, use `/webhook/instagram`.
- After upgrading, open *Chat bots › Messenger* and click **Repair** on each page to re-publish its menu with the 2.0 payloads.

## 5. Renamed things

| 1.x | 2.0 |
| --- | --- |
| resource alias `sylius_bot_plugin.bot` | `sylius_bot.bot` |
| resource alias `sylius_bot_plugin.bot_subscriber` | `sylius_bot.bot_subscriber` |
| services `sylius_bot_plugin.*` | `sylius_bot.*` |
| route `sylius_bot_plugin_facebook_persistent_menu` (`/admin/bots`) | `sylius_bot_admin_bot_index` (`/admin/bots/messenger`) |
| route `sylius_bot_plugin_sylius_bot_checkout` | `sylius_bot_shop_checkout` (same path, `/bot_checkout/{tokenValue}`) |
| grid `sylius_bot_subscriber` | `sylius_bot_admin_bot_subscriber` |
| `SyliusBotPlugin\Service\*` | `SyliusBotPlugin\Bot\*`, `SyliusBotPlugin\Connection\*`, `SyliusBotPlugin\Facebook\*` |
| templates `@SyliusBotPlugin/Facebook/...`, `Ui/...` | `@SyliusBotPlugin/admin/...` |
