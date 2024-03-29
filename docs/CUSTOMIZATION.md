## Contribution
### Requirements
  - symfony cli
  - php
  - composer
  - yarn
  - mysql

### Setup environemnt
1. execute `docker compose up --build -d` to run the mysql engine with phpmyadmin dashboard.
2. execute `composer install` in the plugin root folder.
3. cd inside `tests/Application` then execute `yarn install` and `yarn build`.
4. edit `tests/Application/.env` to have the correct env variables. **[basic .env](https://github.com/Sylius/PluginSkeleton/blob/1.12/tests/Application/.env)** + **[plugin based .env](https://github.com/aa-ahmed-aa/SyliusBotPlugin#installation)**
5. exec `bin/console sylius:install --no-interaction` follow the guide to fix any issue(timezone enabled plugins, etc).
6. exec `bin/console doctrine:schema:update --no-interaction --force`
7. exec `bin/console sylius:fixtures:load --no-interaction`
8. exec `symfony server:start --port=9090` and open `http:localhost:9090`
9. exec `ngrok http 9090` and update the `APP_URL` in .env with the https link.
    sometimes ngrok is not setting the correct headers for the webhook to work properly you can use this command instead `ngrok http https://localhost:9090 --host-header="localhost:9090"`
### Running plugin tests

  - PHPUnit

    ```bash
    vendor/bin/phpunit
    ```

  - PHPSpec

    ```bash
    vendor/bin/phpspec run
    ```

  - Behat (non-JS scenarios)

    ```bash
    vendor/bin/behat --strict --tags="~@javascript"
    ```

  - Behat (JS scenarios)
 
    1. [Install Symfony CLI command](https://symfony.com/download).
 
    2. Start Headless Chrome:
    
      ```bash
      google-chrome-stable --enable-automation --disable-background-networking --no-default-browser-check --no-first-run --disable-popup-blocking --disable-default-apps --allow-insecure-localhost --disable-translate --disable-extensions --no-sandbox --enable-features=Metal --headless --remote-debugging-port=9222 --window-size=2880,1800 --proxy-server='direct://' --proxy-bypass-list='*' http://127.0.0.1
      ```
    
    3. Install SSL certificates (only once needed) and run test application's webserver on `127.0.0.1:8080`:
    
      ```bash
      symfony server:ca:install
      APP_ENV=test symfony server:start --port=8080 --dir=tests/Application/public --daemon
      ```
    
    4. Run Behat:
    
      ```bash
      vendor/bin/behat --strict --tags="@javascript"
      ```
    
  - Static Analysis
  
    - Psalm
    
      ```bash
      vendor/bin/psalm
      ```
      
    - PHPStan
    
      ```bash
      vendor/bin/phpstan analyse -c phpstan.neon -l max src/  
      ```

  - Coding Standard
  
    ```bash
    vendor/bin/ecs check src
    ```

