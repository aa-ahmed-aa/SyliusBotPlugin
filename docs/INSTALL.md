## Installation
- Install using composer 
```bash 
$ composer require aa-ahmed-aa/SyliusBotPlugin
```
- Add this to .env
```dotenv
FACEBOOK_PAGE_ACCESS_TOKEN=<fb-page-access-token>
FACEBOOK_VERIFICATION=syliusgood
FACEBOOK_GRAPH_URL=https://graph.facebook.com
FACEBOOK_GRAPH_VERSION=v12.0
```
> Note : feel free to change the FACEBOOK_VERIFICATION token as you need (this value will be used via facebook to verify the webhook).

- Add the following import to `_sylius.yaml`:
```yml
imports:
    # ...
    - { resource: '@AhmedkhdSyliusBotPlugin/Resources/config/app/config.yml' }

```

- Import routes inside your routes.yml
```yml
ahmedkhd_sylius_bot:
    resource: "@AhmedkhdSyliusBotPlugin/Resources/config/routes.yml"
```
