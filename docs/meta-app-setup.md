# Setting up the Meta app

The plugin talks to Meta through **one Meta app** that you own. This guide sets it up for Messenger, Instagram, or both.

The admin page *Chat bots › Messenger* (and *Instagram*) shows, in its **Meta app setup** card, the exact URLs for your store. The examples below use `https://shop.example.com`.

## 1. Create the app

1. Go to [developers.facebook.com/apps](https://developers.facebook.com/apps) and create an app of type **Business**.
2. In **App settings › Basic**, copy the **App ID** and **App secret** into your store's environment:

    ```dotenv
    FACEBOOK_APP_ID=...
    FACEBOOK_APP_SECRET=...
    FACEBOOK_VERIFICATION=a-long-random-string
    ```

3. Still in **Basic**, fill in the privacy policy URL and the app domain (`shop.example.com`). Meta requires them before the app can go live.

## 2. Facebook Login (to connect Pages from the admin)

1. Add the **Facebook Login for Business** (or **Facebook Login**) product.
2. In its **Settings**, add this to **Valid OAuth Redirect URIs**:

    ```
    https://shop.example.com/admin/bots/facebook/callback
    ```

    If your admin is not under `/admin`, use your admin path.

## 3. Messenger

1. Add the **Messenger** product.
2. Under **Webhooks**, set:
    - **Callback URL**: `https://shop.example.com/webhook/messenger`
    - **Verify token**: the value of `FACEBOOK_VERIFICATION`
3. Subscribe to the **`messages`** and **`messaging_postbacks`** fields.

You do not need to generate Page tokens by hand: connecting a Page from the admin stores its token and subscribes it to the app.

## 4. Instagram

Instagram Direct is reached through the Facebook Page the Instagram account is linked to.

1. Make sure the Instagram account is a **professional** (business or creator) account, and is [linked to a Facebook Page](https://help.instagram.com/570895513091465).
2. In the Instagram app, open **Settings › Privacy › Messages › Connected tools** and turn on **Allow access to messages**.
3. Add the **Instagram** product to your Meta app (*API setup with Facebook login*).
4. Under **Webhooks**, choose the **Instagram** object and set:
    - **Callback URL**: `https://shop.example.com/webhook/instagram`
    - **Verify token**: the value of `FACEBOOK_VERIFICATION`
5. Subscribe to the **`messages`** and **`messaging_postbacks`** fields.

## 5. Permissions and going live

While the app is in **Development** mode, only people with a role on the app (admins, developers, testers) can use it, and the bot only answers them. That is enough to try everything.

To answer every customer, switch the app to **Live**. Meta asks for **App Review** of these permissions:

| Permission | Needed for |
| --- | --- |
| `pages_show_list` | listing your Pages in the admin |
| `pages_manage_metadata` | subscribing a Page to the webhooks, publishing its menu |
| `pages_messaging` | answering on Messenger |
| `instagram_basic`, `instagram_manage_messages`, `pages_read_engagement` | answering on Instagram |

Meta reviewers usually want a short screencast: a customer messaging the Page, browsing products, adding one to the cart and opening the checkout link.

## Troubleshooting

- **"Facebook lists no Pages for this account"**: the Facebook account you logged in with is not an admin of the Page, or you did not select the Page when Facebook asked which ones to share. Log out of Facebook on the admin page and log in again.
- **A connected page shows "Not receiving messages"**: click **Repair** to subscribe the Page again.
- **"Reconnect needed"**: Facebook revoked the Page token (the app was removed from the Page, or the admin's password changed). Log in with Facebook again and reconnect the page.
- **Webhook verification fails**: the verify token in the Meta app must equal `FACEBOOK_VERIFICATION`, and the callback URL must be reachable over HTTPS.
- **Messages arrive but the bot stays silent**: look for `sylius_bot` entries in your logs. A `403 Invalid signature` means `FACEBOOK_APP_SECRET` does not match the app that sends the webhooks.
