<h1 align="center">
    <img width="100" height="100" src="https://github.com/aa-ahmed-aa/SyliusBotPlugin/blob/master/docs/resources/logo.png" />
    <p>
    Sylius Bot Plugin
    </p>
</h1>

<p align="center">Facebook messenger shopping for sylius to give your store a new shoping experience</p>

<p align="center">
    <a href="https://sylius.com" target="_blank">
        <img src="https://github.com/aa-ahmed-aa/SyliusBotPlugin/blob/master/docs/resources/demo_gif.gif" />
    </a>
</p>

## Screenshots
<img width="800" height="600" src="https://github.com/aa-ahmed-aa/SyliusBotPlugin/blob/master/docs/resources/screen_1.png">
<img width="800" height="600" src="https://github.com/aa-ahmed-aa/SyliusBotPlugin/blob/master/docs/resources/screen_2.png">

## Pre-installation
- [create facebook app](https://developers.facebook.com/docs/messenger-platform/getting-started/app-setup)
  > Make sure your facebook page have at least this permission (messages, messaging_postbacks).
- [download ngrok](https://ngrok.com/download)

## Installation
1. Install using composer 
    ```bash 
    composer require ahmedkhd/sylius-bot-plugin
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

8. login to sylius admin dashboard and got to `Messenger` tab and click on update
    - in this step your presistent menu and get started button yould be updated on the facebook page
    - make sure the persistent menu criteria is fulfilled [here](https://developers.facebook.com/docs/messenger-platform/send-messages/persistent-menu/#set_menu)

9. Open your page and start talking to you bot 



## Contribution
- [Contribution Guide](https://github.com/aa-ahmed-aa/SyliusBotPlugin/blob/master/docs/CUSTOMIZATION.md)
