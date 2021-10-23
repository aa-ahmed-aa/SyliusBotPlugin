## Prerequisites
- [create facebook app](https://developers.facebook.com/docs/messenger-platform/getting-started/app-setup)
  > Make sure your facebook page have at least this permission (messages, messaging_postbacks).
- [download ngrok](https://ngrok.com/download)

## Installation
1. Install using composer 
    ```bash 
    composer require aa-ahmed-aa/SyliusBotPlugin
    ```
2. Add this to .env
    ```dotenv
    APP_URL=<ngrok-link>
    FACEBOOK_PAGE_ACCESS_TOKEN=<fb-page-access-token>
    FACEBOOK_VERIFICATION=SYLIUSVERIFY
    FACEBOOK_GRAPH_URL=https://graph.facebook.com
    FACEBOOK_GRAPH_VERSION=v12.0
    ```
    > Note : feel free to change the FACEBOOK_VERIFICATION token as you need (this value will be used via facebook to verify the webhook).

3. Add the following import to `_sylius.yaml`:
    ```yml
    imports:
        # ...
        - { resource: '@AhmedkhdSyliusBotPlugin/Resources/config/app/config.yml' }
    
    ```

4. Import routes inside your routes.yml
    ```yml
    ahmedkhd_sylius_bot:
        resource: "@AhmedkhdSyliusBotPlugin/Resources/config/routes.yml"
    ```

5. Run `php bin/console doctrine:schema:update --force`

6. start ngrok with 

7. open Developers facebook platform > Messenger > Settings
    ```dotenv
    Callback URL : <NGROK_LINK>/webhook/messenger
    Verify Token : SYLIUSVERIFY
    ```

8. Open your page and start talking to you bot 


